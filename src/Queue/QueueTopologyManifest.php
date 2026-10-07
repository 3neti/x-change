<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Queue;

use Illuminate\Contracts\Queue\ShouldQueue;
use InvalidArgumentException;
use ReflectionClass;

final readonly class QueueTopologyManifest
{
    public const string Schema = 'settlement-os.queue-topology.v1';

    public const string Package = '3neti/x-change';

    public function path(): string
    {
        return dirname(__DIR__, 2).'/resources/settlement-os/queues.php';
    }

    /**
     * @return array{
     *     schema: string,
     *     package: string,
     *     lanes: array<string, array<string, mixed>>
     * }
     */
    public function load(): array
    {
        $manifest = require $this->path();

        if (! is_array($manifest)) {
            throw new InvalidArgumentException('The x-change queue topology manifest must return an array.');
        }

        $this->validate($manifest);

        /** @var array{schema: string, package: string, lanes: array<string, array<string, mixed>>} $manifest */
        return $manifest;
    }

    /**
     * @return list<array{
     *     lane: string,
     *     declared_queue: string,
     *     effective_queues: list<string>,
     *     jobs: list<array{class: class-string, queue: string, configuration_key: string|null}>,
     *     criticality: string,
     *     explicit_commissioning: bool,
     *     status: string
     * }>
     */
    public function resolved(): array
    {
        $resolved = [];

        foreach ($this->load()['lanes'] as $lane => $definition) {
            $declaredQueue = (string) $definition['queue'];
            $jobs = [];

            foreach ($definition['jobs'] as $jobClass => $jobDefinition) {
                $configurationKey = is_array($jobDefinition)
                    ? ($jobDefinition['configuration_key'] ?? null)
                    : null;
                $effectiveQueue = is_string($configurationKey)
                    ? (string) config($configurationKey, $declaredQueue)
                    : $declaredQueue;

                $jobs[] = [
                    'class' => $jobClass,
                    'queue' => $effectiveQueue,
                    'configuration_key' => is_string($configurationKey) ? $configurationKey : null,
                ];
            }

            $resolved[] = [
                'lane' => $lane,
                'declared_queue' => $declaredQueue,
                'effective_queues' => array_values(array_unique(array_column($jobs, 'queue'))),
                'jobs' => $jobs,
                'criticality' => (string) $definition['criticality'],
                'explicit_commissioning' => (bool) $definition['explicit_commissioning'],
                'status' => (string) ($definition['status'] ?? 'active'),
            ];
        }

        return $resolved;
    }

    /**
     * @return list<class-string>
     */
    public function jobClasses(): array
    {
        $jobClasses = [];

        foreach ($this->load()['lanes'] as $lane) {
            array_push($jobClasses, ...array_keys($lane['jobs']));
        }

        return $jobClasses;
    }

    /** @param array<string, mixed> $manifest */
    private function validate(array $manifest): void
    {
        if (($manifest['schema'] ?? null) !== self::Schema) {
            throw new InvalidArgumentException('The x-change queue topology schema is unsupported.');
        }

        if (($manifest['package'] ?? null) !== self::Package) {
            throw new InvalidArgumentException('The x-change queue topology package is invalid.');
        }

        $lanes = $manifest['lanes'] ?? null;

        if (! is_array($lanes) || $lanes === []) {
            throw new InvalidArgumentException('The x-change queue topology must declare at least one lane.');
        }

        $jobClasses = [];

        foreach ($lanes as $lane => $definition) {
            if (! is_string($lane) || preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $lane) !== 1) {
                throw new InvalidArgumentException('Queue topology lane keys must use lowercase kebab case.');
            }

            if (! is_array($definition)) {
                throw new InvalidArgumentException("Queue topology lane [{$lane}] must be an array.");
            }

            $queue = $definition['queue'] ?? null;

            if (! is_string($queue) || preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $queue) !== 1) {
                throw new InvalidArgumentException("Queue topology lane [{$lane}] has an invalid queue name.");
            }

            $jobs = $definition['jobs'] ?? null;

            if (! is_array($jobs) || $jobs === []) {
                throw new InvalidArgumentException("Queue topology lane [{$lane}] must declare jobs.");
            }

            foreach ($jobs as $jobClass => $jobDefinition) {
                if (! is_string($jobClass) || ! class_exists($jobClass)) {
                    throw new InvalidArgumentException("Queue topology lane [{$lane}] contains an unknown job.");
                }

                if (! (new ReflectionClass($jobClass))->implementsInterface(ShouldQueue::class)) {
                    throw new InvalidArgumentException("Queue topology job [{$jobClass}] must implement ShouldQueue.");
                }

                if (isset($jobClasses[$jobClass])) {
                    throw new InvalidArgumentException("Queue topology job [{$jobClass}] is declared more than once.");
                }

                if (! is_array($jobDefinition)) {
                    throw new InvalidArgumentException("Queue topology job [{$jobClass}] must use an array definition.");
                }

                $jobClasses[$jobClass] = true;
            }
        }
    }
}
