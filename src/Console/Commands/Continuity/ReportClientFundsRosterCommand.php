<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Console\Commands\Continuity;

use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use LBHurtado\XChange\Actions\Continuity\BuildClientFundsRoster;
use LBHurtado\XChange\Data\Continuity\ClientFundsRosterData;
use LBHurtado\XChange\Data\Continuity\ClientFundsRosterRowData;
use LBHurtado\XChange\Services\Keepsake\CanonicalKeepsakeJson;
use Throwable;

final class ReportClientFundsRosterCommand extends Command
{
    protected $signature = 'x-change:continuity:client-funds-roster
        {--connection= : Exact active Treasury connection reference}
        {--authorization-reference= : External approval or ticket reference for this sensitive report}
        {--confirm-sensitive-output : Confirm that full names and mobile numbers may be written to this terminal}';

    protected $description = 'Privately report each Account name, mobile number, and persisted Client Funds';

    protected $help = <<<'HELP'
Emit a private operator roster containing full names, full mobile numbers, and
persisted Client Funds for one exact active Treasury connection. This command is
read-only and makes no provider call, but its output contains personal data.

Both --authorization-reference and --confirm-sensitive-output are mandatory.
The authorization reference is recorded in the report checksum but does not
authenticate the terminal operator; host access and the external approval remain
the operator authorization boundary. Do not paste the output into chat or logs.
HELP;

    protected function configure(): void
    {
        parent::configure();

        $this->setHelp($this->help);
    }

    public function handle(
        BuildClientFundsRoster $builder,
        CanonicalKeepsakeJson $json,
    ): int {
        $connectionReference = trim((string) $this->option('connection'));
        $authorizationReference = trim((string) $this->option('authorization-reference'));

        if ($connectionReference === '') {
            $this->components->error('An exact --connection is required.');

            return self::FAILURE;
        }

        if ($authorizationReference === '' || mb_strlen($authorizationReference) > 191) {
            $this->components->error('A valid --authorization-reference is required.');

            return self::FAILURE;
        }

        if (! (bool) $this->option('confirm-sensitive-output')) {
            $this->components->error('Use --confirm-sensitive-output to acknowledge that this report contains personal data.');

            return self::FAILURE;
        }

        try {
            $report = $builder->handle(
                $connectionReference,
                $authorizationReference,
                CarbonImmutable::now('UTC'),
            );
        } catch (Throwable $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->components->warn('PRIVATE REPORT — contains full names and mobile numbers.');
        $this->line('As of: '.$report->asOf->toIso8601String());
        $this->line('Instance: '.($report->instanceId ?? 'not configured'));
        $this->line(sprintf(
            'Connection: %s (%s / %s)',
            $report->connectionReference,
            $report->provider,
            $report->currency,
        ));
        $this->line('Authorization reference: '.$report->authorizationReference);
        $this->newLine();
        $this->table(
            ['Name', 'Mobile Number', 'Client Funds'],
            array_map(
                fn (ClientFundsRosterRowData $row): array => [
                    $row->name,
                    $row->mobile,
                    $this->formatMinor($row->amountMinor, $report),
                ],
                $report->rows,
            ),
        );
        $this->line('Roster SHA-256: '.$json->hash($this->checksumPayload($report)));
        $this->line('Evidence only. Client Funds are persisted X-Change positions; this report does not contact a provider or move money.');

        return self::SUCCESS;
    }

    private function formatMinor(int $amountMinor, ClientFundsRosterData $report): string
    {
        $divisor = 10 ** $report->decimalPlaces;
        $amount = number_format($amountMinor / $divisor, $report->decimalPlaces);

        return $report->currency === 'PHP'
            ? '₱'.$amount
            : $report->currency.' '.$amount;
    }

    /** @return array<string, mixed> */
    private function checksumPayload(ClientFundsRosterData $report): array
    {
        return [
            'schema' => 'x-change.private-client-funds-roster.v1',
            'as_of' => $report->asOf->toIso8601String(),
            'instance_id' => $report->instanceId,
            'connection_reference' => $report->connectionReference,
            'provider' => $report->provider,
            'currency' => $report->currency,
            'decimal_places' => $report->decimalPlaces,
            'authorization_reference' => $report->authorizationReference,
            'rows' => array_map(
                static fn (ClientFundsRosterRowData $row): array => [
                    'name' => $row->name,
                    'mobile' => $row->mobile,
                    'amount_minor' => $row->amountMinor,
                ],
                $report->rows,
            ),
        ];
    }
}
