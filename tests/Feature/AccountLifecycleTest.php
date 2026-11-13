<?php

declare(strict_types=1);

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use LBHurtado\XChange\Services\Accounts\AccountLifecycle;

it('defaults existing accounts to active and records audited suspension and reactivation', function (): void {
    Gate::define('x-change.manage-account-lifecycle', fn () => true);
    $owner = actingAsTestUser();
    $actor = $owner;
    $lifecycle = app(AccountLifecycle::class);

    expect($lifecycle->isActive($owner))->toBeTrue();
    $lifecycle->transition($owner, $actor, 'suspended', 'Investigation');
    expect($lifecycle->isActive($owner))->toBeFalse();
    $lifecycle->transition($owner, $actor, 'suspended', 'Repeated request');
    expect(DB::table('x_change_account_lifecycle_events')->count())->toBe(1);
    $lifecycle->transition($owner, $actor, 'active', 'Resolved');
    expect($lifecycle->isActive($owner))->toBeTrue()
        ->and(DB::table('x_change_account_lifecycle_events')->count())->toBe(2);
    $this->assertDatabaseHas('x_change_account_lifecycle_events', [
        'previous_status' => 'active', 'new_status' => 'suspended',
        'actor_type' => $actor->getMorphClass(), 'actor_id' => (string) $actor->getKey(),
        'reason' => 'Investigation',
    ]);
});

it('requires an explicit reason and valid lifecycle state', function (): void {
    Gate::define('x-change.manage-account-lifecycle', fn () => true);
    $owner = actingAsTestUser();
    expect(fn () => app(AccountLifecycle::class)->transition($owner, $owner, 'suspended', ' '))
        ->toThrow(InvalidArgumentException::class);
    expect(fn () => app(AccountLifecycle::class)->transition($owner, $owner, 'closed', 'test'))
        ->toThrow(InvalidArgumentException::class);
});

it('denies lifecycle changes without operator authorization', function (): void {
    $owner = actingAsTestUser();
    expect(fn () => app(AccountLifecycle::class)->transition($owner, $owner, 'suspended', 'test'))
        ->toThrow(AuthorizationException::class);
    expect(app(AccountLifecycle::class)->isActive($owner))->toBeTrue();
});

it('protects the operator controls and records authorized changes', function (): void {
    $owner = actingAsTestUser();
    $url = route('x-change.cockpit.account-lifecycle.index');
    $this->get($url)->assertForbidden();
    $this->post(route('x-change.cockpit.account-lifecycle.update', $owner->getKey()), [
        'status' => 'suspended', 'reason' => 'review',
    ])->assertForbidden();
    Gate::define('x-change.view-account-lifecycle', fn () => true);
    Gate::define('x-change.manage-account-lifecycle', fn ($actor, $target) => $actor->is($target));
    $this->withHeader('X-Inertia', 'true')->get($url.'?account_id='.$owner->getKey())->assertOk();
    $this->post(route('x-change.cockpit.account-lifecycle.update', $owner->getKey()), [
        'status' => 'suspended', 'reason' => 'review',
    ])->assertRedirect();
    expect(app(AccountLifecycle::class)->isActive($owner))->toBeFalse();
    $this->assertDatabaseHas('x_change_account_lifecycle_events', ['reason' => 'review', 'new_status' => 'suspended']);
});

it('requires a transaction for the account lifecycle issuance lock', function (): void {
    $owner = actingAsTestUser();
    expect(fn () => app(AccountLifecycle::class)->lockAndIsActive($owner))->toThrow(LogicException::class);
});

it('serializes suspension with an issuance holding the account lifecycle lock', function (): void {
    if (! in_array(DB::connection()->getDriverName(), ['mysql', 'pgsql'], true) || ! function_exists('pcntl_fork')) {
        test()->markTestSkipped('Requires a dedicated MySQL or PostgreSQL database and pcntl.');
    }
    $owner = actingAsTestUser();
    $channels = [];
    $children = [];
    for ($i = 0; $i < 2; $i++) {
        $pair = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
        if ($pair === false || ($pid = pcntl_fork()) === -1) {
            throw new RuntimeException('Unable to start concurrency test.');
        }
        if ($pid === 0) {
            fclose($pair[0]);
            DB::purge();
            try {
                if ($i === 0) {
                    DB::transaction(function () use ($owner, $pair): void {
                        if (! app(AccountLifecycle::class)->lockAndIsActive($owner)) {
                            throw new RuntimeException('Account unexpectedly suspended.');
                        }
                        fwrite($pair[1], 'L');
                        fread($pair[1], 1);
                    });
                } else {
                    Gate::define('x-change.manage-account-lifecycle', fn () => true);
                    fwrite($pair[1], 'R');
                    app(AccountLifecycle::class)->transition($owner, $owner, 'suspended', 'Concurrency test');
                }
                fwrite($pair[1], 'D');
                fclose($pair[1]);
                exit(0);
            } catch (Throwable $exception) {
                fwrite($pair[1], 'E');
                fclose($pair[1]);
                exit(1);
            }
        }
        fclose($pair[1]);
        $channels[] = $pair[0];
        $children[] = $pid;
        if ($i === 0) {
            expect(fread($pair[0], 1))->toBe('L');
        }
    }
    expect(fread($channels[1], 1))->toBe('R');
    $read = [$channels[1]];
    $write = null;
    $except = null;
    expect(stream_select($read, $write, $except, 0, 200000))->toBe(0);
    fwrite($channels[0], 'C');
    expect(fread($channels[0], 1))->toBe('D');
    expect(fread($channels[1], 1))->toBe('D');
    foreach ($children as $pid) {
        pcntl_waitpid($pid, $status);
        expect(pcntl_wexitstatus($status))->toBe(0);
    }
    foreach ($channels as $channel) {
        fclose($channel);
    }
    DB::purge();
    expect(app(AccountLifecycle::class)->isActive($owner))->toBeFalse();
    expect(DB::transaction(fn () => app(AccountLifecycle::class)->lockAndIsActive($owner)))->toBeFalse();
});
