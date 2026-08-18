<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Keepsake;

use Illuminate\Database\Eloquent\Model;
use RuntimeException;

final class KeepsakeUserModelResolver
{
    /** @return class-string<Model> */
    public function resolve(): string
    {
        $guard = (string) config('auth.defaults.guard', 'web');
        $provider = trim((string) config("auth.guards.{$guard}.provider"));
        $model = $provider === ''
            ? ''
            : trim((string) config("auth.providers.{$provider}.model"));

        if ($model === '' || ! class_exists($model) || ! is_subclass_of($model, Model::class)) {
            throw new RuntimeException('The keepsake user model could not be resolved from the default authentication guard.');
        }

        /** @var class-string<Model> $model */
        return $model;
    }
}
