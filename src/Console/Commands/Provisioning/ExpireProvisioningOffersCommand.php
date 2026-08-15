<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Console\Commands\Provisioning;

use Illuminate\Console\Command;
use LBHurtado\XProvisioning\Actions\ExpireProvisioningOffer;
use LBHurtado\XProvisioning\Enums\ProvisioningRequestStatus;
use LBHurtado\XProvisioning\Models\ProvisioningOffer;

final class ExpireProvisioningOffersCommand extends Command
{
    protected $signature = 'x-change:provisioning:expire-offers {--limit=100 : Maximum offers to expire}';

    protected $description = 'Idempotently expire elapsed provisioning invitations without provider or money movement.';

    public function handle(ExpireProvisioningOffer $expire): int
    {
        $limit = max(1, (int) $this->option('limit'));
        $offers = ProvisioningOffer::query()
            ->where('status', ProvisioningRequestStatus::Offered->value)
            ->where('expires_at', '<=', now())
            ->orderBy('id')
            ->limit($limit)
            ->get();

        $offers->each(fn (ProvisioningOffer $offer) => $expire->handle($offer));
        $this->components->info("Expired {$offers->count()} provisioning invitation(s).");

        return self::SUCCESS;
    }
}
