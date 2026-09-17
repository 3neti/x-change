<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use LBHurtado\Voucher\Models\Voucher;

class CampaignDisplaySession extends Model
{
    protected $table = 'x_change_campaign_display_sessions';

    protected $fillable = ['lead_campaign_id', 'token_hash', 'entry_token', 'browser_hash', 'voucher_id', 'payment_attempt_id', 'expires_at', 'ended_at', 'intake_completed_at'];

    protected $hidden = ['entry_token', 'token_hash', 'browser_hash'];

    protected static function booted(): void
    {
        static::creating(function (self $session): void {
            $session->reference ??= (string) Str::ulid();
        });
    }

    protected function casts(): array
    {
        return ['entry_token' => 'encrypted', 'expires_at' => 'immutable_datetime', 'ended_at' => 'immutable_datetime', 'intake_completed_at' => 'immutable_datetime'];
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(LeadCampaign::class, 'lead_campaign_id');
    }

    public function voucher(): BelongsTo
    {
        return $this->belongsTo(Voucher::class);
    }

    public function attempt(): BelongsTo
    {
        return $this->belongsTo(PaymentAttempt::class, 'payment_attempt_id');
    }
}
