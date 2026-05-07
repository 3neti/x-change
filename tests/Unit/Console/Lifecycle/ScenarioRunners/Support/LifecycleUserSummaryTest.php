<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use LBHurtado\ModelChannel\Contracts\HasMobileChannel;
use LBHurtado\XChange\Lifecycle\Runners\Support\LifecycleUserSummary;

it('returns id email and mobile for a model with mobile channel', function () {
    $user = new class extends Model implements HasMobileChannel {
        protected $guarded = [];

        public $incrementing = true;

        protected $keyType = 'int';

        private ?string $mobile = '639178251991';

        public function __construct(array $attributes = [])
        {
            parent::__construct($attributes);

            $this->exists = true;
            $this->setAttribute('id', 123);
            $this->setAttribute('email', 'issuer@example.test');
        }

        public function getMobileChannel(): ?string
        {
            return $this->mobile;
        }

        public function setMobileChannel(?string $mobile): static
        {
            $this->mobile = $mobile;

            return $this;
        }

        public function hasMobileChannel(): bool
        {
            return filled($this->mobile);
        }
    };

    $result = app(LifecycleUserSummary::class)->fromModel($user);

    expect($result)->toBe([
        'id' => 123,
        'email' => 'issuer@example.test',
        'mobile' => '639178251991',
    ]);
});

it('returns null mobile for a model without mobile channel', function () {
    $user = new class extends Model {
        protected $guarded = [];

        public $incrementing = true;

        protected $keyType = 'int';

        public function __construct(array $attributes = [])
        {
            parent::__construct($attributes);

            $this->exists = true;
            $this->setAttribute('id', 456);
            $this->setAttribute('email', 'plain@example.test');
        }
    };

    $result = app(LifecycleUserSummary::class)->fromModel($user);

    expect($result)->toBe([
        'id' => 456,
        'email' => 'plain@example.test',
        'mobile' => null,
    ]);
});
