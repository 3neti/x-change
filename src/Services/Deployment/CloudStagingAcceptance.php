<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Deployment;

use Illuminate\Http\Client\Factory;
use Illuminate\Support\Str;
use RuntimeException;

final readonly class CloudStagingAcceptance
{
    public function __construct(private Factory $http) {}

    /** @return array<string, mixed> */
    public function inspect(string $baseUrl): array
    {
        $baseUrl = rtrim($baseUrl, '/');

        if (! Str::startsWith($baseUrl, ['https://', 'http://'])) {
            throw new RuntimeException('Cloud acceptance requires an absolute application URL.');
        }

        $checks = [];

        foreach (['/', '/login', '/x/cockpit'] as $path) {
            $response = $this->http->connectTimeout(5)
                ->timeout(15)
                ->withoutRedirecting()
                ->get($baseUrl.$path);
            $safeStatus = $response->status() >= 200 && $response->status() < 500;
            $knownAssetFailure = Str::contains($response->body(), [
                'Unable to locate file in Vite manifest',
                'Vite manifest not found',
            ]);
            $checks[] = [
                'path' => $path,
                'status' => $response->status(),
                'passed' => $safeStatus && ! $knownAssetFailure,
            ];
        }

        return [
            'schema' => 'x-change.cloud-acceptance.v1',
            'success' => collect($checks)->every(
                static fn (array $check): bool => $check['passed'],
            ),
            'provider_calls' => false,
            'real_money_transfer' => false,
            'checks' => $checks,
        ];
    }
}
