<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use LBHurtado\XChange\Enums\TreasuryInstitutionFundClassificationStatus;

final class TreasuryInstitutionFundClassification extends Model
{
    protected $table = 'x_change_treasury_institution_fund_classifications';

    protected $fillable = [
        'reference', 'status', 'evidence_operation_reference', 'evidence_reference',
        'amount_minor', 'currency', 'connection_reference', 'ownership_basis',
        'request_hash', 'idempotency_reference_hash', 'maker_type', 'maker_id',
        'submitted_at', 'checker_type', 'checker_id', 'approved_at',
        'source_position_reference', 'destination_position_reference',
        'operation_reference', 'executed_at',
    ];

    protected $attributes = [
        'status' => 'awaiting_approval',
        'currency' => 'PHP',
    ];

    protected function casts(): array
    {
        return [
            'status' => TreasuryInstitutionFundClassificationStatus::class,
            'amount_minor' => 'integer',
            'submitted_at' => 'immutable_datetime',
            'approved_at' => 'immutable_datetime',
            'executed_at' => 'immutable_datetime',
        ];
    }

    public function maker(): MorphTo
    {
        return $this->morphTo();
    }

    public function checker(): MorphTo
    {
        return $this->morphTo();
    }
}
