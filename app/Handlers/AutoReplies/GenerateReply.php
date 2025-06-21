<?php

namespace App\Handlers\AutoReplies;

use App\Actions\CutCheck;
use App\Services\InstructionParser;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use LBHurtado\Voucher\Actions\GenerateVouchers;
use LBHurtado\Wallet\Services\SystemUserResolverService;
use LBHurtado\OmniChannel\Contracts\AutoReplyInterface;
use App\Models\User;


class GenerateReply implements AutoReplyInterface
{
    public function reply(string $from, string $to, string $message): string
    {
        $user = User::findByMobile($from);
        Auth::setUser($user);

        // 4) generate
        $vouchers = CutCheck::run('generate ' . $message);

        if ($vouchers->isEmpty()) {
            return "ℹ️ No vouchers were generated.";
        }

        // 5) build SMS summary (one line per voucher)
        $lines = $vouchers->map(function ($v) {
            $code   = $v->code;
            $amount = (string) $v->instructions->cash->getAmount();
            $exp    = $v->expires_at?->toDateTimeString() ?? '—';
            return "{$code} ({$amount}, expires {$exp})";
        });

        // If too many, just top 5 and say “…and X more”
        if ($vouchers->count() > 5) {
            $rest = $vouchers->count() - 5;
            $lines = $lines->slice(0, 5);
            $lines->push("…and {$rest} more vouchers");
        }

        // join with line-breaks (SMS will generally collapse them into spaces)
        return "🎉 Generated {$vouchers->count()} voucher(s):\n"
            . $lines->implode("\n");
    }
}
