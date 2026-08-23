<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Console\Commands\Keepsake;

use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use LBHurtado\XChange\Actions\Keepsake\CreateInstanceKeepsakeExport;
use LBHurtado\XChange\Actions\Keepsake\PlanInstanceKeepsakeExport;
use LBHurtado\XChange\Console\Concerns\InteractsWithJsonOutput;
use LBHurtado\XChange\Exceptions\InstanceKeepsakeException;
use LBHurtado\XChange\Services\Keepsake\KeepsakeUserModelResolver;
use Throwable;

final class ExportInstanceKeepsakeCommand extends Command
{
    use InteractsWithJsonOutput;

    protected $help = <<<HELP
Preview or create an encrypted, non-restorable X-Change instance keepsake.

Typical flow:

1. Run a dry-run (default) to get scope, counts, and a deterministic plan hash.
2. Run again with --create and the exact --plan-hash to generate the encrypted archive.
3. Verify the generated archive outside of this command before deciding whether to migrate.

Important behavior:

- Dry runs do not mutate voucher, account, treasury, provider, or file state.
- Create mode requires both a sensitive-operation acknowledgment and a plan hash.
- A successful create result is non-restorable and includes a one-time authenticated download path.
- Location evidence defaults to the claim map image only. Add precise location payload with explicit confirmation.
- Missing artifacts or plan drift cause safe rejection unless explicitly accepted by design.
- The command never triggers provider calls, movements of funds, or financial restoration operations.

Usage:

php artisan x-change:instance-keepsake:export --help

Common examples:

# scope one user and inspect a dry-run plan
php artisan x-change:instance-keepsake:export --user=alice@example.test --json

# run an account-only dry run with sensitive scope
php artisan x-change:instance-keepsake:export --user=alice@example.test --include=accounts --include-personal-data --confirm-sensitive-export --json

# create archive after copying the dry-run plan hash
php artisan x-change:instance-keepsake:export \
  --user=alice@example.test \
  --include=accounts \
  --include-personal-data \
  --include-location-data \
  --confirm-sensitive-export \
  --confirm-location-data \
  --create \
  --plan-hash=<PLAN_HASH_FROM_DRY_RUN> \
  --export-reference=<OPERATOR_UNIQUE_REFERENCE> \
  --authorization-reference=<TICKET_OR_WORKFLOW_REFERENCE> \
  --download-user=alice@example.test \
  --json
HELP;

    protected $signature = 'x-change:instance-keepsake:export
        {--all-users : Include every user Account}
        {--user=* : Include specific users by email or configured model key}
        {--include=* : accounts, pay-codes, claim-evidence, blueprint}
        {--include-personal-data : Include names, emails, and mobiles in the archive}
        {--include-location-data : Include precise location JSON sidecars in the archive}
        {--confirm-location-data : Separately acknowledge precise location export}
        {--allow-incomplete : Permit a review-required archive with explicit omissions}
        {--confirm-incomplete-export : Acknowledge an incomplete sensitive archive}
        {--confirm-sensitive-export : Confirm bulk sensitive export scope}
        {--create : Create and privately stage the encrypted archive (requires --plan-hash)}
        {--plan-hash= : Exact plan hash from a dry-run}
        {--export-reference= : Stable immutable export reference}
        {--authorization-reference= : External approval/ticket reference}
        {--download-user= : Existing authorized user identifier for authenticated download}
        {--json : Emit a machine-readable result}
        {--pretty : Pretty-print JSON output}';

    protected $description = 'Preview or create an encrypted, non-restorable X-Change instance keepsake';

