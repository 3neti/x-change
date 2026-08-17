<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use LBHurtado\XChange\Casts\UtcImmutableDateTime;
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
        'manifest_schema',
        'manifest_hash',
        'manifest_yaml',
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
            'effective_at' => UtcImmutableDateTime::class,
            'submitted_at' => UtcImmutableDateTime::class,
            'approved_at' => UtcImmutableDateTime::class,
            'retired_at' => UtcImmutableDateTime::class,
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

    public function currentActivation(): HasOne
    {
        return $this->hasOne(CommercialOfferingActivation::class, 'commercial_offering_id')
            ->whereNull('deactivated_at');
    }

    public function offering(): CommercialOfferingData
    {
        return CommercialOfferingData::fromArray((array) $this->snapshot);
    }
}
