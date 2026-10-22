<?php

namespace App\Http;

use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

/**
 * The single error format of the merchant API:
 * {"error": {"type": "...", "message": "...", "fields": {...}}}
 */
final class ApiError
{
    public static function json(int $status, string $type, string $message, array $fields = []): JsonResponse
    {
        $error = ['type' => $type, 'message' => $message];

        if ($fields) {
            $error['fields'] = $fields;
        }

        return response()->json(['error' => $error], $status);
    }

    public static function validation(ValidationException $e): JsonResponse
    {
        return self::json(422, 'invalid_request', $e->getMessage(), $e->errors());
    }
}
