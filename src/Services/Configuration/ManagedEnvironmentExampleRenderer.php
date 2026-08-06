<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Configuration;

use LBHurtado\EmiCore\Data\Configuration\EnvironmentVariableData;

final class ManagedEnvironmentExampleRenderer
{
    public const BeginMarker = '# >>> 3neti/x-change managed environment >>>';

    public const EndMarker = '# <<< 3neti/x-change managed environment <<<';

    /**
     * @param  list<EnvironmentVariableData>  $variables
     * @param  list<string>  $providerCodes
     */
    public function render(
        string $existing,
        array $variables,
        string $profile,
        array $providerCodes,
        string $runtimeTier = 'local',
    ): string {
        $pattern = '/'.preg_quote(self::BeginMarker, '/').'.*?'
            .preg_quote(self::EndMarker, '/').'/s';
        $hostOwned = preg_replace($pattern, '', $existing) ?? $existing;
        $hostKeys = $this->environmentKeys($hostOwned);
        $managedVariables = array_values(array_filter(
            $variables,
            static fn (EnvironmentVariableData $variable): bool => ! isset($hostKeys[$variable->key]),
        ));
        $managed = $this->managedBlock(
            $managedVariables,
            $profile,
            $providerCodes,
            $runtimeTier,
        );

        if (preg_match($pattern, $existing) === 1) {
            return rtrim((string) preg_replace($pattern, $managed, $existing)).PHP_EOL;
        }

        return rtrim($existing).PHP_EOL.PHP_EOL.$managed.PHP_EOL;
    }

    /**
     * @return array<string, true>
     */
    private function environmentKeys(string $contents): array
    {
        preg_match_all(
            '/^(?:export\s+)?([A-Z][A-Z0-9_]*)\s*=/m',
            $contents,
            $matches,
        );

        return array_fill_keys($matches[1] ?? [], true);
    }

    /**
     * @param  list<EnvironmentVariableData>  $variables
     * @param  list<string>  $providerCodes
     */
    private function managedBlock(
        array $variables,
        string $profile,
        array $providerCodes,
        string $runtimeTier,
    ): string {
        $groups = [];

        foreach ($variables as $variable) {
            $groups[$variable->category][] = $variable;
        }

        ksort($groups);
        $lines = [
            self::BeginMarker,
            '# Generated documentation. Configure real values in your deployment environment.',
            '# Secrets are intentionally blank. Do not paste credentials into this file.',
        ];

        foreach ($groups as $category => $categoryVariables) {
            $lines[] = '';
            $lines[] = "# {$category}";

            foreach ($categoryVariables as $variable) {
                $required = $variable->isRequired($profile, $providerCodes)
                    ? ' Required for this profile.'
                    : '';
                $lines[] = "# {$variable->description}{$required}";
                $value = $variable->secret ? '' : ($variable->safeExample ?? '');

                if ($variable->key === 'XCHANGE_DEPLOYMENT_PROFILE') {
                    $value = $profile;
                }

                if ($variable->key === 'XCHANGE_RUNTIME_TIER') {
                    $value = $runtimeTier;
                }

                if ($variable->key === 'XCHANGE_CLAIM_EVIDENCE_DISK') {
                    $value = $runtimeTier === 'local' ? 'local' : 's3';
                }

                $lines[] = "{$variable->key}={$value}";
            }
        }

        $lines[] = self::EndMarker;

        return implode(PHP_EOL, $lines);
    }
}
