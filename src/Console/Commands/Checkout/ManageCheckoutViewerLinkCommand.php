<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Console\Commands\Checkout;

use Illuminate\Console\Command;
use Illuminate\Support\Str;
use LBHurtado\XChange\Models\CheckoutViewerLink;
use LBHurtado\XChange\Services\Commercial\ConfiguredCommercialPrincipalResolver;

final class ManageCheckoutViewerLinkCommand extends Command
{
    protected $signature = 'x-change:checkout:viewer-link
        {label? : Stakeholder label for a new read-only link}
        {--days=7 : Link lifetime, maximum 30 days}
        {--revoke= : Revoke an existing link by numeric ID}
        {--list : List active link IDs and labels without tokens}';

    protected $description = 'Create, list, or revoke read-only Checkout console viewer links.';

    public function handle(ConfiguredCommercialPrincipalResolver $resolver): int
    {
        $principal = $resolver->resolve();
        $links = CheckoutViewerLink::query()
            ->where('owner_type', $principal::class)
            ->where('owner_id', (string) $principal->getKey());

        if ($this->option('list')) {
            $this->table(['ID', 'Label', 'Expires', 'Revoked'], $links->get()->map(static fn (CheckoutViewerLink $link): array => [
                $link->getKey(), $link->label, $link->expires_at?->toIso8601String(), $link->revoked_at?->toIso8601String(),
            ])->all());

            return self::SUCCESS;
        }

        if ($this->option('revoke') !== null) {
            $link = (clone $links)->find((int) $this->option('revoke'));

            if ($link === null) {
                $this->error('Viewer link not found for the commissioned owner.');

                return self::FAILURE;
            }

            $link->forceFill(['revoked_at' => now()])->save();
            $this->info('Viewer link revoked.');

            return self::SUCCESS;
        }

        $label = trim((string) $this->argument('label'));
        $days = (int) $this->option('days');

        if ($label === '' || mb_strlen($label) > 191 || $days < 1 || $days > 30) {
            $this->error('Provide a label and a lifetime from 1 to 30 days.');

            return self::FAILURE;
        }

        $token = Str::random(64);
        $link = $links->create([
            'token_hash' => hash('sha256', $token),
            'owner_type' => $principal::class,
            'owner_id' => (string) $principal->getKey(),
            'label' => $label,
            'expires_at' => now()->addDays($days),
        ]);
        $this->info('Viewer link ID: '.$link->getKey());
        $this->line(route('x-change.checkout.viewer', ['token' => $token]));

        return self::SUCCESS;
    }
}
