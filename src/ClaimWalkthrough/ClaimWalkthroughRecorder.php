<?php

declare(strict_types=1);

namespace LBHurtado\XChange\ClaimWalkthrough;

use Illuminate\Support\Facades\Process;
use RuntimeException;

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
            ])
            ->run(['node', $script]);

        if ($result->failed()) {
            throw new RuntimeException(trim($result->errorOutput()) ?: 'Claim walkthrough recorder failed.');
        }

        $payload = json_decode($result->output(), true);

        if (! is_array($payload)) {
            throw new RuntimeException('Claim walkthrough recorder returned invalid JSON.');
        }

        return $payload;
    }
}
