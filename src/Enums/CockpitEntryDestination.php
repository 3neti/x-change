<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Enums;

enum CockpitEntryDestination: string
{
    case Funding = 'funding';
    case Issuance = 'issuance';

    public function routeName(): string
    {
        return match ($this) {
            self::Funding => 'x-change.cockpit.funding.index',
            self::Issuance => 'x-change.cockpit.quick-generate',
        };
    }

    /**
     * @return array{schema: string, destination: string, title: string, message: string, read_only: true}
     */
    public function notice(): array
    {
        return match ($this) {
            self::Funding => [
                'schema' => 'x-change.cockpit.entry-notice.v1',
                'destination' => $this->value,
                'title' => 'Start with Funding',
                'message' => 'Add funds to increase your Issuance Capacity before creating a Pay Code.',
                'read_only' => true,
            ],
            self::Issuance => [
                'schema' => 'x-change.cockpit.entry-notice.v1',
                'destination' => $this->value,
                'title' => 'You’re ready to issue',
                'message' => 'Your available funds and Issuance Capacity can cover a minimum Pay Code.',
                'read_only' => true,
            ],
        };
    }
}
