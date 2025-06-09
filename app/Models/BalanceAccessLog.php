<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BalanceAccessLog extends Model
{
    protected $fillable = [
        'user_id',
        'wallet_type',
        'balance',
        'accessed_by'
    ];
}
