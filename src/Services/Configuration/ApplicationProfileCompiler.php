<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Configuration;

use Illuminate\Support\Facades\Route;
use InvalidArgumentException;
use LBHurtado\XChange\Data\Configuration\ApplicationJourneyData;
use LBHurtado\XChange\Data\Configuration\ApplicationProfileData;
use LBHurtado\XChange\Data\Configuration\CompiledApplicationProfileData;
use Symfony\Component\Yaml\Yaml;

final readonly class ApplicationProfileCompiler
{
    public function __construct(private ApplicationJourneyRouteRegistry $routes) {}

    public function fromConfig(): CompiledApplicationProfileData
    {
        return $this->compile(
            (string) config('x-change.app_profile.primary', 'default'),
            (array) config('x-change.app_profile.addons', []),
        );
    }

    /** @param list<string> $addons */
    public function compile(string $primary, array $addons = []): CompiledApplicationProfileData
    {
        $keys = array_values(array_unique([$primary, ...$addons]));
        $profiles = [];
        $journeys = [];
        $navigation = [];

        foreach ($keys as $key) {
            if (! is_string($key) || preg_match('/^[a-z][a-z0-9-]*$/D', $key) !== 1) {
                throw new InvalidArgumentException('App profile key is invalid.');
            }
            $path = dirname(__DIR__, 3).'/config/app-profiles/'.$key.'.yaml';
            if (! is_file($path)) {
                throw new InvalidArgumentException("App profile [{$key}] is unavailable.");
            }
            $attributes = Yaml::parseFile($path);
            if (! is_array($attributes)) {
                throw new InvalidArgumentException("App profile [{$key}] must be a YAML mapping.");
            }
            $profile = ApplicationProfileData::fromArray($attributes);
            if ($profile->key !== $key) {
                throw new InvalidArgumentException("App profile [{$key}] has a mismatched key.");
            }
            $profiles[] = $profile;

            foreach ($profile->journeys as $journey) {
                if (isset($journeys[$journey->key]) && $journeys[$journey->key] != $journey) {
                    throw new InvalidArgumentException("Journey [{$journey->key}] conflicts across app profiles.");
                }
                $journeys[$journey->key] = $journey;
                $routeName = $this->routes->routeName($journey);
                if (! Route::has($routeName)) {
                    throw new InvalidArgumentException("Journey [{$journey->key}] route [{$routeName}] is unavailable.");
                }
            }

            foreach ($profile->navigation as $journeyKey) {
                if (isset($navigation[$journeyKey])) {
                    continue;
                }
                $journey = $journeys[$journeyKey];
                $routeName = $this->routes->routeName($journey);
                $navigation[$journeyKey] = $this->navigationItem($journey, $routeName);
            }
        }

        $home = $journeys[$profiles[0]->home];

        return new CompiledApplicationProfileData(
            primaryKey: $primary,
            profileKeys: $keys,
            homeJourneyKey: $home->key,
            homeRouteName: $this->routes->routeName($home),
            homeHref: route($this->routes->routeName($home), ['journey' => $home->key], false),
            navigation: array_values($navigation),
        );
    }

    /** @return array{key: string, label: string, kind: string, route_name: string, href: string} */
    private function navigationItem(ApplicationJourneyData $journey, string $routeName): array
    {
        return [
            'key' => $journey->key,
            'label' => $journey->label,
            'kind' => $journey->kind->value,
            'route_name' => $routeName,
            'href' => route($routeName, ['journey' => $journey->key], false),
        ];
    }
}
