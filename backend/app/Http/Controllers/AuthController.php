<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Requests\Auth\ResendVerificationEmailRequest;
use App\Http\Resources\UserResource;
use App\Mail\WelcomePlayer;
use App\Models\PlayerProfile;
use App\Models\TeamInvite;
use App\Models\User;
use App\Support\StatusResolver;
use Illuminate\Auth\Events\Verified;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function register(RegisterRequest $request): JsonResponse
    {
        $validated = $request->validated();

        try {
            $user = DB::transaction(function () use ($validated): User {
                $email = Str::lower($validated['email']);

                $user = User::create([
                    'name' => $this->formatName(
                        $validated['first_name'],
                        $validated['last_name']
                    ),
                    'email' => $email,
                    'phone' => $validated['phone'] ?? null,
                    'password_hash' => Hash::make($validated['password']),
                    'role' => 'player',
                    'is_active' => true,
                ]);

                PlayerProfile::updateOrCreate(
                    ['user_id' => $user->id],
                    [
                        'first_name' => $validated['first_name'],
                        'last_name' => $validated['last_name'],
                        'dni' => $validated['dni'] ?? null,
                        'province_state' => filled($validated['province_state'] ?? null)
                            ? $validated['province_state']
                            : 'Unknown',
                        'ranking_source' => 'NONE',
                        'ranking_value' => null,
                        'ranking_updated_at' => null,
                    ],
                );

                TeamInvite::query()
                    ->where('invited_email', $email)
                    ->whereNull('invited_user_id')
                    ->where(
                        'status_id',
                        StatusResolver::getId('team_invite', 'sent')
                    )
                    ->update([
                        'invited_user_id' => $user->id,
                    ]);

                $this->sendEmailVerification($user);

                return $user;
            });
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'message' => 'No se pudo completar el registro en este momento. Inténtalo nuevamente o contacta soporte.',
                'error_code' => 'VERIFICATION_EMAIL_SEND_FAILED',
                'support_email' => config('mail.support_email'),
            ], 503);
        }

        $this->sendWelcomeEmail($user);

        return response()->json([
            'message' => 'Account created. Please verify your email before logging in.',
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

        return $this->loginPayloadResponse($user);
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
                return response()->json([
                    'message' => 'Email is already verified.',
                ]);
            }

            return redirect()->to(
                rtrim((string) config('app.frontend_url'), '/').'/login?verified=1'
            );
        }

        if ($user->markEmailAsVerified()) {
            event(new Verified($user));
        }

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Email verified successfully.',
            ]);
        }

        return redirect()->to(
            rtrim((string) config('app.frontend_url'), '/').'/login?verified=1'
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
        }

        return response()->json([
            'message' => 'If the account exists and is not yet verified, a verification email has been sent.',
        ]);
    }

    private function loginPayloadResponse(User $user): JsonResponse
    {
        $token = $user
            ->createToken('auth_token')
            ->plainTextToken;

        return response()->json([
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