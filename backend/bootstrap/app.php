<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;
use Illuminate\Routing\Exceptions\InvalidSignatureException;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'role' => \App\Http\Middleware\RoleMiddleware::class,
            'is_admin' => \App\Http\Middleware\IsAdmin::class,
            'active_user' => \App\Http\Middleware\EnsureUserIsActive::class,
            'verified' => \Illuminate\Auth\Middleware\EnsureEmailIsVerified::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $apiError = static function (string $message, int $status, array $extra = []) {
            return response()->json(array_merge(['message' => $message], $extra), $status);
        };

        $exceptions->render(function (AuthenticationException $exception, Request $request) use ($apiError) {
            if (! $request->is('api/*')) {
                return null;
            }

            return $apiError('Unauthorized', 401);
        });

        $exceptions->render(function (AuthorizationException $exception, Request $request) use ($apiError) {
            if (! $request->is('api/*')) {
                return null;
            }

            $message = trim($exception->getMessage());
            if ($message === 'auth.verify_email') {
                $message = 'Please verify your email before accessing this resource.';
            }

            if ($message === '' || $message === 'This action is unauthorized.') {
                $message = 'Forbidden';
            }

            return $apiError($message, 403);
        });

        $exceptions->render(function (ModelNotFoundException $exception, Request $request) use ($apiError) {
            if (! $request->is('api/*')) {
                return null;
            }

            return $apiError('Resource not found', 404);
        });

        $exceptions->render(function (NotFoundHttpException $exception, Request $request) use ($apiError) {
            if (! $request->is('api/*')) {
                return null;
            }

            if ($exception->getPrevious() instanceof ModelNotFoundException) {
                return $apiError('Resource not found', 404);
            }

            return $apiError('Route not found', 404);
        });

        $exceptions->render(function (InvalidSignatureException $exception, Request $request) use ($apiError) {
            if (! $request->is('api/*')) {
                return null;
            }

            return $apiError('Invalid or expired verification link.', 403);
        });

        $exceptions->render(function (ValidationException $exception, Request $request) use ($apiError) {
            if (! $request->is('api/*')) {
                return null;
            }

            return $apiError('Validation error', 422, [
                'errors' => $exception->errors(),
            ]);
        });

        $exceptions->render(function (ThrottleRequestsException $exception, Request $request) use ($apiError) {
            if (! $request->is('api/*')) {
                return null;
            }

            $response = $apiError('Too many requests', 429);
            foreach ($exception->getHeaders() as $key => $value) {
                $response->headers->set($key, $value);
            }

            return $response;
        });

        $exceptions->render(function (HttpExceptionInterface $exception, Request $request) use ($apiError) {
            if (! $request->is('api/*')) {
                return null;
            }

            $status = $exception->getStatusCode();
            $message = trim($exception->getMessage());

            if ($status === 403 && $message === 'Your email address is not verified.') {
                $message = 'Please verify your email before accessing this resource.';
            }

            if ($message === '') {
                $message = SymfonyResponse::$statusTexts[$status] ?? 'HTTP error';
            }

            return $apiError($message, $status);
        });

        $exceptions->render(function (\Throwable $exception, Request $request) use ($apiError) {
            if (! $request->is('api/*')) {
                return null;
            }

            return $apiError('Internal server error', 500);
        });
    })->create();
