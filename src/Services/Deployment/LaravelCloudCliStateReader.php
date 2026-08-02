<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Deployment;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Process;
use LBHurtado\XChange\Contracts\Deployment\CloudStateReaderContract;
use RuntimeException;

final readonly class LaravelCloudCliStateReader implements CloudStateReaderContract
{
    /** @return array<string, mixed> */
    public function read(string $application, string $environment): array
    {
        $applications = $this->runJson('application:list', [
            '--json', '--fields=id,name,slug', '-n',
        ]);
        $matchedApplication = collect($applications)->first(
            static fn (mixed $item): bool => is_array($item)
                && in_array($application, Arr::only($item, ['id', 'name', 'slug']), true),
        );

        if (! is_array($matchedApplication)) {
            return $this->missingState($application, $environment);
        }

        $applicationReference = (string) ($matchedApplication['id'] ?? $application);
        $environments = $this->runJson('environment:list', [
            $applicationReference,
            '--json',
            '--fields=id,name,slug,status,instances,databaseSchemaId,cacheId,websocketApplicationId,buildCommand,deployCommand',
            '-n',
        ]);
        $matchedEnvironment = collect($environments)->first(
            static fn (mixed $item): bool => is_array($item)
                && in_array($environment, Arr::only($item, ['id', 'name', 'slug']), true),
        );

        if (! is_array($matchedEnvironment)) {
            return [
                ...$this->missingState($application, $environment),
                'application' => ['exists' => true, ...Arr::only($matchedApplication, ['id', 'name', 'slug'])],
            ];
        }

        $instances = is_array($matchedEnvironment['instances'] ?? null)
            ? $matchedEnvironment['instances']
            : [];
        $instanceId = $this->firstInstanceId($instances);
        $schedulerEnabled = $this->firstInstanceUsesScheduler($instances);
        $processes = $instanceId === null
            ? []
            : $this->runJson('background-process:list', [
                $instanceId,
                '--json', '--fields=id,type,queue,connection,timeout', '-n',
            ]);

        return [
            'application' => ['exists' => true, ...Arr::only($matchedApplication, ['id', 'name', 'slug'])],
            'environment' => [
                'exists' => true,
                ...Arr::only($matchedEnvironment, ['id', 'name', 'slug', 'status', 'buildCommand', 'deployCommand']),
            ],
            'resources' => [
                'database' => ['attached' => filled($matchedEnvironment['databaseSchemaId'] ?? null)],
                'cache' => ['attached' => filled($matchedEnvironment['cacheId'] ?? null)],
                'compute' => ['attached' => $instances !== [], 'instance_id' => $instanceId],
                'websockets' => ['attached' => filled($matchedEnvironment['websocketApplicationId'] ?? null)],
            ],
            'runtime' => [
                'queues' => collect($processes)
                    ->filter(static fn (mixed $process): bool => is_array($process) && filled($process['queue'] ?? null))
                    ->pluck('queue')
                    ->flatMap(static fn (mixed $queues): array => array_map('trim', explode(',', (string) $queues)))
                    ->filter()
                    ->unique()
                    ->values()
                    ->all(),
                'scheduler' => $schedulerEnabled,
            ],
        ];
    }

    /**
     * @param  list<string>  $arguments
     * @return list<array<string, mixed>>
     */
    private function runJson(string $command, array $arguments): array
    {
        $help = Process::timeout(30)->run(['cloud', $command, '-h', '-n']);

        if (! $help->successful()) {
            throw new RuntimeException("Laravel Cloud CLI command [{$command}] is unavailable.");
        }

        $result = Process::timeout(60)->run(['cloud', $command, ...$arguments]);

        if (! $result->successful()) {
            throw new RuntimeException("Laravel Cloud state read [{$command}] failed.");
        }

        $payload = json_decode($result->output(), true, flags: JSON_THROW_ON_ERROR);

        return is_array($payload) && array_is_list($payload) ? $payload : [];
    }

    /** @param array<mixed> $instances */
    private function firstInstanceId(array $instances): ?string
    {
        $first = $instances[0] ?? null;

        if (is_string($first)) {
            return $first;
        }

        return is_array($first) && filled($first['id'] ?? null) ? (string) $first['id'] : null;
    }

    /** @param array<mixed> $instances */
    private function firstInstanceUsesScheduler(array $instances): bool|string
    {
        $first = $instances[0] ?? null;

        return is_array($first) && is_bool($first['usesScheduler'] ?? null)
            ? $first['usesScheduler']
            : 'unknown';
    }

    /** @return array<string, mixed> */
    private function missingState(string $application, string $environment): array
    {
        return [
            'application' => ['exists' => false, 'requested' => $application],
            'environment' => ['exists' => false, 'requested' => $environment],
            'resources' => [
                'database' => ['attached' => false],
                'cache' => ['attached' => false],
                'compute' => ['attached' => false],
                'websockets' => ['attached' => false],
            ],
            'runtime' => ['queues' => [], 'scheduler' => 'unknown'],
        ];
    }
}
