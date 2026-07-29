<?php

declare(strict_types=1);

namespace LBHurtado\XChange\ClaimWalkthrough;

use Illuminate\Support\Facades\Process;
use RuntimeException;
use Symfony\Component\Process\ExecutableFinder;

final class ClaimWalkthroughRecorder
{
    /**
     * @param  array<string, mixed>  $scenario
     * @return array<string, mixed>
     */
    public function record(
        array $scenario,
        string $baseUrl,
        string $artifactDirectory,
        bool $headed,
        int $slowMotion,
        array $options = [],
    ): array {
        $script = dirname(__DIR__, 2).'/scripts/claim-browser-walkthrough.mjs';

        $result = Process::path(base_path())
            ->timeout(300)
            ->env([
                'XCHANGE_CLAIM_WALKTHROUGH_SCENARIO' => (string) ($scenario['key'] ?? ''),
                'XCHANGE_CLAIM_WALKTHROUGH_BASE_URL' => $baseUrl,
                'XCHANGE_CLAIM_WALKTHROUGH_ARTIFACT_DIR' => $artifactDirectory,
                'XCHANGE_CLAIM_WALKTHROUGH_HEADED' => $headed ? '1' : '0',
                'XCHANGE_CLAIM_WALKTHROUGH_SLOW_MO' => (string) $slowMotion,
                'XCHANGE_CLAIM_WALKTHROUGH_PAY_CODE' => (string) ($options['pay_code'] ?? ''),
                'XCHANGE_CLAIM_WALKTHROUGH_MOBILE' => (string) ($options['mobile'] ?? ''),
                'XCHANGE_CLAIM_WALKTHROUGH_BANK_CODE' => (string) ($options['bank_code'] ?? ''),
                'XCHANGE_CLAIM_WALKTHROUGH_ACCOUNT_NUMBER' => (string) ($options['account_number'] ?? ''),
                'XCHANGE_CLAIM_WALKTHROUGH_SUBMIT_CLAIM' => ($options['submit_claim'] ?? false) ? '1' : '0',
                'XCHANGE_CLAIM_WALKTHROUGH_OG_PREVIEW' => json_encode(data_get($scenario, 'fixture.og_preview', []), JSON_UNESCAPED_SLASHES) ?: '{}',
                'XCHANGE_CLAIM_WALKTHROUGH_RIDER_URL' => (string) data_get($scenario, 'fixture.rider.url', ''),
                'XCHANGE_CLAIM_WALKTHROUGH_RIDER_HANDOFF_PREVIEW' => json_encode(data_get($scenario, 'fixture.rider_handoff_preview', []), JSON_UNESCAPED_SLASHES) ?: '{}',
            ])
            ->run([$this->nodeBinary(), $script]);

        if ($result->failed()) {
            throw new RuntimeException(trim($result->errorOutput()) ?: 'Claim walkthrough recorder failed.');
        }

        $payload = json_decode($result->output(), true);

        if (! is_array($payload)) {
            throw new RuntimeException('Claim walkthrough recorder returned invalid JSON.');
        }

        return $payload;
    }

    protected function nodeBinary(): string
    {
        $configured = trim((string) config('x-change.claim_preview.recorder.node_binary'));

        if ($configured !== '') {
            return $configured;
        }

        $finder = new ExecutableFinder;
        $node = $finder->find('node');

        if (is_string($node) && $node !== '') {
            return $node;
        }

        foreach ($this->localNodeDirectories() as $directory) {
            $node = $finder->find('node', null, [$directory]);

            if (is_string($node) && $node !== '') {
                return $node;
            }
        }

        return 'node';
    }

    /**
     * @return list<string>
     */
    protected function localNodeDirectories(): array
    {
        $home = trim((string) ($_SERVER['HOME'] ?? getenv('HOME') ?: ''));

        if ($home === '') {
            return ['/opt/homebrew/bin', '/usr/local/bin', '/usr/bin'];
        }

        return [
            ...$this->matchingDirectories($home.'/Library/Application Support/Herd/config/nvm/versions/node/*/bin'),
            ...$this->matchingDirectories($home.'/.nvm/versions/node/*/bin'),
            '/opt/homebrew/bin',
            '/usr/local/bin',
            '/usr/bin',
        ];
    }

    /**
     * @return list<string>
     */
    protected function matchingDirectories(string $pattern): array
    {
        $directories = glob($pattern, GLOB_ONLYDIR);

        if (! is_array($directories)) {
            return [];
        }

        rsort($directories, SORT_NATURAL);

        return array_values($directories);
    }
}
