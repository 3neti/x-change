<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Models;

use Illuminate\Database\Eloquent\Model;

class BankAccountIdentity extends Model
{
    protected $table = 'xchange_bank_account_identities';

    protected $fillable = ['owner_type', 'owner_id', 'provider', 'account_fingerprint'];

    protected $hidden = ['account_fingerprint'];
}
