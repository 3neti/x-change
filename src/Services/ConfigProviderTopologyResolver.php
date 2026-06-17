<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services;

use Illuminate\Contracts\Container\Container;
use InvalidArgumentException;
use LBHurtado\XChange\Contracts\XChangeProviderTopologyContract;
use LBHurtado\XChange\Contracts\XChangeProviderTopologyResolverContract;

class ConfigProviderTopologyResolver implements XChangeProviderTopologyResolverContract
{
    public function __construct(
        protected Container $container,
    ) {}

    public function resolve(?string $key = null): XChangeProviderTopologyContract
    {
        $key = trim((string) ($key ?: config('x-change.provider_topologies.default', 'manual')));

        if ($key === '') {
            $key = 'manual';
        }

        $aliases = (array) config('x-change.provider_topologies.aliases', []);
        $key = is_string($aliases[$key] ?? null) ? $aliases[$key] : $key;

        $topologies = (array) config('x-change.provider_topologies.topologies', []);
        $class = $topologies[$key] ?? null;

        if (! is_string($class) || $class === '' || ! class_exists($class)) {
            throw new InvalidArgumentException("Unsupported x-change provider topology [{$key}].");
        }

        $topology = $this->container->make($class);

        if (! $topology instanceof XChangeProviderTopologyContract) {
            throw new InvalidArgumentException("Provider topology [{$key}] must implement XChangeProviderTopologyContract.");
        }

        return $topology;
    }
}
