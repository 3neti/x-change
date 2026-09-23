<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use LBHurtado\SettlementEnvelope\Models\Envelope;
use LBHurtado\Voucher\Models\Voucher;

final class CompletionPayCodeIssuance extends Model
{
    protected $table = 'x_change_completion_pay_code_issuances';

    protected $fillable = ['reference', 'issuance_key', 'instruction_hash', 'provisional_coverage_id', 'envelope_id', 'voucher_id', 'issuer_type', 'issuer_id', 'driver_id', 'driver_version', 'requirements_snapshot', 'issued_at'];

    protected $hidden = ['issuance_key', 'instruction_hash', 'requirements_snapshot'];

    protected static function booted(): void
    {
        self::creating(function (self $issuance): void {
            $issuance->reference ??= (string) Str::ulid();
        });
        self::updating(fn (): never => throw new \LogicException('Completion Pay Code issuances are immutable.'));
        self::deleting(fn (): never => throw new \LogicException('Completion Pay Code issuances cannot be deleted.'));
    }

    protected function casts(): array
    {
        return ['requirements_snapshot' => 'array', 'issued_at' => 'immutable_datetime'];
    }

    public function coverage(): BelongsTo
    {
        return $this->belongsTo(ProvisionalCoverage::class, 'provisional_coverage_id');
    }

    public function envelope(): BelongsTo
    {
        return $this->belongsTo(Envelope::class);
    }

    public function voucher(): BelongsTo
    {
        return $this->belongsTo(Voucher::class);
    }
}
