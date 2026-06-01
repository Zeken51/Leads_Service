<?php

namespace App\Http\Responses;

use App\Http\Context\RequestContext;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class ApiResponse
{
    public static function success(mixed $data, int $status = Response::HTTP_OK): JsonResponse
    {
        return response()->json([
            'data'       => $data,
            'request_id' => self::requestId(),
        ], $status);
    }

    public static function created(mixed $data): JsonResponse
    {
        return self::success($data, Response::HTTP_CREATED);
    }

    public static function noContent(): \Illuminate\Http\Response
    {
        return response()->noContent()
            ->header('X-Request-ID', self::requestId());
    }

    public static function paginated(mixed $data, array $meta): JsonResponse
    {
        return response()->json([
            'data'       => $data,
            'meta'       => $meta,
            'request_id' => self::requestId(),
        ]);
    }

    public static function error(
        string $message,
        array $errors = [],
        int $status = Response::HTTP_BAD_REQUEST,
    ): JsonResponse {
        return response()->json([
            'message'    => $message,
            'errors'     => $errors,
            'request_id' => self::requestId(),
        ], $status);
    }

    public static function validationError(array $errors): JsonResponse
    {
        return self::error(
            message: 'Validation failed.',
            errors: $errors,
            status: Response::HTTP_UNPROCESSABLE_ENTITY,
        );
    }

    public static function unauthorized(string $message = 'Unauthenticated.'): JsonResponse
    {
        return self::error($message, [], Response::HTTP_UNAUTHORIZED);
    }

    public static function forbidden(string $message = 'Forbidden.'): JsonResponse
    {
        return self::error($message, [], Response::HTTP_FORBIDDEN);
    }

    public static function notFound(string $message = 'Resource not found.'): JsonResponse
    {
        return self::error($message, [], Response::HTTP_NOT_FOUND);
    }

    private static function requestId(): string
    {
        try {
            return app(RequestContext::class)->requestId;
        } catch (\Throwable) {
            return 'req_'.substr(bin2hex(random_bytes(4)), 0, 8);
        }
    }
}
