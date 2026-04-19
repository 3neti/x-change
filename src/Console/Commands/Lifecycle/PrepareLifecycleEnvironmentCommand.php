<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Console\Commands\Lifecycle;

use App\Models\User;
use Brick\Money\Money;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Number;
use LBHurtado\ModelChannel\Contracts\HasMobileChannel;
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

        $systemFloat = (float) ($this->option('system-float') ?: config('x-change.lifecycle.defaults.system_float', 1_000_000));
        $userFloat = (float) ($this->option('user-float') ?: config('x-change.lifecycle.defaults.user_float', 10_000));

        $this->fundSystemWallet($systemUser, $systemFloat);
        $this->fundTestUser($systemUser, $testUser, $userFloat);

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

    protected function fundSystemWallet(Model $systemUser, float $amount): void
    {
        if ($amount <= 0 || ! method_exists($systemUser, 'depositFloat')) {
            return;
        }

        $systemUser->depositFloat($amount);
    }

    protected function fundTestUser(Model $systemUser, Model $testUser, float $amount): void
    {
        if ($amount <= 0 || $systemUser->getKey() === $testUser->getKey()) {
            return;
        }

        if (method_exists($systemUser, 'transferFloat')) {
            $systemUser->transferFloat($testUser, $amount);
        }
    }

    protected function userModelClass(): string
    {
        $class = (string) config('x-change.lifecycle.defaults.user_model', User::class);

        if ($class === '' || ! class_exists($class)) {
            throw new RuntimeException('Configured lifecycle user model is invalid.');
        }

        return $class;
    }

    protected function seedInstructionItems(): void
    {
        $configuredSeeder = config('x-change.lifecycle.seeders.instruction_items');

        if (is_string($configuredSeeder) && $configuredSeeder !== '' && class_exists($configuredSeeder)) {
            Artisan::call('db:seed', [
                '--class' => $configuredSeeder,
                '--force' => true,
            ]);

            $this->line(Artisan::output());

            return;
        }

        $this->seedInstructionItemsFromPricingConfig();
    }

    protected function seedInstructionItemsFromPricingConfig(): void
    {
        $items = (array) config('x-change.pricelist', []);

        if ($items === []) {
            $this->warn('No x-change.pricelist config found; instruction_items not seeded.');

            return;
        }

        foreach ($items as $index => $data) {
            if (! is_array($data)) {
                continue;
            }

            $meta = [
                'description' => $data['description'] ?? null,
                'label' => $data['label'] ?? null,
                'category' => $data['category'] ?? 'other',
            ];

            if (! empty($data['deprecated'])) {
                $meta['deprecated'] = true;
                $meta['deprecated_reason'] = $data['deprecated_reason'] ?? 'No longer in use';
            }

            DB::table('instruction_items')->updateOrInsert(
                ['index' => $index],
                [
                    'name' => $this->inferInstructionItemName($index, $data),
                    'type' => $this->inferInstructionItemType($index, $data),
                    'price' => (int) ($data['price'] ?? 0), // stored in minor units
                    'currency' => (string) ($data['currency'] ?? 'PHP'),
                    'meta' => json_encode($meta, JSON_UNESCAPED_SLASHES),
                    'revenue_destination_type' => null,
                    'revenue_destination_id' => null,
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
        }

        $this->info('Instruction items seeded from x-change.pricelist config.');
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
