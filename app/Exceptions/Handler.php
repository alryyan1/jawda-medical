<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Throwable;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

class Handler extends ExceptionHandler
{
    /**
     * A list of the exception types that are not reported.
     *
     * @var array<int, class-string<Throwable>>
     */
    protected $dontReport = [
        //
    ];

    /**
     * A list of the inputs that are never flashed for validation exceptions.
     *
     * @var array<string, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Register the exception handling callbacks for the application.
     */
    public function register(): void
    {
        $this->reportable(function (Throwable $e) {
            // You can add custom reporting logic here if needed
            // For example, sending errors to Sentry, Bugsnag, etc.
        });

        // You can also use $this->renderable() for more fine-grained control
        // over rendering specific exception types.
    }

    /**
     * Prepare a JSON response for the given exception.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Throwable  $e
     * @return \Illuminate\Http\JsonResponse
     */
    protected function prepareJsonResponse($request, Throwable $e): JsonResponse
    {
        $status = $e instanceof HttpExceptionInterface ? $e->getStatusCode() : 500;
        $headers = $e instanceof HttpExceptionInterface ? $e->getHeaders() : [];

        return new JsonResponse(
            $this->convertExceptionToArray($e), $status, $headers,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
        );
    }

    /**
     * Convert the given exception to an array.
     *
     * @param  \Throwable  $e
     * @return array
     */
    protected function convertExceptionToArray(Throwable $e): array
    {
        // Start with Laravel's default array conversion
        $data = parent::convertExceptionToArray($e);

        // Add detailed information ONLY if in local/development environment
        if (config('app.debug') && app()->environment(['local', 'development', 'testing'])) { // Added 'testing'
            $data['exception'] = get_class($e); // The class name of the exception
            $data['file'] = $e->getFile();
            $data['line'] = $e->getLine();
            $data['trace'] = collect($e->getTrace())->map(function ($trace) {
                return Arr::except($trace, ['args']); // Remove voluminous 'args'
            })->all();
        } else {
            // For production, ensure sensitive details are removed.
            // The parent::convertExceptionToArray already hides details if app.debug is false.
            // You might want to ensure 'trace', 'file', 'line' are explicitly unset here if parent doesn't.
            unset($data['file'], $data['line'], $data['trace'], $data['exception']);
            // Ensure a generic message for production if not already set by parent
            if (empty($data['message'])) {
                 $data['message'] = $e instanceof HttpExceptionInterface ? $e->getMessage() : 'Server Error';
            }
        }
        
        // If it's a validation exception, Laravel's default already includes 'errors'
        // You can add more custom handling for specific exception types here if needed

        return $data;
    }

    /**
     * Render an exception into an HTTP response.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Throwable  $exception
     * @return \Symfony\Component\HttpFoundation\Response
     *
     * @throws \Throwable
     */
    public function render($request, Throwable $exception)
    {
        // If the request expects JSON (e.g., API request)
        if ($request->expectsJson()) {
            $status = $exception instanceof HttpExceptionInterface ? $exception->getStatusCode() : 500;
            $response = [
                'message' => $exception->getMessage() ?: 'An error occurred.',
            ];

            // Add validation errors if present
            if ($exception instanceof \Illuminate\Validation\ValidationException) {
                $response['errors'] = $exception->errors();
                $status = $exception->status; // Use validation exception status (usually 422)
            }

            // Always include file and line per requirement
            $response['file'] = $exception->getFile();
            $response['line'] = $exception->getLine();

            // Add detailed debug information ONLY in local/development environment
            if (config('app.debug') && app()->environment(['local', 'development'])) {
                $response['exception'] = get_class($exception);
                $response['trace'] = collect($exception->getTrace())->map(function ($trace) {
                    return Arr::except($trace, ['args']);
                })->all();
            }

            return response()->json($response, $status);
        }

        return parent::render($request, $exception);
    }
}