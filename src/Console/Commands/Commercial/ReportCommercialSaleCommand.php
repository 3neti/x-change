<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Console\Commands\Commercial;

use Illuminate\Console\Command;
use LBHurtado\XChange\Models\CommercialSale;
use LBHurtado\XChange\Services\Commercial\CommercialSaleEvidenceReport;

final class ReportCommercialSaleCommand extends Command
{
    protected $signature = 'x-change:commercial:sale-report
        {reference : Commercial Sale reference}
        {--json : Output canonical JSON}';

    protected $description = 'Verify a Commercial Sale confirmation, allocations, and invoice-authority status.';

    public function handle(CommercialSaleEvidenceReport $reports): int
    {
        $sale = CommercialSale::query()
            ->where('reference', trim((string) $this->argument('reference')))
            ->firstOrFail();
        $report = $reports->build($sale);

        if ((bool) $this->option('json')) {
            $this->line(json_encode($report, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } else {
            $this->components->info('Commercial Sale Evidence Report');
            $this->line('Reference: '.$report['sale']['reference']);
            $this->line('Status: '.$report['sale']['status']);
            $this->line('Commercial charge minor: '.$report['amounts']['commercial_charge_minor']);
            $this->line('Principal minor: '.$report['amounts']['principal_minor'].' (reported separately)');
            $this->line('Allocations reconcile: '.($report['controls']['allocations_reconcile'] ? 'yes' : 'no'));
            $this->line('Evidence verified: '.($report['controls']['report_verified'] ? 'yes' : 'no'));
            $this->line('Invoice status: '.$report['invoice']['status']);
            $this->warn('This evidence report is not a tax invoice.');
        }

        return $report['controls']['report_verified'] ? self::SUCCESS : self::FAILURE;
    }
}
