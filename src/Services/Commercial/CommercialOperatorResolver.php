<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Commercial;

use Illuminate\Database\Eloquent\Model;
use LBHurtado\XChange\Exceptions\CommercialSaleConflict;

final class CommercialOperatorResolver
{
    public function resolve(string $identity, string $column = 'mobile'): Model
    {
        $modelClass = (string) config('auth.providers.users.model');
        $identity = trim($identity);
        $column = trim($column);

        if (! is_subclass_of($modelClass, Model::class)
            || preg_match('/\A[A-Za-z_][A-Za-z0-9_]*\z/', $column) !== 1
            || $identity === '') {
            throw new CommercialSaleConflict('A valid Commercial operator identity is required.');
        }

        return $modelClass::query()->where($column, $identity)->sole();
    }
}
