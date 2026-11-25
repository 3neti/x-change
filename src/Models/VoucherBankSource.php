<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Models;

use Illuminate\Database\Eloquent\Model;

final class VoucherBankSource extends Model
{
    protected $table = 'xchange_voucher_bank_sources';

    protected $guarded = [];

    protected $hidden = ['source_ciphertext'];

    protected function casts(): array
    {
        return ['source_ciphertext' => 'encrypted:array'];
    }
}
