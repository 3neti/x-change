<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Console\Commands;

use Illuminate\Console\Command;

final class CommissionXChangeCommand extends Command
{
    protected $signature = 'x-change:commission
        {--profile= : Explicit deployment profile}
        {--system-principal-name= : Display name for the System Account}
        {--system-principal-email= : Stable System Account email}
        {--treasury-opening-policy= : unattributed, system-capital, or configured}
        {--capitalization-authorization-reference= : Required for system-capital}
        {--confirm-system-ownership : Confirm opening provider funds belong to the System Account}
        {--no-treasury : Explicitly leave commissioning incomplete}
        {--force : Refresh published resources}
        {--dry-run : Show the remote commissioning plan without changes}
        {--json : Output JSON}';

    protected $description = 'Run fail-closed x-change preflight, installation, and verification.';

    public function handle(): int
    {
        $profile = trim((string) ($this->option('profile')
            ?: config('x-change.deployment.profile')));
        $systemEmail = trim((string) ($this->option('system-principal-email')
            ?: config('x-change.payout.system_user_id')));
        $systemName = trim((string) ($this->option('system-principal-name')
            ?: config('app.name').' System'));
        $plan = [
            'profile' => $profile,
            'steps' => ['preflight', 'install', 'verify'],
            'provisions_system_principal' => true,
            'treasury_deferred' => (bool) $this->option('no-treasury'),
        ];

        if ($this->option('dry-run')) {
            return $this->renderResult(true, 'planned', $plan);
        }

        if ($profile === '' || $systemEmail === '') {
            return $this->renderResult(
                false,
                'XCHANGE_DEPLOYMENT_PROFILE and XCHANGE_SYSTEM_USER_ID are required.',
                $plan,
            );
        }

        $quiet = (bool) $this->option('json');
        $preflightExitCode = $this->invokeArtisanCommand($quiet, 'x-change:doctor', [
            '--pre-install' => true,
            '--strict' => true,
        ]);

        if ($preflightExitCode !== self::SUCCESS) {
            return $this->renderResult(false, 'preflight_failed', $plan);
        }

        $installExitCode = $this->invokeArtisanCommand($quiet, 'x-change:install', [
            '--profile' => $profile,
            '--force' => (bool) $this->option('force'),
            '--provision-system-principal' => true,
            '--system-principal-name' => $systemName,
            '--system-principal-email' => $systemEmail,
            '--confirm-system-principal' => true,
            '--treasury-opening-policy' => $this->option('treasury-opening-policy'),
            '--capitalization-authorization-reference' => $this->option(
                'capitalization-authorization-reference',
            ),
            '--confirm-system-ownership' => (bool) $this->option('confirm-system-ownership'),
            '--no-treasury' => (bool) $this->option('no-treasury'),
            '--no-interaction' => true,
        ]);

        if ($installExitCode !== self::SUCCESS) {
            return $this->renderResult(false, 'installation_failed', $plan);
        }

        $verificationExitCode = $this->invokeArtisanCommand($quiet, 'x-change:doctor', [
            '--strict' => true,
        ]);

        if ($verificationExitCode !== self::SUCCESS) {
            return $this->renderResult(false, 'verification_failed', $plan);
        }

        return $this->renderResult(true, 'operational', $plan);
    }

    /**
     * @param  array<string, mixed>  $parameters
     */
    private function invokeArtisanCommand(bool $quiet, string $command, array $parameters): int
    {
        return $quiet
            ? $this->callSilently($command, $parameters)
            : $this->call($command, $parameters);
    }

    /**
     * @param  array<string, mixed>  $plan
     */
    private function renderResult(bool $success, string $status, array $plan): int
    {
        if ($this->option('json')) {
            $this->line(json_encode([
                'schema' => 'x-change.commission.v1',
                'success' => $success,
                'status' => $status,
                ...$plan,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } elseif ($success) {
            $this->components->info(
                $status === 'planned'
                    ? 'Remote commissioning plan is ready; no changes were made.'
                    : 'X-Change commissioning is operational.',
            );
        } else {
            $this->components->error($status);
        }

        return $success ? self::SUCCESS : self::FAILURE;
    }
}
