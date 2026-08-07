<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use LBHurtado\XChange\Enums\CommercialOfferingStatus;
use LBHurtado\XCommerce\Data\CommercialOfferingData;

final class CommercialOffering extends Model
{
    protected $table = 'x_change_commercial_offerings';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'status' => CommercialOfferingStatus::class,
            'snapshot' => 'array',
            'effective_at' => 'immutable_datetime',
            'submitted_at' => 'immutable_datetime',
            'approved_at' => 'immutable_datetime',
            'retired_at' => 'immutable_datetime',
        ];
    }

    public function createdBy(): MorphTo
    {
        return $this->morphTo();
    }

    public function submittedBy(): MorphTo
    {
        return $this->morphTo();
    }

    public function approvedBy(): MorphTo
    {
        return $this->morphTo();
    }

    public function offering(): CommercialOfferingData
    {
        return CommercialOfferingData::fromArray((array) $this->snapshot);
    }
}
