<?php

namespace Tests\Feature\Upload;

use Illuminate\Http\UploadedFile;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Event;
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
// Review silang Orang 2 atas DEPLOY-STORAGE-001 (S-2, S-3): disk S3 'report' => true supaya galat dasar S3 tetap
// terlapor walau throw=false; UploadService mencatat disk dan path saat put() gagal (tanpa isi file/kredensial);
// kasus AWS_PUBLIC_URL kosong didokumentasikan lewat test karakterisasi.
//
// STATUS EKSEKUSI: 9 test awal lulus tanpa dilewati (php artisan test --filter=StorageDiskConfigTest, Orang 1, 16 Nov 2026);
// 12 test (termasuk 3 baru S-2/S-3) lulus dengan PHPUnit 10.5 di PHP 8.3.6, 59 assertion.

class StorageDiskConfigTest extends TestCase
{
    private const KEYS = [
        'STORAGE_PRIVATE_DRIVER', 'STORAGE_PUBLIC_DRIVER', 'AWS_ACCESS_KEY_ID', 'AWS_SECRET_ACCESS_KEY',
        'STORAGE_S3_KEY', 'STORAGE_S3_SECRET',
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
    public function kunci_storage_s3_didahulukan_atas_kunci_aws_bawaan_platform(): void
    {
        $this->requireS3Adapter();

        // DEPLOY-PILOT-001: AWS_* di Vercel dapat tertimpa kredensial platform; STORAGE_S3_* harus menang.
        $disks = $this->disks($this->s3Env([
            'STORAGE_PRIVATE_DRIVER' => 's3',
            'STORAGE_PUBLIC_DRIVER'  => 's3',
            'STORAGE_S3_KEY'         => 'kunci-storage',
            'STORAGE_S3_SECRET'      => 'rahasia-storage',
        ]));

        foreach (['private', 'public'] as $disk) {
            $this->assertSame('kunci-storage', $disks[$disk]['key'], "disk {$disk}");
            $this->assertSame('rahasia-storage', $disks[$disk]['secret'], "disk {$disk}");
        }
    }

    /** @test */
    public function tanpa_storage_s3_kunci_tetap_dibaca_dari_aws_seperti_sebelumnya(): void
    {
        $this->requireS3Adapter();

        $disks = $this->disks($this->s3Env([
            'STORAGE_PRIVATE_DRIVER' => 's3',
            'STORAGE_PUBLIC_DRIVER'  => 's3',
        ]));

        foreach (['private', 'public'] as $disk) {
            $this->assertSame('kunci-uji', $disks[$disk]['key'], "disk {$disk}");
            $this->assertSame('rahasia-uji', $disks[$disk]['secret'], "disk {$disk}");
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

    /** @test */
    public function semua_disk_s3_melaporkan_exception_flysystem_walau_throw_false(): void
    {
        // Review silang Orang 2, S-2: 'throw' => false menelan galat dasar S3; 'report' => true membuatnya tetap masuk log.
        $disks = $this->disks($this->s3Env(['STORAGE_PRIVATE_DRIVER' => 's3', 'STORAGE_PUBLIC_DRIVER' => 's3']));

        foreach (['private', 'public'] as $disk) {
            $this->assertTrue($disks[$disk]['report'], "disk {$disk}");
            $this->assertFalse($disks[$disk]['throw'], "disk {$disk}: throw tetap false (respons API tidak berubah)");
        }
    }

    /** @test */
    public function kegagalan_put_dicatat_dengan_disk_dan_path_dan_galat_s3_dilaporkan_tanpa_membocorkan_kunci(): void
    {
        $this->requireS3Adapter();

        $disks = $this->disks($this->s3Env(['STORAGE_PRIVATE_DRIVER' => 's3']));
        $disks['private']['endpoint'] = 'http://127.0.0.1:9';
        $disks['private']['retries'] = 0;
        $disks['private']['http'] = ['connect_timeout' => 1, 'timeout' => 2];
        config(['filesystems.disks.private' => $disks['private']]);
        Storage::purge('private');

        /** @var list<MessageLogged> $logs */
        $logs = [];
        Event::listen(MessageLogged::class, function (MessageLogged $event) use (&$logs) {
            $logs[] = $event;
        });

        try {
            app(\App\Services\Upload\UploadService::class)->upload(
                UploadedFile::fake()->image('foto.png', 20, 20),
                'students',
                1,
                null,
                'private'
            );
            $this->fail('RuntimeException diharapkan saat put() gagal.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('Gagal menyimpan file upload', $e->getMessage());
        }

        // 1) Log dari UploadService: hanya disk dan path.
        $uploadLogs = array_values(array_filter(
            $logs,
            fn (MessageLogged $log) => $log->message === 'Gagal menyimpan file upload ke storage'
        ));
        $this->assertCount(1, $uploadLogs);
        $this->assertSame('error', $uploadLogs[0]->level);
        $this->assertSame(['disk' => 'private', 'path' => 'students/1.png'], $uploadLogs[0]->context);

        // 2) Galat dasar S3 dilaporkan berkat 'report' => true (exception Flysystem tidak dilempar ke pemanggil).
        $reported = array_values(array_filter(
            $logs,
            fn (MessageLogged $log) => ($log->context['exception'] ?? null) instanceof \League\Flysystem\UnableToWriteFile
        ));
        $this->assertNotEmpty($reported, "exception Flysystem harus ikut dilaporkan ('report' => true)");

        // 3) Tidak ada log, pesan, maupun exception berantai yang memuat kunci atau rahasia.
        foreach ($logs as $log) {
            $text = $log->message . json_encode($log->context, JSON_PARTIAL_OUTPUT_ON_ERROR);
            $exception = $log->context['exception'] ?? null;
            while ($exception instanceof \Throwable) {
                $text .= $exception->getMessage();
                $exception = $exception->getPrevious();
            }

            foreach (['rahasia-uji', 'kunci-uji'] as $secret) {
                $this->assertStringNotContainsString($secret, $text, 'kredensial tidak boleh muncul di log');
            }
        }
    }

    /** @test */
    public function public_s3_tanpa_aws_public_url_membentuk_url_dari_endpoint_s3_yang_bukan_url_publik(): void
    {
        $this->requireS3Adapter();

        // Review silang Orang 2, S-3, test karakterisasi: bila STORAGE_PUBLIC_DRIVER=s3 tetapi AWS_PUBLIC_URL kosong,
        // 'url' bernilai null sehingga SchoolResource membentuk URL logo dari ENDPOINT S3 API (tidak bisa dibuka publik),
        // dan responsnya tetap 200. Karena itu AWS_PUBLIC_URL WAJIB diisi bersama STORAGE_PUBLIC_DRIVER=s3 (dicatat di
        // DEPLOY-DOC-001). Tidak ada penjaga saat boot (keputusan: cukup test + dokumentasi). Bila suatu saat penjaga
        // ditambahkan, test ini harus diubah menjadi pengujian penjaganya.
        $disks = $this->disks($this->s3Env(['STORAGE_PUBLIC_DRIVER' => 's3', 'AWS_PUBLIC_URL' => null]));
        $this->assertNull($disks['public']['url']);

        config(['filesystems.disks.public' => $disks['public']]);
        Storage::purge('public');

        $url = Storage::disk('public')->url('logos/1.png');

        $this->assertStringStartsWith('https://proyek-uji.supabase.co/storage/v1/s3/', $url);
        $this->assertStringNotContainsString('/object/public/', $url, 'bukan URL publik Supabase');
    }
}
