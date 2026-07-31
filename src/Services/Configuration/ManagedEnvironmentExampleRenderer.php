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
    ): string {
        $managed = $this->managedBlock($variables, $profile, $providerCodes);
        $pattern = '/'.preg_quote(self::BeginMarker, '/').'.*?'
            .preg_quote(self::EndMarker, '/').'/s';

        if (preg_match($pattern, $existing) === 1) {
            return rtrim((string) preg_replace($pattern, $managed, $existing)).PHP_EOL;
        }

        return rtrim($existing).PHP_EOL.PHP_EOL.$managed.PHP_EOL;
    }

    /**
     * @param  list<EnvironmentVariableData>  $variables
     * @param  list<string>  $providerCodes
     */
    private function managedBlock(
        array $variables,
        string $profile,
        array $providerCodes,
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

                $lines[] = "{$variable->key}={$value}";
            }
        }

        $lines[] = self::EndMarker;

        return implode(PHP_EOL, $lines);
    }
}
