<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Console\Commands\Keepsake;

use Illuminate\Console\Command;
use LBHurtado\XChange\Console\Concerns\InteractsWithJsonOutput;
use LBHurtado\XChange\Services\Keepsake\InstanceKeepsakeCrypto;
use Throwable;

final class GenerateInstanceKeepsakeKeyCommand extends Command
{
    use InteractsWithJsonOutput;

    protected $signature = 'x-change:instance-keepsake:keygen
        {--private-key-file= : Local destination for the private decryption key}
        {--force : Replace an existing private key file}
        {--json : Emit a machine-readable result}
        {--pretty : Pretty-print JSON output}';

    protected $description = 'Generate a local recipient key for encrypted X-Change instance keepsakes';

    public function handle(InstanceKeepsakeCrypto $crypto): int
    {
        try {
            $path = trim((string) $this->option('private-key-file'));

            if ($path === '') {
                throw new \RuntimeException('Provide --private-key-file.');
            }

            if (file_exists($path) && ! (bool) $this->option('force')) {
                throw new \RuntimeException('The private key file already exists. Use --force to replace it.');
            }

            $directory = dirname($path);

            if (! is_dir($directory) && ! mkdir($directory, 0700, true) && ! is_dir($directory)) {
                throw new \RuntimeException('The private key directory could not be created.');
            }

            $keys = $crypto->generateKeyPair();

            if (file_put_contents($path, $keys['keypair'], LOCK_EX) === false) {
                throw new \RuntimeException('The private key file could not be written.');
            }

            chmod($path, 0600);
            $this->renderPayload([
                'schema' => 'x-change.instance-keepsake-key.v1',
                'status' => 'created',
                'private_key_file' => $path,
                'public_key' => $keys['public_key'],
                'instruction' => 'Store the public key in XCHANGE_INSTANCE_KEEPSAKE_PUBLIC_KEY. Keep the private key outside Cloud.',
            ]);

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->renderPayload([
                'schema' => 'x-change.instance-keepsake-key.v1',
                'status' => 'rejected',
                'message' => $exception->getMessage(),
            ]);

            return self::FAILURE;
        }
    }
}
