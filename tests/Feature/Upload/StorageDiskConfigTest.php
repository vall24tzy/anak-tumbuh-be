<?php

namespace Tests\Feature\Upload;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

// DEPLOY-STORAGE-001 (D-28, usulan) - disk logis 'private' (foto Student, F-06) dan 'public' (logo School)
// dapat dialihkan ke S3 (Supabase Storage) lewat env, tanpa mengubah nama disk. Test ini mengunci:
//   - default tanpa env = disk lokal seperti sebelumnya (dev dan test tidak berubah);
//   - disk S3 selalu 'throw' => false (UploadService memeriksa put() === false), bucket privat dan publik terpisah,
//     disk privat TANPA 'url', disk publik membentuk URL dari AWS_PUBLIC_URL;
//   - kegagalan put() pada disk S3 menghasilkan RuntimeException yang sama seperti disk lokal.
//
// Test yang butuh adapter S3 dilewati bila league/flysystem-aws-s3-v3 belum terpasang
// (composer require league/flysystem-aws-s3-v3). Tidak ada koneksi ke Supabase atau AWS; kegagalan put() disimulasikan
// ke endpoint lokal yang menolak koneksi.
//
// STATUS EKSEKUSI: lulus dalam suite penuh 670 (PHP 8.3.30); jumlah dilewati tidak tercatat. Ulangi dengan
// `php artisan test --filter=StorageDiskConfigTest`.

class StorageDiskConfigTest extends TestCase
{
    private const KEYS = [
        'STORAGE_PRIVATE_DRIVER', 'STORAGE_PUBLIC_DRIVER', 'AWS_ACCESS_KEY_ID', 'AWS_SECRET_ACCESS_KEY',
        'AWS_DEFAULT_REGION', 'AWS_ENDPOINT', 'AWS_USE_PATH_STYLE_ENDPOINT', 'AWS_PRIVATE_BUCKET',
        'AWS_PUBLIC_BUCKET', 'AWS_PUBLIC_URL',
    ];

    /** @var array<string, array{0: string|false, 1: mixed, 2: mixed}> */
    private array $snapshot = [];

    protected function setUp(): void
    {
        parent::setUp();

        foreach (self::KEYS as $key) {
            $this->snapshot[$key] = [getenv($key), $_ENV[$key] ?? null, $_SERVER[$key] ?? null];
        }
    }

    protected function tearDown(): void
    {
        foreach ($this->snapshot as $key => [$getenv, $env, $server]) {
            $getenv === false ? putenv($key) : putenv("{$key}={$getenv}");
            $env === null ? $this->forget($_ENV, $key) : $_ENV[$key] = $env;
            $server === null ? $this->forget($_SERVER, $key) : $_SERVER[$key] = $server;
        }

        parent::tearDown();
    }

    private function forget(array &$bag, string $key): void
    {
        unset($bag[$key]);
    }

    /** Set semua kunci env (null = hapus), lalu evaluasi ulang config/filesystems.php. */
    private function disks(array $env = []): array
    {
        foreach (self::KEYS as $key) {
            $value = $env[$key] ?? null;

            if ($value === null) {
                putenv($key);
                unset($_ENV[$key], $_SERVER[$key]);
            } else {
                putenv("{$key}={$value}");
                $_ENV[$key] = $value;
                $_SERVER[$key] = $value;
            }
        }

        return (require config_path('filesystems.php'))['disks'];
    }

    private function s3Env(array $extra = []): array
    {
        return $extra + [
            'AWS_ACCESS_KEY_ID'     => 'kunci-uji',
            'AWS_SECRET_ACCESS_KEY' => 'rahasia-uji',
            'AWS_DEFAULT_REGION'    => 'ap-southeast-1',
            'AWS_ENDPOINT'          => 'https://proyek-uji.supabase.co/storage/v1/s3',
            'AWS_PRIVATE_BUCKET'    => 'bucket-privat-uji',
            'AWS_PUBLIC_BUCKET'     => 'bucket-publik-uji',
            'AWS_PUBLIC_URL'        => 'https://proyek-uji.supabase.co/storage/v1/object/public/bucket-publik-uji',
        ];
    }

    private function requireS3Adapter(): void
    {
        if (!class_exists(\League\Flysystem\AwsS3V3\AwsS3V3Adapter::class)) {
            $this->markTestSkipped('league/flysystem-aws-s3-v3 belum terpasang (composer require league/flysystem-aws-s3-v3).');
        }
    }

