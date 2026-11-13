<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Accounts;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;

final class AccountLifecycle
{
    public function lockAndIsActive(Model $owner): bool
    {
        if (DB::transactionLevel() < 1) {
            throw new \LogicException('Account lifecycle locks require a database transaction.');
        }

        $key = ['owner_type' => $owner->getMorphClass(), 'owner_id' => (string) $owner->getKey()];
        DB::table('x_change_account_lifecycles')->insertOrIgnore($key + [
            'status' => 'active', 'created_at' => now(), 'updated_at' => now(),
        ]);

        return DB::table('x_change_account_lifecycles')->where($key)->lockForUpdate()->value('status') === 'active';
    }

    public function isActive(Model $owner): bool
    {
        $status = DB::table('x_change_account_lifecycles')
            ->where('owner_type', $owner->getMorphClass())
            ->where('owner_id', (string) $owner->getKey())
            ->value('status');

        return $status === null || $status === 'active';
    }

    public function transition(Model $owner, Model $actor, string $status, string $reason): void
    {
        Gate::forUser($actor)->authorize('x-change.manage-account-lifecycle', $owner);
        if (! in_array($status, ['active', 'suspended'], true) || trim($reason) === '') {
            throw new InvalidArgumentException('A valid status and reason are required.');
        }

        DB::transaction(function () use ($owner, $actor, $status, $reason): void {
            $key = ['owner_type' => $owner->getMorphClass(), 'owner_id' => (string) $owner->getKey()];
            DB::table('x_change_account_lifecycles')->insertOrIgnore($key + [
                'status' => 'active', 'created_at' => now(), 'updated_at' => now(),
            ]);
            $record = DB::table('x_change_account_lifecycles')->where($key)->lockForUpdate()->first();
            if ($record->status === $status) {
                return;
            }
            DB::table('x_change_account_lifecycles')->where('id', $record->id)->update([
                'status' => $status, 'updated_at' => now(),
            ]);
            DB::table('x_change_account_lifecycle_events')->insert([
                'lifecycle_id' => $record->id,
                'previous_status' => $record->status,
                'new_status' => $status,
                'actor_type' => $actor->getMorphClass(),
                'actor_id' => (string) $actor->getKey(),
                'reason' => trim($reason),
                'created_at' => now(),
            ]);
        });
    }
}
