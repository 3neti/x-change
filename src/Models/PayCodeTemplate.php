<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Str;

class PayCodeTemplate extends Model
{
    protected $table = 'x_change_pay_code_templates';

    protected $fillable = [
        'owner_type',
        'owner_id',
        'name',
        'description',
        'base_template_key',
        'instructions_ciphertext',
        'include_amount',
        'include_purpose',
        'status',
    ];

    protected $hidden = [
        'instructions_ciphertext',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $template): void {
            $template->reference ??= (string) Str::ulid();
        });
    }

    protected function casts(): array
    {
        return [
            'instructions_ciphertext' => 'encrypted:array',
            'include_amount' => 'boolean',
            'include_purpose' => 'boolean',
        ];
    }

    public function owner(): MorphTo
    {
        return $this->morphTo();
    }
}
