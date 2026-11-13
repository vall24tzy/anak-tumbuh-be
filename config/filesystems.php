<?php

// DEPLOY-STORAGE-001 (D-28, usulan): di cloud (Vercel) disk lokal tidak awet, jadi disk logis
// 'private' (foto Student, F-06) dan 'public' (logo School) dapat dialihkan ke S3 (Supabase Storage)
// lewat env. Nama disk TIDAK berubah, sehingga seluruh kode dan test yang memakai
// Storage::disk('private'|'public') tetap berlaku. Default tetap lokal (dev dan test).
// Kunci 'throw' => false WAJIB tetap ada pada disk S3: UploadService memeriksa put() === false.
// 'visibility' sengaja tidak diset pada disk S3 (akses diatur lewat bucket privat/publik, bukan ACL).
// DEPLOY-PILOT-001: di runtime Vercel nama AWS_ACCESS_KEY_ID dan AWS_SECRET_ACCESS_KEY dapat tertimpa kredensial
// milik platform, sehingga unggah ke Supabase gagal. Karena itu kunci dibaca lebih dulu dari STORAGE_S3_KEY dan
// STORAGE_S3_SECRET; bila keduanya tidak ada, perilaku lama (AWS_*) tetap berlaku.
$storageS3 = static fn (string $bucketEnv, array $extra = []): array => array_merge([
    'driver'                  => 's3',
    'key'                     => env('STORAGE_S3_KEY', env('AWS_ACCESS_KEY_ID')),
    'secret'                  => env('STORAGE_S3_SECRET', env('AWS_SECRET_ACCESS_KEY')),
    'region'                  => env('AWS_DEFAULT_REGION'),
    'bucket'                  => env($bucketEnv),
    'endpoint'                => env('AWS_ENDPOINT'),
    'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', true),
    'throw'                   => false,
], $extra);

return [

    /*
    |--------------------------------------------------------------------------
    | Default Filesystem Disk
    |--------------------------------------------------------------------------
    |
    | Here you may specify the default filesystem disk that should be used
    | by the framework. The "local" disk, as well as a variety of cloud
    | based disks are available to your application for file storage.
    |
    */

    'default' => env('FILESYSTEM_DISK', 'local'),

    /*
    |--------------------------------------------------------------------------
    | Filesystem Disks
    |--------------------------------------------------------------------------
    |
    | Below you may configure as many filesystem disks as necessary, and you
    | may even configure multiple disks for the same driver. Examples for
    | most supported storage drivers are configured here for reference.
    |
    | Supported Drivers: "local", "ftp", "sftp", "s3"
    |
    */

    'disks' => [

        'local' => [
            'driver' => 'local',
            'root' => storage_path('app'),
            'throw' => false,
        ],

        // Logo School. STORAGE_PUBLIC_DRIVER=s3: bucket publik (AWS_PUBLIC_BUCKET); URL logo dibentuk dari
        // AWS_PUBLIC_URL (wajib diisi), bukan dari endpoint S3.
        'public' => env('STORAGE_PUBLIC_DRIVER', 'local') === 's3'
            ? $storageS3('AWS_PUBLIC_BUCKET', ['url' => env('AWS_PUBLIC_URL')])
            : [
                'driver' => 'local',
                'root' => storage_path('app/public'),
                'url' => env('APP_URL').'/storage',
                'visibility' => 'public',
                'throw' => false,
            ],

        // F-06: berkas privat (foto Student). Root di LUAR storage/app/public dan
        // tanpa 'url', sehingga tidak pernah dilayani web server; hanya dapat dibaca
        // lewat endpoint terotorisasi (GET /students/{id}/photo). Logo School tetap
        // di disk 'public'.
        // STORAGE_PRIVATE_DRIVER=s3: bucket PRIVAT (AWS_PRIVATE_BUCKET), tetap tanpa 'url'.
        'private' => env('STORAGE_PRIVATE_DRIVER', 'local') === 's3'
            ? $storageS3('AWS_PRIVATE_BUCKET')
            : [
                'driver' => 'local',
                'root' => storage_path('app/private'),
                'visibility' => 'private',
                'throw' => false,
            ],

        's3' => [
            'driver' => 's3',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION'),
            'bucket' => env('AWS_BUCKET'),
            'url' => env('AWS_URL'),
            'endpoint' => env('AWS_ENDPOINT'),
            'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
            'throw' => false,
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Symbolic Links
    |--------------------------------------------------------------------------
    |
    | Here you may configure the symbolic links that will be created when the
    | `storage:link` Artisan command is executed. The array keys should be
    | the locations of the links and the values should be their targets.
    |
    */

    'links' => [
        public_path('storage') => storage_path('app/public'),
    ],

];