    public function handle(
        PlanInstanceKeepsakeExport $planner,
        CreateInstanceKeepsakeExport $creator,
        KeepsakeUserModelResolver $userModels,
    ): int {
        try {
            $create = (bool) $this->option('create');
            $allUsers = (bool) $this->option('all-users');
            $includesPersonalData = (bool) $this->option('include-personal-data');
            $includesLocationData = (bool) $this->option('include-location-data');
            $includes = $this->includes();
            $includesClaimEvidence = $includes === [] || in_array('claim-evidence', $includes, true);

            if (($allUsers || $includesPersonalData || $includesLocationData || $includesClaimEvidence)
                && ! (bool) $this->option('confirm-sensitive-export')) {
                throw new InstanceKeepsakeException('confirmation_required', 'Sensitive scope requires --confirm-sensitive-export.');
            }

            if ($includesLocationData && ! (bool) $this->option('confirm-location-data')) {
                throw new InstanceKeepsakeException('confirmation_required', 'Precise location export requires --confirm-location-data.');
            }

            if ($create && (bool) $this->option('allow-incomplete') && ! (bool) $this->option('confirm-incomplete-export')) {
                throw new InstanceKeepsakeException('confirmation_required', 'Incomplete creation requires --confirm-incomplete-export.');
            }

            $plan = $planner->handle(
                allUsers: $allUsers,
                userIdentifiers: array_values(array_filter(array_map('strval', (array) $this->option('user')))),
                includes: $includes,
                includePersonalData: $includesPersonalData,
                includeLocationData: $includesLocationData,
                allowIncomplete: (bool) $this->option('allow-incomplete'),
                materializeArtifacts: $create,
            );

            if (! $create) {
                $this->renderPayload([
                    ...$plan->summary(),
                    'dry_run' => true,
                    'writes_database' => false,
                    'writes_storage' => false,
                    'writes_journal' => false,
                    'message' => 'Review this plan and pass its exact --plan-hash with --create.',
                ], 'Instance keepsake dry run');

                return self::SUCCESS;
            }

            $grantee = $this->resolveDownloadGrantee(
                $userModels,
                trim((string) $this->option('download-user')),
            );
            $result = $creator->handle(
                plan: $plan,
                expectedPlanHash: trim((string) $this->option('plan-hash')),
                exportReference: trim((string) $this->option('export-reference')),
                authorizationReference: trim((string) $this->option('authorization-reference')),
                downloadGrantee: $grantee,
            );
            $this->renderPayload($result, 'Encrypted instance keepsake');

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $reason = $exception instanceof InstanceKeepsakeException
                ? $exception->reason
                : 'unexpected_failure';

            if (! $exception instanceof InstanceKeepsakeException) {
                report($exception);
            }

            $this->renderPayload([
                'schema' => 'x-change.instance-keepsake-export.v1',
                'status' => 'rejected',
                'reason' => $reason,
                'message' => $exception instanceof InstanceKeepsakeException
                    ? $exception->getMessage()
                    : 'The instance keepsake could not be completed safely.',
                'created' => false,
                'provider_calls' => false,
                'moves_money' => false,
                'restores_financial_state' => false,
                'migrate_fresh_invoked' => false,
            ]);

            return self::FAILURE;
        }
    }

    /** @return list<string> */
    private function includes(): array
    {
        $includes = [];

        foreach ((array) $this->option('include') as $value) {
            foreach (explode(',', (string) $value) as $include) {
                if (trim($include) !== '') {
                    $includes[] = trim($include);
                }
            }
        }

        return $includes;
    }

    private function resolveDownloadGrantee(KeepsakeUserModelResolver $resolver, string $identifier): Model
    {
        if ($identifier === '') {
            throw new InstanceKeepsakeException('authorization_required', 'Creation requires --download-user.');
        }

        $model = $resolver->resolve();
        $instance = new $model;
        $grantee = $model::query()
            ->where(function ($query) use ($instance, $identifier): void {
                $hasLookup = false;

                if ($instance->getKeyType() !== 'int' || ctype_digit($identifier)) {
                    $query->where($instance->getKeyName(), $identifier);
                    $hasLookup = true;
                }

                if (Schema::hasColumn($instance->getTable(), 'email')) {
                    $hasLookup
                        ? $query->orWhere('email', $identifier)
                        : $query->where('email', $identifier);
                    $hasLookup = true;
                }

                if (! $hasLookup) {
                    $query->whereRaw('1 = 0');
                }
            })
            ->first();

        if (! $grantee instanceof Model) {
            throw new InstanceKeepsakeException('authorization_required', 'The designated download user was not found.');
        }

        return $grantee;
    }
}
