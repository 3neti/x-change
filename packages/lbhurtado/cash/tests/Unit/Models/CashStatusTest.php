<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use LBHurtado\Cash\Enums\CashStatus;
use LBHurtado\Cash\Models\Cash;
use Spatie\ModelStatus\Status;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->cash = Cash::factory()->create(); // Assuming a Cash factory exists
});

it('can set a status using the enum', function () {
    $this->cash->setStatus(CashStatus::MINTED);

    expect($this->cash->status)->toBe(CashStatus::MINTED->value);
});

it('can set a status with a reason using the enum', function () {
    $this->cash->setStatus(CashStatus::DISBURSED, 'Funds disbursed for project A');

    $statusInstance = $this->cash->getStatusInstance();

    expect($statusInstance->name)->toBe(CashStatus::DISBURSED->value)
        ->and($statusInstance->reason)->toBe('Funds disbursed for project A');
});

it('can check the current status using the enum', function () {
    $this->cash->setStatus(CashStatus::SUSPENDED);

    expect($this->cash->hasStatus(CashStatus::SUSPENDED))->toBeTrue()
        ->and($this->cash->hasStatus(CashStatus::MINTED))->toBeFalse();
});

it('can check if it has ever had a specific status', function () {
    $this->cash->setStatus(CashStatus::MINTED);
    $this->cash->setStatus(CashStatus::DISBURSED);

    expect($this->cash->hasHadStatus(CashStatus::MINTED))->toBeTrue()
        ->and($this->cash->hasHadStatus(CashStatus::REFUNDED))->toBeFalse();
});

it('returns the current status as an enum', function () {
    $this->cash->setStatus(CashStatus::REFUNDED);

    $currentStatus = $this->cash->getCurrentStatus();

    expect($currentStatus)->toBeInstanceOf(CashStatus::class)
        ->and($currentStatus)->toBe(CashStatus::REFUNDED);
});

it('can retrieve the latest status instance', function () {
    $this->cash->setStatus(CashStatus::CANCELLED, 'Customer cancelled the transaction');

    $statusInstance = $this->cash->getStatusInstance();

    expect($statusInstance)->toBeInstanceOf(Status::class)
        ->and($statusInstance->name)->toBe(CashStatus::CANCELLED->value)
        ->and($statusInstance->reason)->toBe('Customer cancelled the transaction');
});

it('can distinguish between reversed and non-reversed statuses', function () {
    $this->cash->setStatus(CashStatus::REVERSED);
    $currentStatus = $this->cash->getCurrentStatus();

    expect($currentStatus->isReversed())->toBeTrue();
});

it('only allows valid statuses from the CashStatus enum', function () {
    $this->cash->setStatus(CashStatus::EXPIRED);

    expect(fn () => $this->cash->setStatus('invalid-status'))->toThrow(TypeError::class);
});
