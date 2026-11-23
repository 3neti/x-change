<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class BankAccountAssignment extends Model
{
    protected $table = 'xchange_bank_account_assignments';

    protected $guarded = [];

    protected $hidden = ['account_fingerprint', 'details_ciphertext'];

    protected function casts(): array
    {
        return ['details_ciphertext' => 'encrypted:array'];
    }

    public function owner(): MorphTo
    {
        return $this->morphTo();
    }

    public function providerAccountLink(): BelongsTo
    {
        return $this->belongsTo(ProviderAccountLink::class);
    }
}
