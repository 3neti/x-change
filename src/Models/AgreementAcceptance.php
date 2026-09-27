<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Models;

use Illuminate\Database\Eloquent\Model;
use LogicException;

final class AgreementAcceptance extends Model
{
    protected $table = 'x_change_agreement_acceptances';

    protected $fillable = [
        'reference',
        'subject_type',
        'subject_id',
        'agreement_key',
        'agreement_version',
        'agreement_sha256',
        'accepted_at',
        'onboarding_reference',
        'locale',
        'ip_address_hash',
        'user_agent_hash',
        'evidence_sha256',
    ];

    protected static function booted(): void
    {
        self::updating(static function (): never {
            throw new LogicException('Agreement acceptance evidence is immutable.');
        });

        self::deleting(static function (): never {
            throw new LogicException('Agreement acceptance evidence is immutable.');
        });
    }

    protected function casts(): array
    {
        return [
            'accepted_at' => 'immutable_datetime',
        ];
    }
}
