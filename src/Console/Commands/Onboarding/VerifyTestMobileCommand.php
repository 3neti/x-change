<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Console\Commands\Onboarding;

use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use LBHurtado\XChange\Support\Auth\MobileNumber;

final class VerifyTestMobileCommand extends Command
{
    protected $signature = 'x-change:onboarding:verify-test-mobile
        {mobile : Local/test mobile number to mark verified}
        {--force : Allow the command outside local/testing environments}';

    protected $description = 'Mark a local/test user mobile as verified for protected x-change workflow testing.';

    public function handle(): int
    {
        if ($this->isProductionEnvironment() && $this->option('force') !== true) {
            $this->components->error('This helper is limited to local/testing environments. Re-run with --force only for an explicitly approved non-production maintenance task.');

            return self::FAILURE;
        }

        $mobile = MobileNumber::normalize((string) $this->argument('mobile'));

        if (! is_string($mobile) || $mobile === '') {
            $this->components->error('The mobile number is invalid.');

            return self::FAILURE;
        }

        $user = $this->resolveUser($mobile);

        if (! $user instanceof Model) {
            $this->components->error('No user was found for the provided mobile number.');

            return self::FAILURE;
        }

        if ($user->getAttribute('mobile_verified_at') === null) {
            $user->forceFill([
                'mobile_verified_at' => now(),
            ])->save();
        }

        $this->components->info(sprintf(
            'Mobile %s is verified for local/test workflow checks.',
            $mobile,
        ));

        return self::SUCCESS;
    }

    private function resolveUser(string $mobile): ?Model
    {
        $modelClass = config('auth.providers.users.model')
            ?: config('x-change.onboarding.issuer_model');

        if (! is_string($modelClass) || ! is_a($modelClass, Model::class, true)) {
            return null;
        }

        $query = $modelClass::query()
            ->whereIn('mobile', MobileNumber::candidates($mobile));

        if ($query->count() !== 1) {
            return null;
        }

        $user = $query->first();

        return $user instanceof Model ? $user : null;
    }

    private function isProductionEnvironment(): bool
    {
        return app()->environment('production') || config('app.env') === 'production';
    }
}
