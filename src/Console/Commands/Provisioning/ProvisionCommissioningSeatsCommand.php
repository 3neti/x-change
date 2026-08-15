<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Console\Commands\Provisioning;

use Illuminate\Console\Command;
use LBHurtado\XProvisioning\Actions\ProvisionCommissioningSeats;

final class ProvisionCommissioningSeatsCommand extends Command
{
    protected $signature = 'x-change:provisioning:commission {--json : Output JSON}';

    protected $description = 'Idempotently create the configured vacant commissioning authority seats.';

    public function handle(ProvisionCommissioningSeats $provisioning): int
    {
        $configured = array_values((array) config('x-change.provisioning.commissioning_seats', []));
        $seats = $provisioning->handle($configured);
        $payload = [
            'schema' => 'x-change.commissioning-provisioning-seats.v1',
            'seat_count' => count($seats),
            'required_count' => collect($seats)->where('required', true)->count(),
            'vacant_count' => collect($seats)->where('status.value', 'vacant')->count(),
            'identities_required_now' => false,
            'authority_activated' => false,
        ];

        if ((bool) $this->option('json')) {
            $this->line(json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
        } else {
            $this->info('Commissioning authority seats are ready.');
            $this->line('Vacant seats may be offered after the human identities are known.');
            $this->line('No authority was activated and no funds moved.');
        }

        return self::SUCCESS;
    }
}
