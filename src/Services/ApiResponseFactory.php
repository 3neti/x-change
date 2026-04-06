<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services;

use Illuminate\Http\JsonResponse;
use Throwable;

class ApiResponseFactory
{
    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $meta
     */
    public function success(array $data = [], array $meta = [], int $status = 200): JsonResponse
    {
        return response()->json([
            $this->successKey() => true,
            $this->dataKey() => $data,
            $this->metaKey() => $meta,
        ], $status);
    }

    /**
     * @param  array<int|string, mixed>  $errors
     */
    public function error(
        string $message,
        string $code,
        array $errors = [],
        int $status = 422,
    ): JsonResponse {
        return response()->json([
            $this->successKey() => false,
            $this->messageKey() => $message,
            $this->codeKey() => $code,
            $this->errorsKey() => $errors,
        ], $status);
    }

    /**
     * @param  array<int|string, mixed>  $errors
     */
    public function errorFromThrowable(
        Throwable $throwable,
        string $code,
        array $errors = [],
        int $status = 500,
    ): JsonResponse {
        return $this->error(
            $throwable->getMessage(),
            $code,
            $errors,
            $status,
        );
    }

    protected function successKey(): string
    {
        return (string) config('x-change.api.response.success_key', 'success');
    }

    protected function dataKey(): string
    {
        return (string) config('x-change.api.response.data_key', 'data');
    }

    protected function metaKey(): string
    {
        return (string) config('x-change.api.response.meta_key', 'meta');
    }

    protected function messageKey(): string
    {
        return (string) config('x-change.api.response.message_key', 'message');
    }

    protected function codeKey(): string
    {
        return (string) config('x-change.api.response.code_key', 'code');
    }

    protected function errorsKey(): string
    {
        return (string) config('x-change.api.response.errors_key', 'errors');
    }
}
