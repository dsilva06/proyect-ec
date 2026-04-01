<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Requests\Auth\ResendVerificationEmailRequest;
use App\Http\Resources\UserResource;
use App\Mail\PendingRegistrationVerificationMail;
use App\Mail\WelcomePlayer;
use App\Models\PlayerProfile;
use App\Models\TeamInvite;
use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function register(RegisterRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $this->purgeLegacyUnverifiedRegistrationRecords($validated);

        try {
            $verificationContext = $this->sendPendingRegistrationVerification(
                $this->pendingRegistrationPayload($validated)
            );
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'message' => 'No se pudo completar el registro en este momento. Inténtalo nuevamente o contacta soporte.',
                'error_code' => 'VERIFICATION_EMAIL_SEND_FAILED',
                'support_email' => config('mail.support_email'),
            ], 503);
        }

        return response()->json([
            'message' => 'Account created. Please verify your email before logging in.',
            'verification_context' => $verificationContext,
        ], 201);
    }

    /**
     * @throws ValidationException
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $user = User::query()
            ->where('email', Str::lower($validated['email']))
            ->first();

        if (! $this->validateCredentials($user, $validated['password'])) {
            throw ValidationException::withMessages([
                'email' => ['Invalid credentials'],
            ]);
        }

        if (! $user->is_active) {
            throw ValidationException::withMessages([
                'email' => ['Account is inactive'],
            ]);
        }

        if (! $user->hasVerifiedEmail()) {
            return response()->json([
                'message' => 'Please verify your email before logging in.',
            ], 403);
        }

        return $this->authPayloadResponse($user);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()?->currentAccessToken()?->delete();

        return response()->json([
            'message' => 'Logged out successfully',
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json([
            'user' => new UserResource(
                $request->user()->load('playerProfile')
            ),
        ]);
    }

    /**
     * @throws ModelNotFoundException
     */
    public function verifyEmail(
        Request $request,
        int $id,
        string $hash
    ): JsonResponse|RedirectResponse {
        $pendingRegistration = $this->pendingRegistrationFromRequest($request, $hash);

        if ($pendingRegistration !== null) {
            [$user, $wasJustVerified] = $this->createVerifiedUserFromPendingRegistration($pendingRegistration);

            if ($wasJustVerified) {
                event(new Verified($user));
                $this->sendWelcomeEmail($user);
            }

            if ($request->expectsJson()) {
                return $this->authPayloadResponse($user, [
                    'message' => $wasJustVerified
                        ? 'Email verified successfully.'
                        : 'Email is already verified.',
                    'verified' => true,
                ]);
            }

            return redirect()->to(
                rtrim((string) config('app.frontend_url'), '/').'/verify-email?status=verified'
            );
        }

        $user = User::query()->findOrFail($id);

        if (! hash_equals((string) $hash, sha1($user->getEmailForVerification()))) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Invalid verification link.',
                ], 403);
            }

            return redirect()->to(
                rtrim((string) config('app.frontend_url'), '/').'/verify-email?status=invalid_or_expired'
            );
        }

        if ($user->hasVerifiedEmail()) {
            if ($request->expectsJson()) {
                return $this->authPayloadResponse($user, [
                    'message' => 'Email is already verified.',
                    'verified' => true,
                ]);
            }

            return redirect()->to(
                rtrim((string) config('app.frontend_url'), '/').'/verify-email?status=verified'
            );
        }

        if ($user->markEmailAsVerified()) {
            event(new Verified($user));
        }

        if ($request->expectsJson()) {
            return $this->authPayloadResponse($user, [
                'message' => 'Email verified successfully.',
                'verified' => true,
            ]);
        }

        return redirect()->to(
            rtrim((string) config('app.frontend_url'), '/').'/verify-email?status=verified'
        );
    }

    public function resendVerificationEmail(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->hasVerifiedEmail()) {
            return response()->json([
                'message' => 'Email is already verified.',
            ]);
        }

        $this->sendEmailVerification($user);

        return response()->json([
            'message' => 'Verification email sent.',
        ]);
    }

    public function publicResendVerificationEmail(ResendVerificationEmailRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $user = User::query()
            ->where('email', Str::lower($validated['email']))
            ->first();

        if ($user && ! $user->hasVerifiedEmail()) {
            $this->sendEmailVerification($user);
        } elseif (! empty($validated['verification_context'])) {
            $pendingRegistration = $this->pendingRegistrationFromContext($validated['verification_context']);

            if ($pendingRegistration && Str::lower($pendingRegistration['email']) === Str::lower($validated['email'])) {
                $this->sendPendingRegistrationVerification($pendingRegistration, $validated['verification_context']);
            }
        }

        $response = [
            'message' => 'If the account exists and is not yet verified, a verification email has been sent.',
        ];

        if (! empty($validated['verification_context'])) {
            $response['verification_context'] = $validated['verification_context'];
        }

        return response()->json($response);
    }

    private function pendingRegistrationPayload(array $validated): array
    {
        return [
            'first_name' => trim((string) $validated['first_name']),
            'last_name' => trim((string) $validated['last_name']),
            'dni' => trim((string) $validated['dni']),
            'email' => Str::lower((string) $validated['email']),
            'phone' => $validated['phone'] ?? null,
            'province_state' => $validated['province_state'] ?? null,
            'password_hash' => Hash::make((string) $validated['password']),
        ];
    }

    private function purgeLegacyUnverifiedRegistrationRecords(array $validated): void
    {
        $email = Str::lower((string) $validated['email']);
        $dni = trim((string) $validated['dni']);
        $phone = filled($validated['phone'] ?? null) ? (string) $validated['phone'] : null;

        DB::transaction(function () use ($email, $dni, $phone): void {
            User::query()
                ->whereNull('email_verified_at')
                ->where(function ($query) use ($email, $dni, $phone): void {
                    $query->where('email', $email)
                        ->orWhereHas('playerProfile', fn ($playerProfileQuery) => $playerProfileQuery->where('dni', $dni));

                    if ($phone !== null) {
                        $query->orWhere('phone', $phone);
                    }
                })
                ->get()
                ->each(function (User $user): void {
                    $user->tokens()->delete();
                    $user->delete();
                });
        });
    }

    private function sendPendingRegistrationVerification(array $payload, ?string $verificationContext = null): string
    {
        $verificationContext ??= Crypt::encryptString(json_encode($payload, JSON_THROW_ON_ERROR));
        $frontendUrl = rtrim((string) config('app.frontend_url'), '/');
        $apiUrl = rtrim((string) config('app.url'), '/');
        $verificationExpireMinutes = (int) config('auth.verification.expire', 60);
        $relativeVerificationApiUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes($verificationExpireMinutes),
            [
                'id' => 0,
                'hash' => sha1((string) $payload['email']),
                'registration' => $verificationContext,
            ],
            absolute: false,
        );
        $verificationApiUrl = rtrim((string) preg_replace('#/api/?$#', '', $apiUrl), '/').$relativeVerificationApiUrl;

        Mail::to($payload['email'])->send(new PendingRegistrationVerificationMail(
            $this->formatName((string) $payload['first_name'], (string) $payload['last_name']),
            $apiUrl.'/emails/estars-logo.png',
            $frontendUrl.'/?verify_url='.rawurlencode($verificationApiUrl),
            $frontendUrl.'/login',
        ));

        return $verificationContext;
    }

    private function pendingRegistrationFromRequest(Request $request, string $hash): ?array
    {
        $encryptedContext = trim((string) $request->query('registration', ''));

        if ($encryptedContext === '') {
            return null;
        }

        $payload = $this->pendingRegistrationFromContext($encryptedContext);

        if (! $payload) {
            return null;
        }

        if (! hash_equals((string) $hash, sha1((string) $payload['email']))) {
            return null;
        }

        return $payload;
    }

    private function pendingRegistrationFromContext(string $encryptedContext): ?array
    {
        try {
            $decrypted = Crypt::decryptString($encryptedContext);
        } catch (DecryptException) {
            return null;
        }

        $payload = json_decode($decrypted, true);

        if (! is_array($payload)) {
            return null;
        }

        foreach (['first_name', 'last_name', 'dni', 'email', 'password_hash'] as $requiredField) {
            if (! array_key_exists($requiredField, $payload) || ! is_string($payload[$requiredField])) {
                return null;
            }
        }

        $payload['email'] = Str::lower(trim((string) $payload['email']));
        $payload['phone'] = filled($payload['phone'] ?? null) ? (string) $payload['phone'] : null;
        $payload['province_state'] = filled($payload['province_state'] ?? null) ? (string) $payload['province_state'] : null;

        return $payload;
    }

    private function createVerifiedUserFromPendingRegistration(array $payload): array
    {
        $email = Str::lower((string) $payload['email']);
        $wasJustVerified = false;

        $user = DB::transaction(function () use ($payload, $email, &$wasJustVerified): User {
            $user = User::query()->where('email', $email)->first();

            if ($user && $user->hasVerifiedEmail()) {
                return $user;
            }

            $user = $user ?? new User();
            $user->name = $this->formatName((string) $payload['first_name'], (string) $payload['last_name']);
            $user->email = $email;
            $user->phone = $payload['phone'] ?? null;
            $user->password_hash = (string) $payload['password_hash'];
            $user->role = 'player';
            $user->is_active = true;
            $user->save();

            PlayerProfile::updateOrCreate(
                ['user_id' => $user->id],
                [
                    'first_name' => (string) $payload['first_name'],
                    'last_name' => (string) $payload['last_name'],
                    'dni' => (string) $payload['dni'],
                    'province_state' => filled($payload['province_state'] ?? null)
                        ? (string) $payload['province_state']
                        : 'Unknown',
                    'ranking_source' => 'NONE',
                    'ranking_value' => null,
                    'ranking_updated_at' => null,
                ],
            );

            TeamInvite::query()
                ->where('invited_email', $email)
                ->whereNull('invited_user_id')
                ->whereHas('status', fn ($query) => $query->whereIn('code', ['pending', 'sent']))
                ->update([
                    'invited_user_id' => $user->id,
                ]);

            if (! $user->hasVerifiedEmail()) {
                $user->markEmailAsVerified();
                $wasJustVerified = true;
            }

            return $user;
        });

        return [$user->fresh()->load('playerProfile'), $wasJustVerified];
    }

    private function authPayloadResponse(User $user, array $extra = []): JsonResponse
    {
        $token = $user
            ->createToken('auth_token')
            ->plainTextToken;

        return response()->json([
            ...$extra,
            'token' => $token,
            'user' => new UserResource(
                $user->load('playerProfile')
            ),
        ]);
    }

    private function formatName(
        string $firstName,
        string $lastName
    ): string {
        return trim($firstName.' '.$lastName);
    }

    private function validateCredentials(
        ?User $user,
        string $password
    ): bool {
        $hash = $user?->password_hash
            ?? '$2y$12$'.str_repeat('0', 53);

        return Hash::check($password, $hash) && $user !== null;
    }

    private function sendWelcomeEmail(User $user): void
    {
        try {
            Mail::to($user->email)
                ->queue(new WelcomePlayer($user));
        } catch (\Throwable $e) {
            report($e);
        }
    }

    private function sendEmailVerification(User $user): void
    {
        $user->sendEmailVerificationNotification();
    }
}
