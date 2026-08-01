<?php

declare(strict_types=1);

use Illuminate\Filesystem\Filesystem;
use LBHurtado\XChange\Services\Configuration\LocalEnvironmentFileWriter;

it('creates a local environment from the host example and generates an application key', function (): void {
    $directory = sys_get_temp_dir().'/xchange-env-'.bin2hex(random_bytes(6));
    mkdir($directory);
    $example = $directory.'/.env.example';
    $environment = $directory.'/.env';
    file_put_contents($example, "APP_NAME=Laravel\nAPP_KEY=\nHOST_VALUE=preserved\n");

    try {
        $result = (new LocalEnvironmentFileWriter(new Filesystem))->write(
            $environment,
            $example,
            [
                'APP_NAME' => 'x-PayOut',
                'APP_URL' => 'http://x-payout.test',
                'XCHANGE_DEPLOYMENT_PROFILE' => 'development',
            ],
        );
        $contents = file_get_contents($environment);

        expect($result['created'])->toBeTrue()
            ->and($result['application_key_generated'])->toBeTrue()
            ->and($contents)->toContain(
                'APP_NAME=x-PayOut',
                'APP_URL=http://x-payout.test',
                'XCHANGE_DEPLOYMENT_PROFILE=development',
                'HOST_VALUE=preserved',
            )->toMatch('/^APP_KEY="?base64:[A-Za-z0-9+\/=]+"?$/m');
    } finally {
        array_map('unlink', glob($directory.'/.env*') ?: []);
        @rmdir($directory);
    }
});

it('backs up an existing environment and never replaces its application key', function (): void {
    $directory = sys_get_temp_dir().'/xchange-env-'.bin2hex(random_bytes(6));
    mkdir($directory);
    $example = $directory.'/.env.example';
    $environment = $directory.'/.env';
    file_put_contents($example, "APP_KEY=\n");
    file_put_contents($environment, "APP_NAME=Old\nAPP_KEY=base64:stable-key\nSECRET=preserved\n");

    try {
        $result = (new LocalEnvironmentFileWriter(new Filesystem))->write(
            $environment,
            $example,
            ['APP_NAME' => 'x-PayOut'],
        );
        $contents = file_get_contents($environment);

        expect($result['backup_path'])->not->toBeNull()
            ->and(file_get_contents($result['backup_path']))->toContain('APP_NAME=Old')
            ->and($contents)->toContain(
                'APP_NAME=x-PayOut',
                'APP_KEY=base64:stable-key',
                'SECRET=preserved',
            );
    } finally {
        foreach (glob($directory.'/.env*') ?: [] as $file) {
            @unlink($file);
        }
        @rmdir($directory);
    }
});
