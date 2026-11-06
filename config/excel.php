<?php

// DEPLOY-IMAGE-001 butir 8b: maatwebsite/excel menulis file sementara (import dan export) ke
// storage/framework/cache/laravel-excel. Di Vercel Container Runtime direktori itu belum tentu bisa ditulis,
// jadi lokasinya bisa diarahkan lewat env (EXCEL_TEMP_PATH=/tmp/laravel-excel pada image).
//
// Berkas ini HANYA menimpa bagian `temporary_files`; bagian lain memakai bawaan paket. Service provider paket
// menggabungkan config hanya satu tingkat (seluruh bagian `temporary_files` diganti), karena itu seluruh kunci
// bagian itu ditulis ulang di sini dengan nilai bawaan paket. Default tanpa env = perilaku sebelumnya.
return [

    'temporary_files' => [
        'local_path'          => env('EXCEL_TEMP_PATH', storage_path('framework/cache/laravel-excel')),
        'local_permissions'   => [],
        'remote_disk'         => null,
        'remote_prefix'       => null,
        'force_resync_remote' => null,
    ],
];
