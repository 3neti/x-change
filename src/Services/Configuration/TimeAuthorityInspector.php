<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Configuration;

use Illuminate\Database\ConnectionInterface;
use Throwable;

final readonly class TimeAuthorityInspector
{
    public function __construct(private ConnectionInterface $database) {}

    /**
     * @return array{operational: bool, message: string, app_timezone: string, php_timezone: string, database_driver: string, database_timezone: string|null}
     */
    public function inspect(): array
    {
        $appTimezone = (string) config('app.timezone', 'UTC');
        $phpTimezone = date_default_timezone_get();
        $driver = $this->database->getDriverName();
        $databaseTimezone = $this->databaseTimezone($driver);
        $operational = $this->isUtc($appTimezone)
            && $this->isUtc($phpTimezone)
            && ($databaseTimezone === null || $this->isUtc($databaseTimezone));

        return [
            'operational' => $operational,
            'message' => $operational
                ? 'Laravel, PHP, and the database session share UTC time authority'
                : 'Laravel, PHP, and the database session must use UTC',
            'app_timezone' => $appTimezone,
            'php_timezone' => $phpTimezone,
            'database_driver' => $driver,
            'database_timezone' => $databaseTimezone,
        ];
    }

    private function databaseTimezone(string $driver): ?string
    {
        try {
            return match ($driver) {
                'pgsql' => (string) ($this->database->selectOne("select current_setting('TimeZone') as timezone")?->timezone ?? ''),
                'mysql', 'mariadb' => (string) ($this->database->selectOne('select @@session.time_zone as timezone')?->timezone ?? ''),
                default => null,
            };
        } catch (Throwable) {
            return '';
        }
    }

    private function isUtc(string $timezone): bool
    {
        return in_array(mb_strtoupper(trim($timezone)), [
            'UTC',
            'GMT',
            'ETC/UTC',
            'ETC/GMT',
            '+00:00',
            'Z',
        ], true);
    }
}
