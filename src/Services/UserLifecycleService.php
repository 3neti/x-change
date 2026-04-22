<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Hash;
use LBHurtado\ModelChannel\Contracts\HasMobileChannel;
use LBHurtado\XChange\Contracts\HasLifecycleMetadata;
use LBHurtado\XChange\Contracts\UserLifecycleServiceContract;
use RuntimeException;

class UserLifecycleService implements UserLifecycleServiceContract
{
    public function create(array $payload): mixed
    {
        $user = $this->newUserModel();

        $user->fill(array_filter([
            'name' => $payload['name'] ?? null,
            'email' => $payload['email'] ?? null,
            'country' => $payload['country'] ?? null,
            'password' => Hash::make((string) ($payload['password'] ?? 'lifecycle-placeholder-password')),
        ], fn ($value) => $value !== null));

        $user->save();

        if (isset($payload['mobile']) && is_string($payload['mobile']) && $payload['mobile'] !== '') {
            $user->setMobileChannel($payload['mobile']);
        }

        if (array_key_exists('metadata', $payload)) {
            $user->putLifecycleMetadata('profile', (array) $payload['metadata']);
        }

        $user->refresh();

        return [
            'id' => (string) $user->getKey(),
            'name' => $user->getAttribute('name'),
            'email' => $user->getAttribute('email'),
            'mobile' => $user->getMobileChannel(),
            'country' => $user->getAttribute('country'),
            'status' => 'created',
        ];
    }

    public function show(string $user): mixed
    {
        $model = $this->findUserOrFail($user);

        return [
            'id' => (string) $model->getKey(),
            'name' => $model->getAttribute('name'),
            'email' => $model->getAttribute('email'),
            'mobile' => $model->getMobileChannel(),
            'country' => $model->getAttribute('country'),
            'status' => 'active',
        ];
    }

    public function submitKyc(string $user, array $payload): mixed
    {
        $model = $this->findUserOrFail($user);

        $kyc = [
            'transaction_id' => (string) ($payload['transaction_id'] ?? ''),
            'provider' => (string) ($payload['provider'] ?? 'hyperverge'),
            'status' => (string) ($payload['status'] ?? 'submitted'),
            'metadata' => (array) ($payload['metadata'] ?? []),
        ];

        $model->putLifecycleMetadata('kyc', $kyc);

        return [
            'user_id' => (string) $model->getKey(),
            'status' => $kyc['status'],
            'transaction_id' => $kyc['transaction_id'],
            'provider' => $kyc['provider'],
            'messages' => ['KYC submitted successfully.'],
        ];
    }

    public function showKyc(string $user): mixed
    {
        $model = $this->findUserOrFail($user);

        $kyc = $model->getLifecycleMetadata('kyc');

        return [
            'user_id' => (string) $model->getKey(),
            'status' => Arr::get($kyc, 'status', 'unknown'),
            'transaction_id' => Arr::get($kyc, 'transaction_id'),
            'provider' => Arr::get($kyc, 'provider'),
            'messages' => [],
        ];
    }

    /**
     * @return Model&HasMobileChannel&HasLifecycleMetadata
     */
    protected function newUserModel(): Model
    {
        /** @var class-string<Model> $modelClass */
        $modelClass = (string) config('x-change.onboarding.issuer_model');

        $user = new $modelClass();

        if (! $user instanceof HasMobileChannel) {
            throw new RuntimeException(sprintf(
                'Configured user model [%s] must implement [%s].',
                $modelClass,
                HasMobileChannel::class,
            ));
        }

        if (! $user instanceof HasLifecycleMetadata) {
            throw new RuntimeException(sprintf(
                'Configured user model [%s] must implement [%s].',
                $modelClass,
                HasLifecycleMetadata::class,
            ));
        }

        return $user;
    }

    /**
     * @return Model&HasMobileChannel&HasLifecycleMetadata
     */
    protected function findUserOrFail(string $user): Model
    {
        /** @var class-string<Model> $modelClass */
        $modelClass = (string) config('x-change.onboarding.issuer_model');

        $model = $modelClass::query()->find($user);

        if (! $model instanceof Model) {
            throw (new ModelNotFoundException())->setModel($modelClass, [$user]);
        }

        if (! $model instanceof HasMobileChannel) {
            throw new RuntimeException(sprintf(
                'Configured user model [%s] must implement [%s].',
                $modelClass,
                HasMobileChannel::class,
            ));
        }

        if (! $model instanceof HasLifecycleMetadata) {
            throw new RuntimeException(sprintf(
                'Configured user model [%s] must implement [%s].',
                $modelClass,
                HasLifecycleMetadata::class,
            ));
        }

        return $model;
    }
}
