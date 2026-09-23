<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use LBHurtado\SettlementEnvelope\Models\Envelope;
use LBHurtado\SettlementEnvelope\Models\EnvelopePayloadVersion;

final class CompletionClaimEvidenceProjection extends Model
{
    protected $table = 'x_change_completion_claim_evidence_projections';

    protected $fillable = ['reference', 'completion_pay_code_issuance_id', 'voucher_claim_id', 'envelope_id', 'envelope_payload_version_id', 'manifest_hash', 'projection_hash', 'source_snapshot', 'projected_at'];

    protected $hidden = ['manifest_hash', 'projection_hash', 'source_snapshot'];

    protected static function booted(): void
    {
        self::creating(function (self $projection): void {
            $projection->reference ??= (string) Str::ulid();
        });
        self::updating(fn (): never => throw new \LogicException('Completion claim evidence projections are immutable.'));
        self::deleting(fn (): never => throw new \LogicException('Completion claim evidence projections cannot be deleted.'));
    }

    protected function casts(): array
    {
        return ['source_snapshot' => 'encrypted:array', 'projected_at' => 'immutable_datetime'];
    }

    public function issuance(): BelongsTo
    {
        return $this->belongsTo(CompletionPayCodeIssuance::class, 'completion_pay_code_issuance_id');
    }

    public function claim(): BelongsTo
    {
        return $this->belongsTo(VoucherClaim::class, 'voucher_claim_id');
    }

    public function envelope(): BelongsTo
    {
        return $this->belongsTo(Envelope::class);
    }

    public function payloadVersion(): BelongsTo
    {
        return $this->belongsTo(EnvelopePayloadVersion::class, 'envelope_payload_version_id');
    }
}
