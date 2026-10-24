<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Data\Configuration;

final readonly class CompiledApplicationProfileData
{
    /**
     * @param  list<string>  $profileKeys
     * @param  list<array{key: string, label: string, kind: string, route_name: string, href: string}>  $navigation
     */
    public function __construct(
        public string $primaryKey,
        public array $profileKeys,
        public string $homeJourneyKey,
        public string $homeRouteName,
        public string $homeHref,
        public array $navigation,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'schema' => 'x-change.compiled-app-profile.v1',
            'primary' => $this->primaryKey,
            'profiles' => $this->profileKeys,
            'home_journey' => $this->homeJourneyKey,
            'home_route' => $this->homeRouteName,
            'home_href' => $this->homeHref,
            'navigation' => $this->navigation,
        ];
    }
}
