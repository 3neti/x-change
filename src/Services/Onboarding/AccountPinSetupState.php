<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Onboarding;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

final class AccountPinSetupState
{
    private const string Path = 'x_change.security.pin_setup';

    public function isRequired(Model $user): bool
    {
        return data_get($this->metadata($user), self::Path.'.status') === 'required';
    }

    public function markRequired(Model $user, bool $save = true): void
    {
        $metadata = $this->metadata($user);

        data_set($metadata, self::Path, [
            'status' => 'required',
            'required_at' => now()->toIso8601String(),
            'completed_at' => null,
        ]);

        $this->write($user, $metadata, $save);
    }

    public function markCompleted(Model $user, bool $save = true): void
    {
        $metadata = $this->metadata($user);

        data_set($metadata, self::Path, [
            'status' => 'completed',
            'required_at' => data_get(
                $metadata,
                self::Path.'.required_at',
            ),
            'completed_at' => now()->toIso8601String(),
        ]);

        $this->write($user, $metadata, $save);
    }

    /**
     * @return array<string, mixed>
     */
    public function metadata(Model $user): array
    {
        $column = $this->column($user);
        $value = $user->getAttribute($column);

        if (is_array($value)) {
            return $value;
        }

        if (! is_string($value) || trim($value) === '') {
            return [];
        }

        $decoded = json_decode($value, true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    private function write(Model $user, array $metadata, bool $save): void
    {
        $column = $this->column($user);
        $user->setAttribute(
            $column,
            $user->hasCast($column) ? $metadata : json_encode(
                $metadata,
                JSON_THROW_ON_ERROR,
            ),
        );

        if ($save) {
            $user->save();
        }
    }

    private function column(Model $user): string
    {
        $table = $user->getTable();

        foreach (['onboarding_meta', 'metadata'] as $column) {
            if (Schema::hasColumn($table, $column)) {
                return $column;
            }
        }

        throw new RuntimeException(
            'The configured onboarding Account model has no metadata column for PIN setup state.',
        );
    }
}
