<?php

namespace LBHurtado\OmniChannel\Models;

use LBHurtado\OmniChannel\Database\Factories\SMSFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use LBHurtado\OmniChannel\Data\SMSData;

/**
 * Class SMS.
 *
 * @property int         $id
 * @property string      $from
 * @property string      $to
 * @property string      $message
 *
 *
 * @method int getKey()
 */
class SMS extends Model
{
    use HasFactory;

    protected $table = 'sms';

    protected $fillable = [
        'from',
        'to',
        'message'
    ];

    public static function newFactory(): SMSFactory
    {
        return SMSFactory::new();
    }

    public static function createFromSMSData(SMSData $SMSData): static
    {
        return static::create($SMSData->toArray());
    }
}
