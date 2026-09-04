<?php

declare(strict_types=1);

namespace Srustamov\Azericard\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Srustamov\Azericard\Options;

class GenerateKeysCommand extends Command
{
    protected $signature = 'azericard:keys
        {--path= : Directory the key files are written to}
        {--name= : File name prefix, defaults to the configured merchant name}
        {--bits=2048 : RSA key size, 2048 is the specified minimum}
        {--force : Overwrite existing key files}';

    protected $description = 'Generate the merchant RSA key pair required by Azericard';

    /** Azericard requires an effective RSA key length of at least 2048 bits. */
    private const MINIMUM_BITS = 2048;

    public function handle(): int
    {
        $bits = (int) $this->option('bits');

        if ($bits < self::MINIMUM_BITS) {
            $this->components->error(sprintf(
                'Azericard requires at least %d bits, %d given.',
                self::MINIMUM_BITS,
                $bits
            ));

            return self::FAILURE;
        }

        $directory = $this->directory();
        $prefix = $this->prefix();

        $privatePath = $directory . DIRECTORY_SEPARATOR . $prefix . '_private_key.pem';
        $publicPath = $directory . DIRECTORY_SEPARATOR . $prefix . '_public_key.pem';

        if (! $this->confirmOverwrite($privatePath, $publicPath)) {
            return self::FAILURE;
        }

        if (! is_dir($directory) && ! mkdir($directory, 0750, true) && ! is_dir($directory)) {
            $this->components->error('Unable to create directory: ' . $directory);

            return self::FAILURE;
        }

        [$private, $public] = $this->generate($bits);

        if ($private === null || $public === null) {
            $this->components->error('OpenSSL failed: ' . (openssl_error_string() ?: 'unknown error'));

            return self::FAILURE;
        }

        file_put_contents($privatePath, $private);
        chmod($privatePath, 0600);

        file_put_contents($publicPath, $public);
        chmod($publicPath, 0644);

        $this->components->info(sprintf('Generated a %d bit RSA key pair.', $bits));

        $this->components->twoColumnDetail('<fg=yellow>Private key</>', $privatePath);
        $this->components->twoColumnDetail('<fg=yellow>Public key</>', $publicPath);

        $this->newLine();
        $this->components->bulletList([
            'Send <options=bold>' . basename($publicPath) . '</> to Azericard. It is the key they use to verify your requests.',
            'Point <options=bold>AZERICARD_PRIVATE_KEY</> at ' . $privatePath . ' and keep that file out of version control.',
            'Set <options=bold>AZERICARD_PUBLIC_KEY</> to the key <options=bold>Azericard gives you</>, not to the public key generated here. It verifies their callbacks.',
        ]);

        $this->newLine();
        $this->components->warn('Never regenerate the private key once Azericard has registered it: every request would fail signature verification.');

        return self::SUCCESS;
    }

    /**
     * @return array{0: string|null, 1: string|null}
     */
    private function generate(int $bits): array
    {
        $resource = openssl_pkey_new([
            'private_key_bits' => $bits,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);

        if ($resource === false || ! openssl_pkey_export($resource, $private)) {
            return [null, null];
        }

        $details = openssl_pkey_get_details($resource);

        return [$private, $details === false ? null : $details['key']];
    }

    private function confirmOverwrite(string $privatePath, string $publicPath): bool
    {
        $existing = array_values(array_filter([$privatePath, $publicPath], is_file(...)));

        if ($existing === []) {
            return true;
        }

        if (! $this->option('force')) {
            $this->components->error('Key files already exist. Pass --force to replace them.');

            foreach ($existing as $path) {
                $this->components->bulletList([$path]);
            }

            return false;
        }

        return $this->confirm(
            'This permanently destroys the existing key pair. If Azericard has registered it, every payment will start failing. Continue?',
            false
        );
    }

    private function directory(): string
    {
        $path = $this->stringOption('path') ?? storage_path('app/azericard');

        return rtrim($path, DIRECTORY_SEPARATOR);
    }

    private function prefix(): string
    {
        $configured = config('azericard.' . Options::MERCH_NAME);

        $name = $this->stringOption('name')
            ?? (is_string($configured) && $configured !== '' ? $configured : 'merchant');

        return Str::slug($name, '_') ?: 'merchant';
    }

    private function stringOption(string $key): ?string
    {
        $value = $this->option($key);

        return is_string($value) && $value !== '' ? $value : null;
    }
}
