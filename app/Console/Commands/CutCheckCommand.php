<?php

namespace App\Console\Commands;

use App\Actions\CutCheck;
use App\Exceptions\InstructionParseException;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use LBHurtado\Wallet\Services\SystemUserResolverService;
use Throwable;

class CutCheckCommand extends Command
{
    protected $signature = 'voucher:cut-check
                            {text? : The voucher instructions (free-form text)}
                            {--no-prompt : Don’t prompt if the text argument is missing}';

    protected $description = 'Parse a free-form text into voucher instructions and generate the vouchers';

    public function handle(CutCheck $cutCheck)
    {
        Log::debug('[voucher:cut-check] Starting command');

        // 1️⃣ Grab & “log in” your system user
        $system = app(SystemUserResolverService::class)->resolve();
        Auth::setUser($system);
        Log::debug('[voucher:cut-check] Authenticated as system user', [
            'system_user_id' => $system->getKey(),
            'system_user_class' => get_class($system),
        ]);

        // 2️⃣ Get the instruction text
        $text = $this->argument('text');
        if (! $text && ! $this->option('no-prompt')) {
            $text = $this->ask('Please paste your voucher-creation instructions');
        }

        if (! $text) {
            $this->error('No instruction text provided. Aborting.');
            Log::warning('[voucher:cut-check] Aborting: no text supplied');
            return 1;
        }

        $this->info("Parsing instructions…");
        Log::debug('[voucher:cut-check] Raw instruction text', ['text' => $text]);

        try {
            $vouchers = $cutCheck->run($text);

            Log::debug('[voucher:cut-check] Parser returned', [
                'vouchers_count' => $vouchers->count(),
                'first_codes'    => $vouchers->pluck('code')->take(5),
            ]);

            if ($vouchers->isEmpty()) {
                $this->warn('No vouchers were generated.');
                return 0;
            }

            $this->info('🎉 Generated ' . $vouchers->count() . ' voucher(s):');

            $rows = $vouchers
                ->map(fn($v) => [
                    'Code'       => $v->code,
                    'Amount'     => (string) $v->instructions->cash->getAmount(),
                    'Expires At' => $v->expires_at?->toDateTimeString() ?? '—',
                ])
                ->all();

            $this->table(['Code', 'Amount', 'Expires At'], $rows);

            Log::info('[voucher:cut-check] Completed successfully', [
                'count' => $vouchers->count(),
            ]);

            return 0;

        } catch (InstructionParseException $e) {
            $this->error('Failed to parse instructions: ' . $e->getMessage());
            Log::error('[voucher:cut-check] InstructionParseException', [
                'message' => $e->getMessage(),
                'text'    => $text,
                'exception' => $e,
            ]);
            return 2;

        } catch (Throwable $e) {
            $this->error('An unexpected error occurred: ' . $e->getMessage());
            Log::error('[voucher:cut-check] Unexpected exception', [
                'message'   => $e->getMessage(),
                'exception' => $e,
            ]);
            return 3;
        }
    }
}
