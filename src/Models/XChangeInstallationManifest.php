<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Models;

use Illuminate\Database\Eloquent\Model;

class XChangeInstallationManifest extends Model
{
    protected $table = 'x_change_installation_manifests';

    protected $primaryKey = 'key';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'key',
        'manifest_version',
        'package_version',
        'profile',
        'active_connection_references',
        'configuration_fingerprint',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'manifest_version' => 'integer',
            'active_connection_references' => 'array',
            'completed_at' => 'immutable_datetime',
        ];
    }
}
