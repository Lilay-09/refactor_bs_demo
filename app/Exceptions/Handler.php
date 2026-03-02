<?php

namespace App\Exceptions;

use ApiResponse;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
// use Illuminate\Http\JsonResponse;
use Log;
use Throwable;
use Symfony\Component\HttpKernel\Exception\HttpException;

class Handler extends ExceptionHandler
{
    /**
     * Report or log an exception.
     *
     * @param  \Throwable  $exception
     * @return void
     */
    public function report(Throwable $exception)
    {
        // Log the exception details for debugging purposes
        // \Log::error('Caught exception:', ['exception' => $exception]);

        parent::report($exception);
    }

    /**
     * Render an exception into an HTTP response.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Throwable  $exception
     * @return \Illuminate\Http\Response
     */
    public function render($request, Throwable $exception)
    {
        // Check if the exception is an HTTP Exception with a 500 status code
        if ($exception instanceof HttpException && $exception->getStatusCode() === 500) {

            // Return a custom 500 JSON response
            return ApiResponse::Error('Internal Server Error');
        }

        if ($exception instanceof BadRequestExcept) {
            return ApiResponse::error($exception->getMessage());
        }

        if ($exception instanceof ForbiddenExcept) {
            return ApiResponse::error($exception->getMessage());
        }

        // If the request is for API, return a generic 500 response for other errors
        if ($exception instanceof HttpException && $exception->getStatusCode() === 405){
            return ApiResponse::JsonRaw([
                'error' => true,
                'status' => 'Method Not Allowed',
                'message' => 'Not Allowed',
                'errors' => []
            ],$exception->getStatusCode());
        }

        if ($exception instanceof HttpException && $exception->getStatusCode() === 404){
            return ApiResponse::JsonRaw([
                'error' => true,
                'status' => 'Not Found',
                'message' => 'Not Found',
                'errors' => []
            ],$exception->getStatusCode());
        }

        if ($request->is('api/*')) {
            return ApiResponse::Error('Internal Server Error');
        }

        // Fall back to the default rendering for all other exceptions
        return parent::render($request, $exception);
    }

    /**
     * Convert an authentication exception into an unauthenticated response.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Illuminate\Auth\AuthenticationException  $exception
     * @return \Illuminate\Http\Response
     */
    protected function unauthenticated($request, AuthenticationException $exception)
    {
        return response()->json(['error' => 'Unauthenticated'], 401);
    }
}
