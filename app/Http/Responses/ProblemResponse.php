<?php

namespace App\Http\Responses;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * RFC 7807 problem+json response. The schema matches the Problem component
 * in openapi/spec.yaml.
 */
class ProblemResponse extends JsonResponse
{
    public function __construct(
        int $status,
        string $title,
        string $detail = '',
        string $type = 'about:blank',
        ?array $errors = null,
        ?string $instance = null,
    ) {
        $body = [
            'type' => $type,
            'title' => $title,
            'status' => $status,
        ];

        if ($detail !== '') {
            $body['detail'] = $detail;
        }
        if ($instance !== null) {
            $body['instance'] = $instance;
        }
        if ($errors !== null) {
            $body['errors'] = $errors;
        }

        parent::__construct(
            data: $body,
            status: $status,
            headers: ['Content-Type' => 'application/problem+json'],
        );
    }

    public static function for(\Throwable $e, ?int $statusOverride = null): self
    {
        $status = $statusOverride ?? self::statusFor($e);
        $title = self::titleFor($status);

        return new self(
            status: $status,
            title: $title,
            detail: $e->getMessage(),
        );
    }

    public static function statusFor(\Throwable $e): int
    {
        if ($e instanceof AuthenticationException) {
            return Response::HTTP_UNAUTHORIZED;
        }
        if ($e instanceof AuthorizationException) {
            return Response::HTTP_FORBIDDEN;
        }
        if ($e instanceof ModelNotFoundException) {
            return Response::HTTP_NOT_FOUND;
        }
        if ($e instanceof NotFoundHttpException) {
            return Response::HTTP_NOT_FOUND;
        }
        if ($e instanceof MethodNotAllowedHttpException) {
            return Response::HTTP_METHOD_NOT_ALLOWED;
        }
        if ($e instanceof ThrottleRequestsException) {
            return Response::HTTP_TOO_MANY_REQUESTS;
        }
        if ($e instanceof ValidationException) {
            return Response::HTTP_BAD_REQUEST;
        }
        // abort(403) / abort(404) and friends throw Symfony HttpException with
        // the status code on the exception itself. Without this branch every
        // abort(403) falls through to 500 and the cross-tenant authz tests
        // (which assert 403) see 500.
        if ($e instanceof HttpException) {
            return $e->getStatusCode();
        }

        return Response::HTTP_INTERNAL_SERVER_ERROR;
    }

    private static function titleFor(int $status): string
    {
        return match ($status) {
            400 => 'Invalid request',
            401 => 'Authentication required',
            403 => 'Forbidden',
            404 => 'Not found',
            405 => 'Method not allowed',
            409 => 'Conflict',
            422 => 'Unprocessable entity',
            429 => 'Too many requests',
            500 => 'Internal server error',
            default => 'Error',
        };
    }
}
