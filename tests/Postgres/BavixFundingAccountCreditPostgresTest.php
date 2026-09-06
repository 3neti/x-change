<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Tests\Postgres;

use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Str;
use LBHurtado\XChange\Exceptions\FundingSettlementDenied;
use LBHurtado\XChange\Services\Funding\BavixFundingAccountCredit;
use PHPUnit\Framework\TestCase;

final class BavixFundingAccountCreditPostgresTest extends TestCase
{
    private Capsule $database;

    protected function setUp(): void
    {
        parent::setUp();

        if (getenv('X_CHANGE_POSTGRES_TESTS') !== '1') {
            self::markTestSkipped('Set X_CHANGE_POSTGRES_TESTS=1 to run PostgreSQL integration tests.');
        }

        $container = new Container;
        Container::setInstance($container);
        $container->instance('config', new Repository([
            'wallet' => [
                'wallet' => [
                    'model' => PostgresTestWallet::class,
                    'table' => 'x_change_test_wallets',
                ],
            ],
        ]));
        $this->database = new Capsule($container);
        $this->database->addConnection([
            'driver' => 'pgsql',
            'host' => (string) getenv('X_CHANGE_TEST_DB_HOST'),
            'port' => (int) getenv('X_CHANGE_TEST_DB_PORT'),
            'database' => (string) getenv('X_CHANGE_TEST_DB_DATABASE'),
            'username' => (string) getenv('X_CHANGE_TEST_DB_USERNAME'),
            'password' => (string) getenv('X_CHANGE_TEST_DB_PASSWORD'),
            'charset' => 'utf8',
            'prefix' => '',
            'search_path' => 'public',
            'sslmode' => 'prefer',
        ]);
        $this->database->setAsGlobal();
        $this->database->bootEloquent();
        $this->database->schema()->dropIfExists('x_change_test_wallets');
        $this->database->schema()->create('x_change_test_wallets', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->uuid('uuid')->unique();
            $table->string('holder_type');
            $table->unsignedBigInteger('holder_id');
            $table->string('name');
            $table->string('slug');
            $table->bigInteger('balance')->default(0);
            $table->unsignedTinyInteger('decimal_places')->default(2);
            $table->json('meta')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    protected function tearDown(): void
    {
        if (isset($this->database)) {
            $this->database->schema()->dropIfExists('x_change_test_wallets');
        }

        Container::setInstance(null);
        parent::tearDown();
    }

    public function test_numeric_and_uuid_wallet_references_are_resolved_without_uuid_coercion(): void
    {
        $uuid = (string) Str::uuid();
        $walletId = $this->database->table('x_change_test_wallets')->insertGetId([
            'uuid' => $uuid,
            'holder_type' => 'test-user',
            'holder_id' => 1,
            'name' => 'Platform',
            'slug' => 'platform',
            'balance' => 0,
            'decimal_places' => 2,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $wallet = PostgresTestWallet::query()->findOrFail($walletId);
        $credit = new BavixFundingAccountCredit;

        self::assertTrue($credit->resolve('wallet:'.$wallet->getKey())->is($wallet));
        self::assertTrue($credit->resolve('wallet:'.$uuid)->is($wallet));

        $this->expectException(FundingSettlementDenied::class);
        $credit->resolve('wallet:not-a-valid-uuid');
    }
}

final class PostgresTestWallet extends Model
{
    protected $table = 'x_change_test_wallets';

    protected $guarded = [];

    public function deposit(int $amount, array $metadata = [], bool $confirmed = true): object
    {
        return (object) compact('amount', 'metadata', 'confirmed');
    }
}
