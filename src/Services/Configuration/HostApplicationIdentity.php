<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Configuration;

use Illuminate\Support\Str;

final readonly class HostApplicationIdentity
{
    /**
     * @return array{display_name: string, slug: string}
     */
    public function resolve(?string $displayName = null): array
    {
        $displayName = trim($displayName ?? (string) config('app.name'));

        if ($displayName === '' || mb_strtolower($displayName) === 'laravel') {
            $directoryName = basename(base_path());
            $displayName = str_starts_with($directoryName, 'x-')
                ? $directoryName
                : 'x-'.Str::headline($directoryName);
        }

        if (str_starts_with(mb_strtolower($displayName), 'x-')) {
            $displayName = 'x-'.mb_substr($displayName, 2);
        } else {
            $displayName = 'x-'.$displayName;
        }

        return [
            'display_name' => $displayName,
            'slug' => Str::slug($displayName),
        ];
    }
}
