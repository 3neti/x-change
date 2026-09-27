<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Models;

use Illuminate\Database\Eloquent\Model;

final class DeferredOnboardingFunding extends Model
{
    protected $table = 'x_change_deferred_onboarding_fundings';

    protected $fillable = [
        'reference',
        'voucher_id',
        'subject_type',
        'subject_id',
        'required_agreement_key',
        'required_agreement_version',
        'required_agreement_sha256',
        'amount_minor',
        'currency',
        'connection_reference',
        'reservation_operation_reference',
        'status',
        'agreement_acceptance_id',
        'voucher_claim_id',
        'treasury_operation_reference',
        'deferred_at',
        'released_at',
    ];

    protected function casts(): array
    {
        return [
            'amount_minor' => 'integer',
            'deferred_at' => 'immutable_datetime',
            'released_at' => 'immutable_datetime',
        ];
    }
}
