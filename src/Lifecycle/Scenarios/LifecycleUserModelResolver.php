<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Lifecycle\Scenarios;

use Illuminate\Database\Eloquent\Model;
use RuntimeException;

final class LifecycleUserModelResolver
{
    /**
     * @return class-string<Model>
     */
    public function resolve(): string
    {
        $override = trim((string) config('x-change.lifecycle.defaults.user_model'));

        if ($override !== '') {
            return $this->validate($override);
        }

        $guard = (string) config('auth.defaults.guard', 'web');
        $provider = trim((string) config("auth.guards.{$guard}.provider"));
        $model = $provider === ''
            ? ''
            : trim((string) config("auth.providers.{$provider}.model"));

        if ($model === '') {
            throw new RuntimeException(sprintf(
                'The lifecycle user model could not be resolved from auth guard [%s].',
                $guard,
            ));
        }

        return $this->validate($model);
    }

    /**
     * @return class-string<Model>
     */
    private function validate(string $model): string
    {
        if (! class_exists($model) || ! is_subclass_of($model, Model::class)) {
            throw new RuntimeException(sprintf(
                'Configured lifecycle user model [%s] must extend [%s].',
                $model,
                Model::class,
            ));
        }

        /** @var class-string<Model> $model */
        return $model;
    }
}
