<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Console\Commands\Lifecycle;

use Brick\Money\Money;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Number;
use LBHurtado\ModelChannel\Contracts\HasMobileChannel;
use LBHurtado\XChange\Contracts\CommercialOfferingResolverContract;
use LBHurtado\XChange\Lifecycle\Scenarios\LifecycleScenarioRepository;
use LBHurtado\XChange\Lifecycle\Scenarios\LifecycleUserModelResolver;
use LBHurtado\XChange\Services\Commercial\BootstrapCommercialOfferingFactory;
use RuntimeException;

class PrepareLifecycleEnvironmentCommand extends Command
{
    protected $signature = 'xchange:lifecycle:prepare
        {--fresh : Run migrate:fresh}
        {--seed : Run configured lifecycle seeders}
        {--system-float= : Override system wallet funding amount}
        {--user-float= : Override test user wallet funding amount}
        {--json : Output JSON}';

    protected $description = 'Prepare a deterministic environment for lifecycle testing.';

    public function handle(): int
    {
        if (! app()->environment((array) config('x-change.lifecycle.synthetic_funding_environments', ['local', 'testing']))) {
            $this->error('Synthetic lifecycle funding is disabled in this environment.');

            return self::FAILURE;
        }

        if ($this->option('fresh')) {
            if (! $this->confirmFresh()) {
                $this->warn('Aborted.');

                return self::FAILURE;
            }

            Artisan::call('migrate:fresh', ['--force' => true]);
            $this->line(Artisan::output());
        }

        if ($this->option('seed')) {
            $this->runConfiguredSeeders();
        }

        $this->assertLifecycleUserModelSupportsMobile();

        $systemUser = $this->ensureSystemUser();
        $testUser = $this->ensureTestUser();
        $scenarioIssuers = $this->ensureScenarioIssuers();

        $systemFloat = (float) ($this->option('system-float') ?: config('x-change.lifecycle.defaults.system_float', 1_000_000));
        $userFloat = (float) ($this->option('user-float') ?: config('x-change.lifecycle.defaults.user_float', 10_000));

        $this->fundSystemWallet($systemUser, $systemFloat);
        $this->fundTestUser($systemUser, $testUser, $userFloat);

        foreach ($scenarioIssuers as $scenarioIssuer) {
            $this->fundTestUser($systemUser, $scenarioIssuer, $userFloat);
        }

        $this->fundSystemWallet($systemUser, $systemFloat);
        $this->fundIsolatedLifecycleWallet($systemUser, $systemFloat);
        $this->seedInstructionItems();

        $priceList = $this->lifecyclePriceList();

        $payload = [
            'system_user' => [
                'id' => $systemUser->getKey(),
                'email' => $systemUser->getAttribute('email'),
                'mobile' => $systemUser instanceof HasMobileChannel
                    ? $systemUser->getMobileChannel()
                    : null,
            ],
            'test_user' => [
                'id' => $testUser->getKey(),
                'email' => $testUser->getAttribute('email'),
                'mobile' => $testUser instanceof HasMobileChannel
                    ? $testUser->getMobileChannel()
                    : null,
            ],
            'scenario_issuers' => array_map(
                static fn (Model $issuer): array => [
                    'id' => $issuer->getKey(),
                    'email' => $issuer->getAttribute('email'),
                    'mobile' => $issuer instanceof HasMobileChannel
                        ? $issuer->getMobileChannel()
                        : null,
                    'wallet_balance' => $issuer->wallet?->balanceFloat,
                ],
                $scenarioIssuers,
            ),
            'balances' => [
                'system_wallet' => $systemUser->wallet?->balanceFloat ?? null,
                'test_wallet' => $testUser->wallet?->balanceFloat ?? null,
            ],
            'instruction_items_count' => count($priceList),
            'price_list' => $priceList,
        ];

        if ($this->option('json')) {
            $this->line(json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        $this->info('Lifecycle environment prepared.');
        $this->newLine();

        $this->line(
            'System User: '.($systemUser->getAttribute('email') ?: '#'.$systemUser->getKey())
            .' / '.($systemUser instanceof HasMobileChannel ? ($systemUser->getMobileChannel() ?: 'n/a') : 'n/a')
        );

        $this->line(
            'Test User: '.($testUser->getAttribute('email') ?: '#'.$testUser->getKey())
            .' / '.($testUser instanceof HasMobileChannel ? ($testUser->getMobileChannel() ?: 'n/a') : 'n/a')
        );

        $this->line('System Wallet Balance: '.(
            $systemUser->wallet?->balanceFloat !== null
                ? Number::currency((float) $systemUser->wallet->balanceFloat, in: 'PHP')
                : 'n/a'
        ));

        $this->line('Test Wallet Balance: '.(
            $testUser->wallet?->balanceFloat !== null
                ? Number::currency((float) $testUser->wallet->balanceFloat, in: 'PHP')
                : 'n/a'
        ));

        foreach ($scenarioIssuers as $scenarioIssuer) {
            $this->line(sprintf(
                'Scenario Issuer: %s / %s / %s',
                $scenarioIssuer->getAttribute('email') ?: '#'.$scenarioIssuer->getKey(),
                $scenarioIssuer instanceof HasMobileChannel
                    ? ($scenarioIssuer->getMobileChannel() ?: 'n/a')
                    : 'n/a',
                $scenarioIssuer->wallet?->balanceFloat !== null
                    ? Number::currency((float) $scenarioIssuer->wallet->balanceFloat, in: 'PHP')
                    : 'n/a',
            ));
        }

        $this->line('Instruction Items: '.count($priceList));
        $this->newLine();

        $this->table(
            ['Index', 'Name', 'Type', 'Price', 'Currency'],
            array_map(
                fn (array $item) => [
                    $item['index'],
                    $item['name'],
                    $item['type'],
                    Number::currency((float) $item['price'], in: $item['currency']),
                    $item['currency'],
                ],
                $priceList
            )
        );

        return self::SUCCESS;
    }

    protected function confirmFresh(): bool
    {
        if (app()->environment('production')) {
            return false;
        }

        if (app()->runningUnitTests()) {
            return true;
        }

        return $this->confirm('This will destroy all database data. Continue?', false);
    }

    protected function runConfiguredSeeders(): void
    {
        foreach ((array) config('x-change.lifecycle.seeders', []) as $class) {
            if (! is_string($class) || $class === '' || ! class_exists($class)) {
                continue;
            }

            Artisan::call('db:seed', [
                '--class' => $class,
                '--force' => true,
            ]);

            $this->line(Artisan::output());
        }
    }

    protected function ensureSystemUser(): Model
    {
        $class = $this->userModelClass();

        $configured = config('x-change.lifecycle.defaults.system_user_email')
            ?: env('SYSTEM_USER_ID')
                ?: config('account.system_user.identifier');

        $email = is_string($configured) && filter_var($configured, FILTER_VALIDATE_EMAIL)
            ? $configured
            : 'system@example.test';

        $mobile = (string) config('x-change.lifecycle.defaults.system_user_mobile', '');

        /** @var Model $user */
        $user = $class::firstOrCreate(
            ['email' => $email],
            [
                'name' => 'System User',
                'password' => bcrypt('password'),
            ]
        );

        if ($mobile !== '') {
            if (! $user instanceof HasMobileChannel) {
                throw new RuntimeException(sprintf(
                    'Lifecycle user model [%s] must implement [%s] to support mobile channels.',
                    $class,
                    HasMobileChannel::class,
                ));
            }

            if ($user->getMobileChannel() !== $mobile) {
                $user->setMobileChannel($mobile);
                $user->refresh();
            }
        }

        return $user;
    }

    protected function ensureTestUser(): Model
    {
        $class = $this->userModelClass();

        $email = (string) (
            config('x-change.lifecycle.defaults.test_user_email')
                ?: 'lifecycle-user@example.test'
        );

        $mobile = (string) config('x-change.lifecycle.defaults.test_user_mobile', '');

        /** @var Model $user */
        $user = $class::firstOrCreate(
            ['email' => $email],
            [
                'name' => 'Lifecycle Test User',
                'password' => bcrypt('password'),
            ]
        );

        if ($mobile !== '') {
            if (! $user instanceof HasMobileChannel) {
                throw new RuntimeException(sprintf(
                    'Lifecycle user model [%s] must implement [%s] to support mobile channels.',
                    $class,
                    HasMobileChannel::class,
                ));
            }

            if ($user->getMobileChannel() !== $mobile) {
                $user->setMobileChannel($mobile);
                $user->refresh();
            }
        }

        return $user;
    }

    /**
     * @return list<Model>
     */
    protected function ensureScenarioIssuers(): array
    {
        $issuers = [];

        foreach (app(LifecycleScenarioRepository::class)->all() as $scenario) {
            if (! is_array($scenario)) {
                continue;
            }

            $email = trim((string) data_get($scenario, 'lifecycle.issuer_email'));

            if ($email === '' || isset($issuers[$email])) {
                continue;
            }

            $issuers[$email] = $this->ensureScenarioIssuer(
                email: $email,
                mobile: trim((string) data_get($scenario, 'lifecycle.issuer_mobile')),
            );
        }

        return array_values($issuers);
    }

    protected function ensureScenarioIssuer(string $email, string $mobile): Model
    {
        $class = $this->userModelClass();

        /** @var Model $user */
        $user = $class::firstOrCreate(
            ['email' => $email],
            [
                'name' => 'Lifecycle Scenario Issuer',
                'password' => bcrypt('password'),
            ],
        );

        if ($mobile !== '') {
            if (! $user instanceof HasMobileChannel) {
                throw new RuntimeException(sprintf(
                    'Lifecycle user model [%s] must implement [%s] to support mobile channels.',
                    $class,
                    HasMobileChannel::class,
                ));
            }

            if ($user->getMobileChannel() !== $mobile) {
                $user->setMobileChannel($mobile);
                $user->refresh();
            }
        }

        return $user;
    }

    protected function fundSystemWallet(Model $systemUser, float $amount): void
    {
        if ($amount <= 0) {
            return;
        }

        $systemUser->unsetRelation('wallet');
        $wallet = method_exists($systemUser, 'getWallet')
            ? $systemUser->getWallet('platform')
            : $systemUser->wallet;
        $difference = $amount - (float) ($wallet?->balanceFloat ?? 0);

        if ($difference > 0 && method_exists($wallet, 'depositFloat')) {
            $wallet->depositFloat($difference);
        } elseif ($difference > 0 && method_exists($systemUser, 'depositFloat')) {
            $systemUser->depositFloat($difference);
        }
    }

    protected function fundTestUser(Model $systemUser, Model $testUser, float $amount): void
    {
        if ($amount <= 0 || $systemUser->getKey() === $testUser->getKey()) {
            return;
        }

        $difference = $amount - (float) ($testUser->wallet?->balanceFloat ?? 0);

        if ($difference > 0 && method_exists($systemUser, 'transferFloat')) {
            $systemUser->transferFloat($testUser, $difference);
        }
    }

    protected function fundIsolatedLifecycleWallet(Model $systemUser, float $amount): void
    {
        if (
            $amount <= 0
            || ! method_exists($systemUser, 'getWallet')
            || ! method_exists($systemUser, 'createWallet')
        ) {
            return;
        }

        $usesSystemLifecycleIssuer = collect(
            app(LifecycleScenarioRepository::class)->all()
        )->contains(static fn (mixed $scenario): bool => is_array($scenario)
            && data_get($scenario, 'lifecycle.issuer_role') === 'system'
            && data_get($scenario, 'lifecycle.funding_boundary') === 'isolated_compatibility_wallet');

        if (! $usesSystemLifecycleIssuer) {
            return;
        }

        $wallet = $systemUser->getWallet('lifecycle')
            ?? $systemUser->createWallet([
                'name' => 'Lifecycle Scenario Funds',
                'slug' => 'lifecycle',
            ]);
        $difference = $amount - (float) $wallet->balanceFloat;

        if ($difference > 0 && method_exists($wallet, 'depositFloat')) {
            $wallet->depositFloat($difference);
        }
    }

    protected function userModelClass(): string
    {
        return app(LifecycleUserModelResolver::class)->resolve();
    }

    protected function seedInstructionItems(): void
    {
        try {
            $offering = app(CommercialOfferingResolverContract::class)->resolve('pay_code');
        } catch (\DomainException) {
            $offering = app(BootstrapCommercialOfferingFactory::class)->make('pay_code');
        }

        foreach ($offering->catalog->items as $item) {
            $index = $item->reference;

            DB::table('instruction_items')->updateOrInsert(
                ['index' => $index],
                [
                    'name' => $item->label,
                    'type' => $item->category,
                    'price' => $item->unitPriceMinor,
                    'currency' => $offering->catalog->currency,
                    'meta' => json_encode([
                        'label' => $item->label,
                        'category' => $item->category,
                        'deprecated' => $item->deprecated,
                        'catalog_reference' => $offering->catalog->reference,
                        'catalog_version' => $offering->catalog->version,
                        'commercial_offering_reference' => $offering->reference,
                        'commercial_offering_version' => $offering->version,
                        'commercial_offering_snapshot_hash' => $offering->snapshotHash(),
                    ], JSON_UNESCAPED_SLASHES),
                    'revenue_destination_type' => null,
                    'revenue_destination_id' => null,
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
        }

        $this->info('Instruction items projected from the governed Commercial Offering.');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function lifecyclePriceList(): array
    {
        return DB::table('instruction_items')
            ->orderBy('index')
            ->get(['index', 'name', 'type', 'price', 'currency'])
            ->map(function ($row): array {
                $money = $this->moneyFromMinor((int) $row->price, (string) $row->currency);

                return [
                    'index' => $row->index,
                    'name' => $row->name,
                    'type' => $row->type,
                    'price_minor' => (int) $row->price,
                    'price' => $money->getAmount()->toFloat(),
                    'currency' => $row->currency,
                ];
            })
            ->toArray();
    }

    protected function assertLifecycleUserModelSupportsMobile(): void
    {
        $class = $this->userModelClass();

        if (! is_subclass_of($class, HasMobileChannel::class)) {
            throw new RuntimeException(sprintf(
                'Configured lifecycle user model [%s] must implement [%s].',
                $class,
                HasMobileChannel::class,
            ));
        }
    }

    protected function inferInstructionItemName(string $index, array $data): string
    {
        if (! empty($data['label']) && is_string($data['label'])) {
            return $data['label'];
        }

        return str($index)
            ->replace(['.', '_'], ' ')
            ->title()
            ->toString();
    }

    protected function inferInstructionItemType(string $index, array $data): string
    {
        if (! empty($data['category']) && is_string($data['category'])) {
            return $data['category'];
        }

        return match (true) {
            str_starts_with($index, 'inputs.fields.') => 'input_fields',
            str_starts_with($index, 'feedback.') => 'feedback',
            str_starts_with($index, 'validation.') => 'validation',
            str_starts_with($index, 'cash.validation.') => 'validation',
            str_starts_with($index, 'rider.') => 'rider',
            str_starts_with($index, 'voucher_type.') => 'base',
            $index === 'cash.amount' => 'base',
            default => 'other',
        };
    }

    protected function moneyFromMinor(int $minorAmount, string $currency): Money
    {
        return Money::ofMinor($minorAmount, $currency);
    }
}
