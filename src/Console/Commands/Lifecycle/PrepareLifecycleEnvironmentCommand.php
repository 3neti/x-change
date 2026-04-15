<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Console\Commands\Lifecycle;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
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

        $this->line('System Wallet Balance: '.($systemUser->wallet?->balanceFloat ?? 'n/a'));
        $this->line('Test Wallet Balance: '.($testUser->wallet?->balanceFloat ?? 'n/a'));

        $this->line('Instruction Items: '.count($priceList));
        $this->newLine();

        $this->table(
            ['Index', 'Name', 'Type', 'Price', 'Currency'],
            array_map(
                fn (array $item) => [
                    $item['index'],
                    $item['name'],
                    $item['type'],
                    number_format((float) $item['price'], 2),
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

        /** @var Model $user */
        $user = $class::firstOrCreate(
            ['email' => $email],
            [
                'name' => 'System User',
                'password' => bcrypt('password'),
            ]
        );

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
        $components = (array) config('x-change.pricing.components', []);
        $baseFee = (float) config('x-change.pricing.base_fee', 0);
        $currency = (string) config('x-change.pricing.currency', 'PHP');

        if ($components === [] && $baseFee === 0.0) {
            $this->warn('No x-change pricing config found; instruction_items not seeded.');

            return;
        }

        $items = [
            'cash' => ['name' => 'Cash', 'type' => 'cash', 'price' => (float) ($components['cash'] ?? 0)],
            'kyc' => ['name' => 'KYC', 'type' => 'kyc', 'price' => (float) ($components['kyc'] ?? 0)],
            'otp' => ['name' => 'OTP', 'type' => 'otp', 'price' => (float) ($components['otp'] ?? 0)],
            'selfie' => ['name' => 'Selfie', 'type' => 'selfie', 'price' => (float) ($components['selfie'] ?? 0)],
            'signature' => ['name' => 'Signature', 'type' => 'signature', 'price' => (float) ($components['signature'] ?? 0)],
            'location' => ['name' => 'Location', 'type' => 'location', 'price' => (float) ($components['location'] ?? 0)],
            'webhook' => ['name' => 'Webhook', 'type' => 'webhook', 'price' => (float) ($components['webhook'] ?? 0)],
            'email_feedback' => ['name' => 'Email Feedback', 'type' => 'email_feedback', 'price' => (float) ($components['email_feedback'] ?? 0)],
            'sms_feedback' => ['name' => 'SMS Feedback', 'type' => 'sms_feedback', 'price' => (float) ($components['sms_feedback'] ?? 0)],
            'base_fee' => ['name' => 'Base Fee', 'type' => 'base_fee', 'price' => $baseFee],
        ];

        foreach ($items as $index => $item) {
            DB::table('instruction_items')->updateOrInsert(
                ['index' => $index],
                [
                    'name' => $item['name'],
                    'type' => $item['type'],
                    'price' => $item['price'],
                    'currency' => $currency,
                    'meta' => json_encode([], JSON_UNESCAPED_SLASHES),
                    'revenue_destination_type' => null,
                    'revenue_destination_id' => null,
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
        }

        $this->info('Instruction items seeded from x-change.pricing config.');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function lifecyclePriceList(): array
    {
        return DB::table('instruction_items')
            ->orderBy('index')
            ->get(['index', 'name', 'type', 'price', 'currency'])
            ->map(fn ($row) => [
                'index' => $row->index,
                'name' => $row->name,
                'type' => $row->type,
                'price' => (float) $row->price,
                'currency' => $row->currency,
            ])
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
}
