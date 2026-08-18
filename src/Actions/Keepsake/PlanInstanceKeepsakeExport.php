<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Actions\Keepsake;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Data\Keepsake\InstanceKeepsakeContext;
use LBHurtado\XChange\Data\Keepsake\InstanceKeepsakePlan;
use LBHurtado\XChange\Exceptions\InstanceKeepsakeException;
use LBHurtado\XChange\Services\Keepsake\CanonicalKeepsakeJson;
use LBHurtado\XChange\Services\Keepsake\InstanceKeepsakeContributorCatalog;
use LBHurtado\XChange\Services\Keepsake\KeepsakeUserModelResolver;

final readonly class PlanInstanceKeepsakeExport
{
    private const ALLOWED_INCLUDES = ['accounts', 'pay-codes', 'claim-evidence', 'blueprint'];

    public function __construct(
        private KeepsakeUserModelResolver $users,
        private InstanceKeepsakeContributorCatalog $contributors,
        private CanonicalKeepsakeJson $json,
    ) {}

    /**
     * @param  list<string>  $includes
     * @param  list<string>  $userIdentifiers
     */
    public function handle(
        bool $allUsers,
        array $userIdentifiers,
        array $includes,
        bool $includePersonalData,
        bool $includeLocationData,
        bool $allowIncomplete,
        bool $materializeArtifacts,
    ): InstanceKeepsakePlan {
        $includes = $this->normalizeIncludes($includes);

        if (! $allUsers && $userIdentifiers === []) {
            throw new InstanceKeepsakeException('scope_required', 'Specify --all-users or at least one --user value.');
        }

        $users = $this->loadUsers($allUsers, $userIdentifiers);
        $vouchers = $this->loadVouchers($users);
        $observedAt = $this->observationWatermark($users, $vouchers);
        $context = new InstanceKeepsakeContext(
            users: $users,
            vouchers: $vouchers,
            includes: $includes,
            currency: mb_strtoupper((string) config('x-change.product.default_currency', 'PHP')),
            includePersonalData: $includePersonalData,
            includeLocationData: $includeLocationData,
            allowIncomplete: $allowIncomplete,
            materializeArtifacts: $materializeArtifacts,
            observedAt: $observedAt,
        );

        $contributions = [];
        $paths = [];
        $artifactBytes = 0;
        $artifactCount = 0;
        $omissionCount = 0;

        foreach ($this->contributors->contributors() as $contributor) {
            $contribution = $contributor->contribute($context);

            if ($contribution->key !== $contributor->key()) {
                throw new InvalidArgumentException("Keepsake contributor [{$contributor->key()}] returned a mismatched key.");
            }

            foreach ([...array_keys($contribution->snapshotFiles), ...array_keys($contribution->blueprintFiles)] as $path) {
                $this->assertUniqueSafePath($path, $paths);
            }

            foreach ($contribution->artifacts as $artifact) {
                $this->assertUniqueSafePath($artifact['path'], $paths);
                $artifactBytes += $artifact['size'];
                $artifactCount++;
            }

            $omissionCount += count($contribution->omissions);
            $contributions[] = $contribution;
        }

        $this->assertLimits(count($users), count($vouchers), $artifactCount, $artifactBytes);

        $hash = $this->json->hash([
            'schema' => 'x-change.instance-keepsake-plan.v1',
            'includes' => $includes,
            'include_personal_data' => $includePersonalData,
            'include_location_data' => $includeLocationData,
            'allow_incomplete' => $allowIncomplete,
            'users' => array_column($users, 'reference'),
            'vouchers' => array_column($vouchers, 'reference'),
            'contributions' => array_map(
                fn ($contribution): array => [
                    'key' => $contribution->key,
                    'snapshot_schema_version' => $contribution->snapshotSchemaVersion,
                    'blueprint_schema_version' => $contribution->blueprintSchemaVersion,
                    'snapshot_files' => $this->fileHashes($contribution->snapshotFiles),
                    'blueprint_files' => $this->fileHashes($contribution->blueprintFiles),
                    'artifacts' => array_map(
                        static fn (array $artifact): array => [
                            'path' => $artifact['path'],
                            'mime_type' => $artifact['mime_type'],
                            'size' => $artifact['size'],
                            'sha256' => $artifact['sha256'],
                            'source' => $artifact['source'],
                        ],
                        $contribution->artifacts,
                    ),
                    'summary' => $contribution->summary,
                    'omissions' => $contribution->omissions,
                ],
                $contributions,
            ),
        ]);

        return new InstanceKeepsakePlan(
            hash: $hash,
            observedAt: $observedAt,
            contributions: $contributions,
            userCount: count($users),
            payCodeCount: count($vouchers),
            artifactCount: $artifactCount,
            artifactBytes: $artifactBytes,
            omissionCount: $omissionCount,
        );
    }

    /** @return list<array{reference:string,model:Model}> */
    private function loadUsers(bool $allUsers, array $identifiers): array
    {
        $model = $this->users->resolve();
        $instance = new $model;
        $query = $model::query()->orderBy($instance->getQualifiedKeyName());

        if (! $allUsers) {
            $query->where(function (Builder $query) use ($identifiers, $instance): void {
                $query->whereIn($instance->getKeyName(), $identifiers);

                if (Schema::hasColumn($instance->getTable(), 'email')) {
                    $query->orWhereIn('email', $identifiers);
                }
            });
        }

        $users = [];
        $limit = (int) config('x-change.instance_keepsake.max_users', 1_000);

        foreach ($query->cursor() as $user) {
            if (count($users) >= $limit) {
                throw new InstanceKeepsakeException('limit_exceeded', 'The keepsake user limit was exceeded.');
            }

            $users[] = [
                'reference' => 'account-'.str_pad((string) (count($users) + 1), 6, '0', STR_PAD_LEFT),
                'model' => $user,
            ];
        }

        return $users;
    }

    /** @param list<array{reference:string,model:Model}> $users
     * @return list<array{reference:string,model:Model}>
     */
    private function loadVouchers(array $users): array
    {
        if ($users === []) {
            return [];
        }

        $owners = [];

        foreach ($users as $user) {
            $owners[$user['model']->getMorphClass()][] = $user['model']->getKey();
        }

        $query = Voucher::query()
            ->where(function (Builder $query) use ($owners): void {
                foreach ($owners as $type => $keys) {
                    $query->orWhere(function (Builder $owner) use ($type, $keys): void {
                        $owner->where('owner_type', $type)->whereIn('owner_id', $keys);
                    });
                }
            })
            ->orderBy('id');

        $vouchers = [];
        $limit = (int) config('x-change.instance_keepsake.max_pay_codes', 10_000);

        foreach ($query->cursor() as $voucher) {
            if (count($vouchers) >= $limit) {
                throw new InstanceKeepsakeException('limit_exceeded', 'The keepsake Pay Code limit was exceeded.');
            }

            $vouchers[] = [
                'reference' => 'pay-code-'.str_pad((string) (count($vouchers) + 1), 6, '0', STR_PAD_LEFT),
                'model' => $voucher,
            ];
        }

        return $vouchers;
    }

    /** @return list<string> */
    private function normalizeIncludes(array $includes): array
    {
        $normalized = array_values(array_unique(array_filter(array_map(
            static fn (string $value): string => trim($value),
            $includes,
        ))));

        if ($normalized === []) {
            $normalized = ['accounts', 'pay-codes', 'claim-evidence', 'blueprint'];
        }

        foreach ($normalized as $include) {
            if (! in_array($include, self::ALLOWED_INCLUDES, true)) {
                throw new InstanceKeepsakeException('invalid_scope', "Unsupported keepsake contributor [{$include}].");
            }
        }

        sort($normalized);

        return $normalized;
    }

    /** @param array<string, bool> $paths */
    private function assertUniqueSafePath(string $path, array &$paths): void
    {
        if ($path === ''
            || str_starts_with($path, '/')
            || preg_match('/(^|\/)\.\.?(\/|$)/', $path) === 1
            || preg_match('/[\x00-\x1F\x7F\\\\]/', $path) === 1) {
            throw new InstanceKeepsakeException('unsafe_archive_path', 'A keepsake contributor produced an unsafe archive path.');
        }

        $key = mb_strtolower($path);

        if (isset($paths[$key])) {
            throw new InstanceKeepsakeException('duplicate_archive_path', 'A keepsake contributor produced a duplicate archive path.');
        }

        $paths[$key] = true;
    }

    private function assertLimits(int $users, int $payCodes, int $artifacts, int $bytes): void
    {
        if ($users > (int) config('x-change.instance_keepsake.max_users', 1_000)
            || $payCodes > (int) config('x-change.instance_keepsake.max_pay_codes', 10_000)
            || $artifacts > (int) config('x-change.instance_keepsake.max_artifacts', 20_000)
            || $bytes > (int) config('x-change.instance_keepsake.max_total_bytes', 536_870_912)) {
            throw new InstanceKeepsakeException('limit_exceeded', 'The keepsake export exceeds a configured safety limit.');
        }
    }

    /** @param array<string, string> $files
     * @return array<string, string>
     */
    private function fileHashes(array $files): array
    {
        $hashes = [];

        foreach ($files as $path => $contents) {
            $hashes[$path] = hash('sha256', $contents);
        }

        ksort($hashes);

        return $hashes;
    }

    /**
     * @param  list<array{reference:string,model:Model}>  $users
     * @param  list<array{reference:string,model:Model}>  $vouchers
     */
    private function observationWatermark(array $users, array $vouchers): string
    {
        $timestamps = [];

        foreach ([...$users, ...$vouchers] as $item) {
            $timestamp = $item['model']->getAttribute('updated_at') ?? $item['model']->getAttribute('created_at');

            if ($timestamp instanceof \DateTimeInterface) {
                $timestamps[] = CarbonImmutable::instance($timestamp)->utc();
            }
        }

        if ($timestamps === []) {
            return CarbonImmutable::createFromTimestampUTC(0)->toIso8601String();
        }

        usort($timestamps, static fn (CarbonImmutable $left, CarbonImmutable $right): int => $left <=> $right);

        return end($timestamps)->toIso8601String();
    }
}
