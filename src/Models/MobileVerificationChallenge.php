<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Models;

use Illuminate\Database\Eloquent\Model;

final class MobileVerificationChallenge extends Model
{
    protected $table = 'x_change_mobile_verification_challenges';

    protected $fillable = [
        'reference',
        'user_type',
        'user_id',
        'mobile_hash',
        'provider',
        'status',
        'attempts',
        'expires_at',
        'verified_at',
    ];

    protected $hidden = [
        'mobile_hash',
    ];

    protected function casts(): array
    {
        return [
            'attempts' => 'integer',
            'expires_at' => 'immutable_datetime',
            'verified_at' => 'immutable_datetime',
        ];
    }
}
