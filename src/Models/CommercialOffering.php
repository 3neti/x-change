<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use LBHurtado\XChange\Enums\CommercialOfferingOrigin;
use LBHurtado\XChange\Enums\CommercialOfferingStatus;
use LBHurtado\XCommerce\Data\CommercialOfferingData;

final class CommercialOffering extends Model
{
    protected $table = 'x_change_commercial_offerings';

    protected $fillable = [
        'reference',
        'version',
        'profile',
        'status',
        'origin',
        'currency',
        'snapshot_hash',
        'snapshot',
        'source_package',
        'source_package_version',
        'commissioning_manifest_reference',
        'created_by_type',
        'created_by_id',
        'submitted_by_type',
        'submitted_by_id',
        'approved_by_type',
        'approved_by_id',
        'authorization_reference',
        'effective_at',
        'submitted_at',
        'approved_at',
        'retired_at',
    ];

    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'status' => CommercialOfferingStatus::class,
            'origin' => CommercialOfferingOrigin::class,
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
