<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use LBHurtado\XChange\Contracts\IssuerOnboardingContract;
use RuntimeException;

class DefaultIssuerOnboardingService implements IssuerOnboardingContract
{
    public function onboard(array $input): mixed
    {
        $modelClass = $this->issuerModelClass();

        /** @var Model $issuer */
        $issuer = new $modelClass;

        if (! $issuer instanceof Model) {
            throw new RuntimeException('Configured issuer model must be an Eloquent model.');
        }

        $attributes = [
            'name' => data_get($input, 'name'),
            'email' => data_get($input, 'email'),
            'mobile' => data_get($input, 'mobile'),
            'country' => data_get($input, 'country'),
        ];

        if ($this->hasFillableAttribute($issuer, 'password')) {
            $attributes['password'] = bcrypt((string) Str::uuid());
        }

        $issuer->fill(array_filter(
            $attributes,
            static fn (mixed $value): bool => $value !== null
        ));

        if ($this->hasAttribute($issuer, 'meta') && array_key_exists('metadata', $input)) {
            $issuer->setAttribute('meta', data_get($input, 'metadata'));
        }

        if ($this->hasAttribute($issuer, 'identity') && array_key_exists('identity', $input)) {
            $issuer->setAttribute('identity', data_get($input, 'identity'));
        }

        $issuer->save();

        return $issuer->fresh() ?? $issuer;
    }

    protected function issuerModelClass(): string
    {
        $modelClass = config('x-change.onboarding.issuer_model');

        if (! is_string($modelClass) || $modelClass === '') {
            throw new RuntimeException('No issuer model configured for onboarding.');
        }

        if (! class_exists($modelClass)) {
            throw new RuntimeException(sprintf('Configured issuer model [%s] does not exist.', $modelClass));
        }

        return $modelClass;
    }

    protected function hasFillableAttribute(Model $model, string $attribute): bool
    {
        $fillable = $model->getFillable();

        return $fillable === [] || in_array($attribute, $fillable, true);
    }

    protected function hasAttribute(Model $model, string $attribute): bool
    {
        return array_key_exists($attribute, $model->getAttributes())
            || method_exists($model, $attribute)
            || in_array($attribute, $model->getFillable(), true);
    }
}
