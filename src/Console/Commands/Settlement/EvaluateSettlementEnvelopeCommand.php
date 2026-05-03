<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Console\Commands\Settlement;

use Illuminate\Console\Command;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Contracts\SettlementEnvelopeReadinessContract;

class EvaluateSettlementEnvelopeCommand extends Command
{
    protected $signature = 'xchange:settlement-envelope:evaluate
        {voucher_code : Voucher / Pay Code to evaluate}
        {--driver=philhealth-bst : Settlement envelope driver}
        {--gate=settleable : Settlement gate to evaluate}
        {--json : Output JSON}';

    protected $description = 'Evaluate settlement envelope readiness for a voucher.';

    public function handle(SettlementEnvelopeReadinessContract $readiness): int
    {
        $code = (string) $this->argument('voucher_code');

        $voucher = Voucher::query()
            ->where('code', $code)
            ->first();

        if (! $voucher) {
            $this->error("Voucher [{$code}] was not found.");

            return self::FAILURE;
        }

        $result = $readiness->evaluate(
            voucher: $voucher,
            gate: (string) $this->option('gate'),
            context: [
                'driver' => (string) $this->option('driver'),
            ],
        );

        $payload = [
            'voucher_code' => $voucher->code ?? $code,
            'driver' => $result->driver,
            'gate' => $result->gate,
            'required' => $result->required,
            'exists' => $result->exists,
            'ready' => $result->ready,
            'satisfied' => $result->satisfied,
            'missing' => $result->missing,
            'failed' => $result->failed,
            'warnings' => $result->warnings,
            'checklist' => $result->checklist,
            'meta' => $result->meta,
        ];

        if ($this->option('json')) {
            $this->line(json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return $result->ready ? self::SUCCESS : self::FAILURE;
        }

        $this->components->info($result->ready
            ? 'Settlement envelope is ready.'
            : 'Settlement envelope is not ready.');

        $this->line('Driver: '.$result->driver);
        $this->line('Gate: '.$result->gate);

        if ($result->missing !== []) {
            $this->warn('Missing: '.implode(', ', $result->missing));
        }

        return $result->ready ? self::SUCCESS : self::FAILURE;
    }
}
