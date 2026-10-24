<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Data\Configuration;

use InvalidArgumentException;

final readonly class ApplicationProfileData
{
    public const SCHEMA = 'x-change.app-profile.v1';

    /**
     * @param  list<string>  $navigation
     * @param  list<ApplicationJourneyData>  $journeys
     */
    public function __construct(
        public string $schema,
        public string $key,
        public string $label,
        public string $home,
        public array $navigation,
        public array $journeys,
    ) {
        if ($schema !== self::SCHEMA || preg_match('/^[a-z][a-z0-9-]*$/D', $key) !== 1
            || trim($label) === '' || $journeys === []) {
            throw new InvalidArgumentException('Application profile identity or schema is invalid.');
        }

        $keys = [];
        foreach ($journeys as $journey) {
            if (! $journey instanceof ApplicationJourneyData || isset($keys[$journey->key])) {
                throw new InvalidArgumentException('Application profile journeys must be typed and unique.');
            }
            $keys[$journey->key] = true;
        }

        if (! isset($keys[$home]) || $navigation === []) {
            throw new InvalidArgumentException('Application profile home or navigation is invalid.');
        }

        $navigationKeys = [];
        foreach ($navigation as $journeyKey) {
            if (! is_string($journeyKey) || ! isset($keys[$journeyKey]) || isset($navigationKeys[$journeyKey])) {
                throw new InvalidArgumentException('Application profile navigation references an unknown journey.');
            }
            $navigationKeys[$journeyKey] = true;
        }
    }

    /** @param array<string, mixed> $attributes */
    public static function fromArray(array $attributes): self
    {
        if (array_diff(array_keys($attributes), ['schema', 'key', 'label', 'home', 'navigation', 'journeys']) !== []
            || ! is_string($attributes['schema'] ?? null)
            || ! is_string($attributes['key'] ?? null)
            || ! is_string($attributes['label'] ?? null)
            || ! is_string($attributes['home'] ?? null)
            || ! is_array($attributes['navigation'] ?? null)
            || ! array_is_list($attributes['navigation'])
            || ! is_array($attributes['journeys'] ?? null)
            || ! array_is_list($attributes['journeys'])) {
            throw new InvalidArgumentException('Application profile fields are invalid.');
        }

        $journeys = array_map(static function (mixed $journey): ApplicationJourneyData {
            if (! is_array($journey) || array_is_list($journey)) {
                throw new InvalidArgumentException('Application journey must be a mapping.');
            }

            return ApplicationJourneyData::fromArray($journey);
        }, $attributes['journeys']);

        return new self(
            schema: $attributes['schema'],
            key: $attributes['key'],
            label: $attributes['label'],
            home: $attributes['home'],
            navigation: $attributes['navigation'],
            journeys: $journeys,
        );
    }
}
