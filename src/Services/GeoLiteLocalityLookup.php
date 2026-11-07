<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services;

use Closure;
use MaxMind\Db\Reader;
use Throwable;

final readonly class GeoLiteLocalityLookup
{
    private Closure $readerFactory;

    public function __construct(private string $databasePath, private int $maxAgeDays = 30, ?Closure $readerFactory = null)
    {
        $this->readerFactory = $readerFactory ?? static fn (string $path): Reader => new Reader($path);
    }

    /** @return array{city: string, region: ?string}|null */
    public function find(?string $ip): ?array
    {
        if ($ip === null || filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false
            || ! is_file($this->databasePath) || ! is_readable($this->databasePath)) {
            return null;
        }
        $modified = filemtime($this->databasePath);
        if ($modified === false || $modified < time() - max(1, $this->maxAgeDays) * 86400) {
            return null;
        }
        $reader = null;
        try {
            $reader = ($this->readerFactory)($this->databasePath);
            $result = $reader->get($ip);
            $city = data_get($result, 'city.names.en');
            if (data_get($result, 'country.iso_code') !== 'PH' || ! is_string($city) || trim($city) === '') {
                return null;
            }
            $region = data_get($result, 'subdivisions.0.names.en');

            return ['city' => trim($city), 'region' => is_string($region) && trim($region) !== '' ? trim($region) : null];
        } catch (Throwable) {
            return null;
        } finally {
            if ($reader !== null) {
                try {
                    $reader->close();
                } catch (Throwable) {
                }
            }
        }
    }
}
