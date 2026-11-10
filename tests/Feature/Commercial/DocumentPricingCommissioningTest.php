<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use LBHurtado\XChange\Contracts\CommercialComponentEconomicsResolverContract;
use LBHurtado\XChange\Contracts\CommercialOfferingResolverContract;
use LBHurtado\XChange\Enums\CommercialOperatorCapability;
use LBHurtado\XChange\Models\CommercialOfferingActivation;
use LBHurtado\XChange\Models\CommercialOperatorAuthorization;
use LBHurtado\XChange\Services\Commercial\CommissionDocumentPricing;
use LBHurtado\XChange\Services\Commercial\ProvisionCommercialBaselines;
use LBHurtado\XJournal\Models\ExecutionJournalEntry;

beforeEach(function (): void {
    config()->set('x-change.commercial.legal_trace.legal_entity_reference', 'legal-entity:x-change:test');
    config()->set('x-change.commercial.legal_trace.profile_version', 'test-v1');
    $catalog = config('x-commerce.catalogs.pay_code');
    $documentItem = $catalog['items']['inputs.fields.documents'];
    unset($catalog['items']['inputs.fields.documents']);
    $catalog['version'] = 4;
    config()->set('x-commerce.catalogs.pay_code', $catalog);
    app(ProvisionCommercialBaselines::class)->provision('commissioning-manifest:document-test');
    $catalog['items']['inputs.fields.documents'] = $documentItem;
    $catalog['version'] = 5;
    config()->set('x-commerce.catalogs.pay_code', $catalog);
});

it('requires a designated operator to commission the document fee', function (): void {
    $operator = actingAsTestUser();
    expect(fn () => app(CommissionDocumentPricing::class)->execute($operator, 'owner-request:test'))
        ->toThrow(DomainException::class);
});

it('rejects an unauthorized operator through the commissioning command', function (): void {
    $operator = actingAsTestUser();
    $this->artisan('x-change:commercial:commission-document-pricing', [
        'operator' => $operator->getKey(), '--authorization-reference' => 'owner-request:test',
    ])->assertFailed();
});

it('rejects an authorization reference that does not belong to the operator', function (): void {
    $operator = actingAsTestUser();
    CommercialOperatorAuthorization::query()->create([
        'operator_type' => $operator->getMorphClass(), 'operator_id' => $operator->getKey(),
        'capability' => CommercialOperatorCapability::CommissionDocumentPricing->value,
        'authorization_reference' => 'owner-request:real', 'valid_from' => now()->subMinute(),
    ]);
    expect(fn () => app(CommissionDocumentPricing::class)->execute($operator, 'owner-request:other'))
        ->toThrow(DomainException::class);
});

it('does not change the active catalog when the commissioned allocation policy differs', function (): void {
    $operator = actingAsTestUser();
    CommercialOperatorAuthorization::query()->create([
        'operator_type' => $operator->getMorphClass(), 'operator_id' => $operator->getKey(),
        'capability' => CommercialOperatorCapability::CommissionDocumentPricing->value,
        'authorization_reference' => 'owner-request:test', 'valid_from' => now()->subMinute(),
    ]);
    $before = CommercialOfferingActivation::query()->where('profile', 'pay_code')->whereNull('deactivated_at')->sole();
    config()->set('x-change.commercial.component_economics.bootstrap.recipient_reference', 'counterparty:unexpected');

    expect(fn () => app(CommissionDocumentPricing::class)->execute($operator, 'owner-request:test'))
        ->toThrow(DomainException::class, 'Existing component economics differ');
    expect(CommercialOfferingActivation::query()->where('profile', 'pay_code')->whereNull('deactivated_at')->sole()->id)
        ->toBe($before->id);
});

