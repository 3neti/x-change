<?php

declare(strict_types=1);

use LBHurtado\XChange\Services\GeoLiteLocalityLookup;
use MaxMind\Db\Reader;

it('uses a local GeoLite city result only for a public Philippine IP', function (): void {
    $path = tempnam(sys_get_temp_dir(), 'geolite-');
    $reader = new class
    {
        public function get(string $ip): array
        {
            return ['country' => ['iso_code' => 'PH'], 'city' => ['names' => ['en' => 'Cebu City']], 'subdivisions' => [['names' => ['en' => 'Central Visayas']]]];
        }

        public function close(): void {}
    };
    try {
        $lookup = new GeoLiteLocalityLookup($path, 30, fn (string $file): object => $reader);
        expect($lookup->find('8.8.8.8'))->toBe(['city' => 'Cebu City', 'region' => 'Central Visayas'])
            ->and($lookup->find('127.0.0.1'))->toBeNull()
            ->and($lookup->find('not-an-ip'))->toBeNull();
    } finally {
        unlink($path);
    }
});

it('falls back for missing, stale, foreign, and unreadable GeoLite evidence', function (): void {
    $path = tempnam(sys_get_temp_dir(), 'geolite-');
    touch($path, time() - 86400 * 40);
    try {
        expect((new GeoLiteLocalityLookup($path, 30))->find('8.8.8.8'))->toBeNull()
            ->and((new GeoLiteLocalityLookup($path.'-missing', 30))->find('8.8.8.8'))->toBeNull();
        touch($path);
        clearstatcache(true, $path);
        $foreign = new class
        {
            public function get(string $ip): array
            {
                return ['country' => ['iso_code' => 'US'], 'city' => ['names' => ['en' => 'Chicago']]];
            }

            public function close(): void {}
        };
        expect((new GeoLiteLocalityLookup($path, 30, fn (string $file): object => $foreign))->find('8.8.8.8'))->toBeNull()
            ->and((new GeoLiteLocalityLookup($path, 30, fn (string $file): object => throw new RuntimeException('unavailable')))->find('8.8.8.8'))->toBeNull();
    } finally {
        unlink($path);
    }
});

it('uses the installed reader against MaxMind’s public GeoLite test database', function (): void {
    // Apache-2.0 fixture from maxmind/MaxMind-DB test-data/GeoLite2-City-Test.mmdb.
    $path = __DIR__.'/../../Fixtures/GeoLite/GeoLite2-City-Test.mmdb';
    $reader = new Reader($path);
    $record = $reader->get('202.196.224.1');
    $reader->close();
    expect(data_get($record, 'country.iso_code'))->toBe('PH')
        ->and((new GeoLiteLocalityLookup($path, 3650))->find('202.196.224.1'))->toBeNull();
});
