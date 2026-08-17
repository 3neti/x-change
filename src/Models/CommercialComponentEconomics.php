<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LBHurtado\XChange\Casts\UtcImmutableDateTime;
use LBHurtado\XChange\Enums\CommercialActivationAuthority;
use LBHurtado\XChange\Enums\CommercialOfferingOrigin;
use LBHurtado\XCommerce\Data\CommercialComponentEconomicsSetData;

final class CommercialComponentEconomics extends Model
{
    protected $table = 'x_change_commercial_component_economics_manifests';

    protected $fillable = [
        'reference', 'version', 'profile', 'origin', 'authority',
        'commercial_offering_id', 'offering_reference', 'offering_version',
        'offering_snapshot_hash', 'offering_manifest_hash', 'currency',
        'snapshot_hash', 'snapshot', 'artifact_schema', 'artifact_hash', 'artifact_yaml',
        'source_package', 'source_package_version', 'commissioning_manifest_reference',
        'effective_at',
    ];

    protected $attributes = ['origin' => 'installation_baseline'];

    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'offering_version' => 'integer',
            'origin' => CommercialOfferingOrigin::class,
            'authority' => CommercialActivationAuthority::class,
            'snapshot' => 'array',
            'effective_at' => UtcImmutableDateTime::class,
        ];
    }

    public function offering(): BelongsTo
    {
        return $this->belongsTo(CommercialOffering::class, 'commercial_offering_id');
    }

    public function activations(): HasMany
    {
        return $this->hasMany(CommercialComponentEconomicsActivation::class, 'commercial_component_economics_id');
    }

    public function economics(): CommercialComponentEconomicsSetData
    {
        return CommercialComponentEconomicsSetData::fromArray((array) $this->snapshot);
    }
}