it('commissions the fee once while preserving existing prices and economics', function (): void {
    $operator = actingAsTestUser();
    CommercialOperatorAuthorization::query()->create([
        'operator_type' => $operator->getMorphClass(), 'operator_id' => $operator->getKey(),
        'capability' => CommercialOperatorCapability::CommissionDocumentPricing->value,
        'authorization_reference' => 'owner-request:test', 'valid_from' => now(),
    ]);
    $before = app(CommercialOfferingResolverContract::class)->resolve('pay_code');
    $oldEconomics = app(CommercialComponentEconomicsResolverContract::class)->resolve('pay_code');
    $activation = app(CommissionDocumentPricing::class)->execute($operator, 'owner-request:test');
    $after = app(CommercialOfferingResolverContract::class)->resolve('pay_code');
    $economics = app(CommercialComponentEconomicsResolverContract::class)->resolve('pay_code');

    expect(collect($after->catalog->items)->firstWhere('reference', 'inputs.fields.documents')->unitPriceMinor)->toBe(200)
        ->and(count($after->catalog->items))->toBe(count($before->catalog->items) + 1)
        ->and(collect($economics->components)->firstWhere('componentReference', 'cash.amount')->toArray())
        ->toBe(collect($oldEconomics->components)->firstWhere('componentReference', 'cash.amount')->toArray())
        ->and(app(CommissionDocumentPricing::class)->execute($operator, 'owner-request:test')->id)->toBe($activation->id)
        ->and($activation->offering->authorization_reference)->toBe('owner-request:test');

    $event = ExecutionJournalEntry::query()->where('event_type', 'commercial.offering.single_operator_authorized')->sole();
    expect(data_get($event->actor, 'id'))->toBe((string) $operator->getKey())
        ->and(data_get($event->references, 'external_reference'))->toBe('owner-request:test');
});

it('serializes concurrent commissioning requests on a row-locking database', function (): void {
    if (! in_array(DB::connection()->getDriverName(), ['mysql', 'pgsql'], true) || ! function_exists('pcntl_fork')) {
        test()->markTestSkipped('Requires a dedicated MySQL or PostgreSQL database and pcntl.');
    }
    expect(DB::connection()->transactionLevel())->toBe(0);
    $operator = actingAsTestUser();
    CommercialOperatorAuthorization::query()->create([
        'operator_type' => $operator->getMorphClass(), 'operator_id' => $operator->getKey(),
        'capability' => CommercialOperatorCapability::CommissionDocumentPricing->value,
        'authorization_reference' => 'owner-request:race', 'valid_from' => now()->subMinute(),
    ]);
    $children = $channels = $paths = [];
    for ($i = 0; $i < 2; $i++) {
        $channel = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
        $path = tempnam(sys_get_temp_dir(), 'document-price-race-');
        if ($channel === false || $path === false || ($pid = pcntl_fork()) === -1) {
            throw new RuntimeException('Unable to prepare the commissioning race.');
        }
        if ($pid === 0) {
            fclose($channel[0]);
            DB::purge();
            fread($channel[1], 1);
            try {
                file_put_contents($path, (string) app(CommissionDocumentPricing::class)->execute($operator, 'owner-request:race')->id);
                exit(0);
            } catch (Throwable $exception) {
                file_put_contents($path, get_class($exception).': '.$exception->getMessage());
                exit(1);
            }
        }
        fclose($channel[1]);
        $children[] = $pid;
        $channels[] = $channel[0];
        $paths[] = $path;
    }
    foreach ($channels as $channel) {
        fwrite($channel, '1');
        fclose($channel);
    }
    $exitCodes = [];
    foreach ($children as $pid) {
        pcntl_waitpid($pid, $status);
        $exitCodes[] = pcntl_wexitstatus($status);
    }
    $results = array_map(static fn (string $path): string => (string) file_get_contents($path), $paths);
    foreach ($paths as $path) {
        unlink($path);
    }
    DB::purge();
    expect($exitCodes)->toBe([0, 0], implode('; ', $results))
        ->and($results[0])->toBe($results[1])
        ->and(CommercialOfferingActivation::query()->where('profile', 'pay_code')->count())->toBe(2);
});
