<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Notifications\Notifiable;
use LBHurtado\XChange\Auth\XChangeAuthenticatable;

it('adds x-change attributes without discarding host model capabilities', function (): void {
    $user = new class extends XChangeAuthenticatable
    {
        use HasFactory;
        use Notifiable;

        protected $fillable = ['name', 'email', 'password'];

        protected $hidden = ['password', 'remember_token'];

        protected function casts(): array
        {
            return [
                'email_verified_at' => 'datetime',
                'password' => 'hashed',
            ];
        }
    };

    expect($user->getFillable())
        ->toContain('name', 'email', 'password', 'mobile', 'mobile_verified_at', 'onboarding_meta')
        ->and($user->getHidden())
        ->toContain('password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes')
        ->and($user->getCasts())
        ->toHaveKeys([
            'email_verified_at',
            'password',
            'mobile_verified_at',
            'onboarding_meta',
            'two_factor_confirmed_at',
        ]);
});
