<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

final class CampaignWorkflowPublication extends Model
{
    protected $table = 'x_change_campaign_workflow_publications';

    protected $fillable = [
        'endpoint_campaign_id', 'campaign_revision_id', 'draft_template_id',
        'snapshot', 'snapshot_hash', 'published_by_type', 'published_by_id', 'published_at',
    ];

    protected $hidden = ['snapshot'];

    protected static function booted(): void
    {
        self::creating(function (self $publication): void {
            $publication->reference ??= (string) Str::ulid();
        });
        self::updating(function (): never {
            throw new \LogicException('Campaign workflow publications are immutable.');
        });
        self::deleting(function (): never {
            throw new \LogicException('Campaign workflow publications cannot be deleted.');
        });
    }

    protected function casts(): array
    {
        return ['snapshot' => 'encrypted:array', 'published_at' => 'immutable_datetime'];
    }
}
