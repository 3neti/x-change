<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Console\Commands\Affiliation;

use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use LBHurtado\XChange\Actions\Affiliation\EnrollAffiliationRootAccount;
use Throwable;

final class EnrollAffiliationRootCommand extends Command
{
    protected $signature = 'x-change:affiliation:enroll-root
        {account : Primary key of the existing Account owner}
        {--authorization-reference= : Stable commissioning or adoption authority}
        {--confirm-root-adoption : Confirm explicit enrollment as a network root}';

    protected $description = 'Explicitly adopt an existing verified Account as an affiliation-network root';

    public function handle(EnrollAffiliationRootAccount $enroll): int
    {
        if (! (bool) $this->option('confirm-root-adoption')) {
            $this->components->error('Root adoption requires [--confirm-root-adoption].');

            return self::FAILURE;
        }

        $modelClass = (string) config('auth.providers.users.model');

        if (! is_subclass_of($modelClass, Model::class)) {
            $this->components->error('The configured authentication model cannot be adopted.');

            return self::FAILURE;
        }

        $account = $modelClass::query()->find((string) $this->argument('account'));

        if (! $account instanceof Model) {
            $this->components->error('The requested Account could not be resolved.');

            return self::FAILURE;
        }

        try {
            $membership = $enroll->handle(
                $account,
                (string) $this->option('authorization-reference'),
            );
        } catch (Throwable $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->components->info('Affiliation root adopted.');
        $this->line('Network: '.$membership->network->reference);
        $this->line('Membership: '.$membership->reference);

        return self::SUCCESS;
    }
}
