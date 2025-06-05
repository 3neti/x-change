<?php

use LBHurtado\PaymentGateway\Support\BankRegistry;
use Illuminate\Support\Collection;

//beforeEach(function () {
//    // Define the mock file path using the helper
//    $this->banksPath = documents_path('banks.json');
//
//    // Write temporary banks.json file with the provided data
//    file_put_contents($this->banksPath, json_encode([
//        'banks' => [
//            "AGBUPHM1XXX" => [
//                "full_name" => "AGRIBUSINESS RURAL BANK, INC.",
//                "swift_bic" => "AGBUPHM1XXX",
//                "settlement_rail" => [
//                    "PESONET" => [
//                        "bank_code" => "AGBUPHM1XXX",
//                        "name" => "PESONET"
//                    ]
//                ]
//            ],
//            "AIIPPHM1XXX" => [
//                "full_name" => "AL-AMANAH ISLAMIC BANK",
//                "swift_bic" => "AIIPPHM1XXX",
//                "settlement_rail" => [
//                    "PESONET" => [
//                        "bank_code" => "AIIPPHM1XXX",
//                        "name" => "PESONET"
//                    ]
//                ]
//            ],
//            "ALKBPHM2XXX" => [
//                "full_name" => "ALLBANK, INC.",
//                "swift_bic" => "ALKBPHM2XXX",
//                "settlement_rail" => [
//                    "PESONET" => [
//                        "bank_code" => "ALKBPHM2XXX",
//                        "name" => "PESONET"
//                    ]
//                ]
//            ],
//        ],
//    ]));
//});

//afterEach(function () {
//    // Remove the mock banks.json file using the helper path
//    if (file_exists($this->banksPath)) {
//        unlink($this->banksPath);
//    }
//});

it('validates that the banks.json file exists and the BankRegistry loads it correctly', function () {
    // Path to the banks.json file using the helper
    $path = documents_path('banks.json');

    // Check that the file exists
    expect(file_exists($path))->toBeTrue();

    // Instantiate the BankRegistry (should not throw an exception)
    $bankRegistry = new BankRegistry();

    // Validate all() returns data
    $allBanks = $bankRegistry->all();

    expect($allBanks)->toBeArray()->not->toBeEmpty();
});

//it('throws an exception if the file format is invalid', function () {
//    // Overwrite banks.json with invalid data
//    file_put_contents($this->banksPath, json_encode([]));
//
//    $this->expectException(UnexpectedValueException::class);
//    $this->expectExceptionMessage("Invalid format in banks.json. Expected 'banks' root key.");
//
//    (new BankRegistry());
//});

it('returns all banks using the all() method', function () {
    $bankRegistry = new BankRegistry();

    $allBanks = $bankRegistry->all();

    expect($allBanks)
        ->toBeArray()
        ->toHaveCount(146)
        ->and($allBanks['AGBUPHM1XXX']['full_name'])
        ->toBe('AGRIBUSINESS RURAL BANK, INC.')
        ->and($allBanks['AIIPPHM1XXX']['full_name'])
        ->toBe('AL-AMANAH ISLAMIC BANK')
        ->and($allBanks['ALKBPHM2XXX']['full_name'])
        ->toBe('ALLBANK, INC.');
});

it('finds a bank by swift_bic using the find() method', function () {
    $bankRegistry = new BankRegistry();

    // Test existing bank
    $bank = $bankRegistry->find('AGBUPHM1XXX');
    expect($bank)
        ->toBeArray()
        ->and($bank['full_name'])
        ->toBe('AGRIBUSINESS RURAL BANK, INC.');

    // Test nonexistent bank
    expect($bankRegistry->find('NOT_EXISTENT'))->toBeNull();
});

it('returns supported settlement rails for a bank', function () {
    $bankRegistry = new BankRegistry();

    // Test settlement rail for an existing bank
    $rails = $bankRegistry->supportedSettlementRails('AGBUPHM1XXX');
    expect($rails)
        ->toBeArray()
        ->and($rails['PESONET']['bank_code'])
        ->toBe('AGBUPHM1XXX')
        ->and($rails['PESONET']['name'])
        ->toBe('PESONET');

    // Test settlement rails for a nonexistent bank
    $rails = $bankRegistry->supportedSettlementRails('NOT_EXISTENT');
    expect($rails)->toBeArray()->toBeEmpty();
});

it('returns a collection using the toCollection() method', function () {
    $bankRegistry = new BankRegistry();

    $collection = $bankRegistry->toCollection();

    expect($collection)
        ->toBeInstanceOf(Collection::class)
        ->toHaveCount(146)
        ->and($collection->get('AGBUPHM1XXX')['full_name'])
        ->toBe('AGRIBUSINESS RURAL BANK, INC.');
});
