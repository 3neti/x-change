<?php

declare(strict_types=1);

namespace LBHurtado\Contact\Tests\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use LBHurtado\Contact\Database\Factories\UserFactory;
use Illuminate\Notifications\Notifiable;

/**
 * Class User.
 *
 * @property int        $id
 * @property string     $name
 * @property string     $email
 *
 * @method int getKey()
 */
class User extends Authenticatable
{
    use HasFactory;
    use Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    public static function newFactory(): UserFactory
    {
        return UserFactory::new();
    }
}