    /** @test */
    public function tanpa_env_disk_private_dan_public_tetap_lokal(): void
    {
        $disks = $this->disks();

        $this->assertSame('local', $disks['private']['driver']);
        $this->assertSame(storage_path('app/private'), $disks['private']['root']);
        $this->assertSame('private', $disks['private']['visibility']);
        $this->assertArrayNotHasKey('url', $disks['private'], 'foto Student tidak boleh punya URL publik (F-06)');
        $this->assertFalse($disks['private']['throw']);

        $this->assertSame('local', $disks['public']['driver']);
        $this->assertSame(storage_path('app/public'), $disks['public']['root']);
        $this->assertStringEndsWith('/storage', $disks['public']['url']);
        $this->assertFalse($disks['public']['throw']);
    }

    /** @test */
    public function private_s3_memakai_bucket_privat_tanpa_url_dan_public_tetap_lokal(): void
    {
        $disks = $this->disks($this->s3Env(['STORAGE_PRIVATE_DRIVER' => 's3']));

        $this->assertSame('s3', $disks['private']['driver']);
        $this->assertSame('bucket-privat-uji', $disks['private']['bucket']);
        $this->assertSame('https://proyek-uji.supabase.co/storage/v1/s3', $disks['private']['endpoint']);
        $this->assertSame('ap-southeast-1', $disks['private']['region']);
        $this->assertSame('kunci-uji', $disks['private']['key']);
        $this->assertTrue($disks['private']['use_path_style_endpoint']);
        $this->assertFalse($disks['private']['throw']);
        $this->assertArrayNotHasKey('url', $disks['private'], 'bucket privat tidak boleh membentuk URL publik');

        $this->assertSame('local', $disks['public']['driver'], 'STORAGE_PUBLIC_DRIVER tidak diset, logo tetap lokal');
    }

    /** @test */
    public function public_s3_memakai_bucket_publik_dan_url_dari_aws_public_url(): void
    {
        $disks = $this->disks($this->s3Env(['STORAGE_PUBLIC_DRIVER' => 's3']));

        $this->assertSame('s3', $disks['public']['driver']);
        $this->assertSame('bucket-publik-uji', $disks['public']['bucket']);
        $this->assertSame(
            'https://proyek-uji.supabase.co/storage/v1/object/public/bucket-publik-uji',
            $disks['public']['url']
        );
        $this->assertTrue($disks['public']['use_path_style_endpoint']);
        $this->assertFalse($disks['public']['throw']);

        $this->assertSame('local', $disks['private']['driver'], 'foto Student tidak ikut berpindah tanpa STORAGE_PRIVATE_DRIVER');
    }

    /** @test */
    public function dua_bucket_tidak_pernah_sama_dan_semua_disk_s3_throw_false(): void
    {
        $disks = $this->disks($this->s3Env(['STORAGE_PRIVATE_DRIVER' => 's3', 'STORAGE_PUBLIC_DRIVER' => 's3']));

        $this->assertNotSame($disks['private']['bucket'], $disks['public']['bucket']);

        foreach (['private', 'public', 's3'] as $name) {
            $this->assertSame('s3', $disks[$name]['driver'] ?? 's3');
            $this->assertFalse($disks[$name]['throw'], "disk {$name}: 'throw' harus false (UploadService memeriksa put() === false)");
        }
    }

    /** @test */
    public function path_style_dapat_dimatikan_lewat_env(): void
    {
        $disks = $this->disks($this->s3Env(['STORAGE_PRIVATE_DRIVER' => 's3', 'AWS_USE_PATH_STYLE_ENDPOINT' => 'false']));

        $this->assertFalse($disks['private']['use_path_style_endpoint']);
    }

    /** @test */
    public function url_logo_dibentuk_dari_aws_public_url_bukan_dari_endpoint_s3(): void
    {
        $this->requireS3Adapter();

        $disks = $this->disks($this->s3Env(['STORAGE_PUBLIC_DRIVER' => 's3']));
        config(['filesystems.disks.public' => $disks['public']]);
        Storage::purge('public');

        $this->assertSame(
            'https://proyek-uji.supabase.co/storage/v1/object/public/bucket-publik-uji/logos/1.png',
            Storage::disk('public')->url('logos/1.png')
        );
    }

    /** @test */
    public function kegagalan_put_pada_disk_s3_menghasilkan_runtime_exception_yang_sama_seperti_disk_lokal(): void
    {
        $this->requireS3Adapter();

        $disks = $this->disks($this->s3Env(['STORAGE_PRIVATE_DRIVER' => 's3']));
        // Endpoint lokal yang menolak koneksi, tanpa retry dan dengan timeout pendek: tidak ada trafik keluar.
        $disks['private']['endpoint'] = 'http://127.0.0.1:9';
        $disks['private']['retries'] = 0;
        $disks['private']['http'] = ['connect_timeout' => 1, 'timeout' => 2];
        config(['filesystems.disks.private' => $disks['private']]);
        Storage::purge('private');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Gagal menyimpan file upload');

        app(\App\Services\Upload\UploadService::class)->upload(
            UploadedFile::fake()->image('foto.png', 20, 20),
            'students',
            1,
            null,
            'private'
        );
    }
}
