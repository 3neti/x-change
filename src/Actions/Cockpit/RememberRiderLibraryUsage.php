<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Actions\Cockpit;

use Illuminate\Database\Eloquent\Model;
use LBHurtado\XChange\Enums\RiderLibraryEntryKind;
use LBHurtado\XChange\Services\Cockpit\RiderLibraryEntryStore;

final readonly class RememberRiderLibraryUsage
{
    public function __construct(
        private RiderLibraryEntryStore $entries,
    ) {}

    /**
     * @param  array<string, mixed>  $instructions
     */
    public function handle(Model $owner, array $instructions): void
    {
        $url = trim((string) data_get($instructions, 'rider.url', ''));

        if ($url !== '') {
            $this->entries->persist(
                owner: $owner,
                kind: RiderLibraryEntryKind::Url,
                payload: ['url' => $url],
                label: null,
                saved: false,
                used: true,
            );
        }

        $splash = trim((string) data_get($instructions, 'rider.splash', ''));

        if ($splash !== '') {
            $this->entries->persist(
                owner: $owner,
                kind: RiderLibraryEntryKind::Splash,
                payload: [
                    'splash' => $splash,
                    'format' => (string) data_get(
                        $instructions,
                        'rider.splash_format',
                        'plain',
                    ),
                ],
                label: null,
                saved: false,
                used: true,
            );
        }
    }
}
