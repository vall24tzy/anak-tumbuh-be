<?php

namespace Tests\Concerns;

use Illuminate\Http\Middleware\TrustProxies;

/**
 * DEPLOY-RUNTIME-001: mengubah variabel environment SEMENTARA di dalam test, lalu mengembalikannya.
 *
 * Laravel membaca env() secara langsung dari $_ENV, $_SERVER, dan getenv(), dan Dotenv bersifat immutable
 * (tidak menimpa nilai yang sudah ada). Karena itu nilai yang diset di sini mengalahkan .env/.env.testing
 * dan baru terbaca bila diset SEBELUM aplikasi dibuat (setUp sebelum parent::setUp()) atau sebelum
 * config file di-require ulang (configWithEnv()).
 */
trait SetsTemporaryEnv
{
    /** @var array<string, array{env: array{0: bool, 1: mixed}, server: array{0: bool, 1: mixed}, getenv: string|false}> */
    private array $envBackup = [];

    /** Set (atau hapus bila null) satu variabel env di ketiga penyimpanan; nilai awal diingat untuk restoreEnv(). */
    protected function setEnv(string $name, ?string $value): void
    {
        if (!array_key_exists($name, $this->envBackup)) {
            $this->envBackup[$name] = [
                'env'    => [array_key_exists($name, $_ENV), $_ENV[$name] ?? null],
                'server' => [array_key_exists($name, $_SERVER), $_SERVER[$name] ?? null],
                'getenv' => getenv($name),
            ];
        }

        if ($value === null) {
            unset($_ENV[$name], $_SERVER[$name]);
            putenv($name);

            return;
        }

        $_ENV[$name] = $value;
        $_SERVER[$name] = $value;
        putenv("{$name}={$value}");
    }

    protected function restoreEnv(): void
    {
        foreach ($this->envBackup as $name => $backup) {
            [$envExisted, $envValue] = $backup['env'];
            [$serverExisted, $serverValue] = $backup['server'];

            if ($envExisted) {
                $_ENV[$name] = $envValue;
            } else {
                unset($_ENV[$name]);
            }

            if ($serverExisted) {
                $_SERVER[$name] = $serverValue;
            } else {
                unset($_SERVER[$name]);
            }

            if ($backup['getenv'] === false) {
                putenv($name);
            } else {
                putenv("{$name}={$backup['getenv']}");
            }
        }

        $this->envBackup = [];
    }

    /**
     * Muat ulang satu berkas config/<nama>.php dengan env tertentu dan kembalikan arraynya, lalu pulihkan env.
     * Nilai null = variabel dihapus (supaya hasil tidak bergantung pada env mesin yang menjalankan test).
     *
     * @param  array<string, string|null>  $env
     * @return array<string, mixed>
     */
    protected function configWithEnv(string $file, array $env): array
    {
        foreach ($env as $name => $value) {
            $this->setEnv($name, $value);
        }

        try {
            return require config_path("{$file}.php");
        } finally {
            $this->restoreEnv();
        }
    }

    /**
     * TrustProxies::at() menyimpan daftar proxy di properti STATIC, sehingga bocor ke test berikutnya dalam
     * proses PHP yang sama. Panggil sebelum membuat aplikasi dan setelah test selesai.
     */
    protected function flushTrustedProxies(): void
    {
        TrustProxies::flushState();
    }
}
