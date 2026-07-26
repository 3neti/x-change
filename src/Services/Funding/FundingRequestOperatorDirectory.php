<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Funding;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use LBHurtado\XChange\Models\FundingRequest;
use RuntimeException;

final readonly class FundingRequestOperatorDirectory
{
    public function __construct(
        private FundingRequestAccess $access,
    ) {}

    /**
     * @return list<Model&Authenticatable>
     */
    public function makersFor(FundingRequest $request): array
    {
        return $this->eligible(
            $this->access->makerIds(),
            [$request->requester_id],
        );
    }

    /**
     * @return list<Model&Authenticatable>
     */
    public function checkersFor(FundingRequest $request): array
    {
        return $this->eligible(
            $this->access->checkerIds(),
            array_filter([
                $request->requester_id,
                $request->reviewed_by_id,
            ]),
        );
    }

    /**
     * @param  list<Model&Authenticatable>  $operators
     * @return array<string, string>
     */
    public function options(array $operators): array
    {
        $options = [];

        foreach ($operators as $operator) {
            $id = (string) $operator->getAuthIdentifier();
            $name = trim((string) $operator->getAttribute('name'));
            $options[$id] = ($name !== '' ? $name : 'Operator').' · #'.$id;
        }

        return $options;
    }

    /**
     * @param  list<Model&Authenticatable>  $operators
     * @return Model&Authenticatable
     */
    public function resolve(array $operators, string $operatorId, string $role): Model
    {
        foreach ($operators as $operator) {
            if ((string) $operator->getAuthIdentifier() === trim($operatorId)) {
                return $operator;
            }
        }

        throw new RuntimeException(
            "Funding Request {$role} [{$operatorId}] is not eligible for this request.",
        );
    }

    /**
     * @param  list<string>  $ids
     * @param  array<int, mixed>  $excludedIds
     * @return list<Model&Authenticatable>
     */
    private function eligible(array $ids, array $excludedIds): array
    {
        $modelClass = config('x-change.onboarding.issuer_model')
            ?: config('auth.providers.users.model');

        if (
            ! is_string($modelClass)
            || ! is_subclass_of($modelClass, Model::class)
        ) {
            throw new RuntimeException(
                'The configured Funding Request operator model is invalid.',
            );
        }

        $excluded = array_map(
            static fn (mixed $id): string => trim((string) $id),
            $excludedIds,
        );
        $eligibleIds = array_values(array_filter(
            $ids,
            static fn (string $id): bool => ! in_array($id, $excluded, true),
        ));
        $models = $modelClass::query()
            ->whereKey($eligibleIds)
            ->get()
            ->keyBy(fn (Model $operator): string => (string) $operator->getKey());
        $operators = [];

        foreach ($eligibleIds as $id) {
            $operator = $models->get($id);

            if (
                $operator instanceof Model
                && $operator instanceof Authenticatable
            ) {
                $operators[] = $operator;
            }
        }

        return $operators;
    }
}
