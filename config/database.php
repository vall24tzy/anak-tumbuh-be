<?php

use Illuminate\Support\Str;

return [

    /*
    |--------------------------------------------------------------------------
    | Default Database Connection Name
    |--------------------------------------------------------------------------
    |
    | Here you may specify which of the database connections below you wish
    | to use as your default connection for database operations. This is
    | the connection which will be utilized unless another connection
    | is explicitly specified when you execute a query / statement.
    |
    */

    'default' => env('DB_CONNECTION', 'sqlite'),

    /*
    |--------------------------------------------------------------------------
    | Database Connections
    |--------------------------------------------------------------------------
    |
    | Below are all of the database connections defined for your application.
    | An example configuration is provided for each database system which
    | is supported by Laravel. You're free to add / remove connections.
    |
    */

    'connections' => [

        'sqlite' => [
            'driver' => 'sqlite',
            'url' => env('DB_URL'),
            'database' => env('DB_DATABASE', database_path('database.sqlite')),
            'prefix' => '',
            'foreign_key_constraints' => env('DB_FOREIGN_KEYS', true),
        ],

        'mysql' => [
            'driver' => 'mysql',
            'url' => env('DB_URL'),
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', '3306'),
            'database' => env('DB_DATABASE', 'laravel'),
            'username' => env('DB_USERNAME', 'root'),
            'password' => env('DB_PASSWORD', ''),
            'unix_socket' => env('DB_SOCKET', ''),
            'charset' => env('DB_CHARSET', 'utf8mb4'),
            'collation' => env('DB_COLLATION', 'utf8mb4_0900_ai_ci'),
            'prefix' => '',
            'prefix_indexes' => true,
            'strict' => true,
            'engine' => null,
            'options' => extension_loaded('pdo_mysql') ? array_filter([
                PDO::MYSQL_ATTR_SSL_CA => env('MYSQL_ATTR_SSL_CA'),
            ]) : [],
        ],

        'mariadb' => [
            'driver' => 'mariadb',
            'url' => env('DB_URL'),
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', '3306'),
            'database' => env('DB_DATABASE', 'laravel'),
            'username' => env('DB_USERNAME', 'root'),
            'password' => env('DB_PASSWORD', ''),
            'unix_socket' => env('DB_SOCKET', ''),
            'charset' => env('DB_CHARSET', 'utf8mb4'),
            'collation' => env('DB_COLLATION', 'utf8mb4_uca1400_ai_ci'),
            'prefix' => '',
            'prefix_indexes' => true,
            'strict' => true,
            'engine' => null,
            'options' => extension_loaded('pdo_mysql') ? array_filter([
                PDO::MYSQL_ATTR_SSL_CA => env('MYSQL_ATTR_SSL_CA'),
            ]) : [],
        ],

        'pgsql' => [
            'driver' => 'pgsql',
            'url' => env('DB_URL'),
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', '5432'),
            'database' => env('DB_DATABASE', 'laravel'),
            'username' => env('DB_USERNAME', 'root'),
            'password' => env('DB_PASSWORD', ''),
            'charset' => env('DB_CHARSET', 'utf8'),
            'prefix' => '',
            'prefix_indexes' => true,
            'search_path' => 'public',
            // DEPLOY-RUNTIME-001 (D-27): SSL dan opsi PDO bisa diatur lewat env tanpa mengubah kode.
            // Default tidak mengubah perilaku sebelumnya (sslmode 'prefer', prepared statements bawaan).
            // Supabase: DB_SSLMODE=require (atau verify-full + DB_SSLROOTCERT).
            'sslmode' => env('DB_SSLMODE', 'prefer'),
            'sslrootcert' => env('DB_SSLROOTCERT'),
            // Pooler Supabase mode transaksi TIDAK mendukung prepared statements bernama, sedangkan PDO pgsql
            // memakainya secara default. Dua opsi, KEDUANYA default mati (filter_var supaya 'false', '0', 'off',
            // 'no' benar-benar dianggap mati):
            //  - DB_DISABLE_PREPARES=true -> PDO::PGSQL_ATTR_DISABLE_PREPARES: query dan parameter dikirim sekaligus
            //    ke server (tanpa prepared statement bernama). Parameter tetap bertipe "unknown" sehingga PostgreSQL
            //    menyimpulkan tipe dari kolom. Opsi yang disarankan untuk pooler mode transaksi.
            //  - DB_EMULATE_PREPARES=true -> PDO::ATTR_EMULATE_PREPARES: PDO menyusun query di sisi klien dan
            //    menyisipkan nilai sebagai literal. TIDAK DISARANKAN: Laravel mengubah boolean menjadi 1/0, sehingga
            //    `where is_active = 1` ditolak PostgreSQL ("operator does not exist: boolean = integer"); terbukti
            //    pada run suite dengan flag ini (lihat docs/addendum/DEPLOY-RUNTIME-001.md bagian 3).
            'options' => [
                PDO::ATTR_EMULATE_PREPARES => filter_var(env('DB_EMULATE_PREPARES', false), FILTER_VALIDATE_BOOLEAN),
            ] + (filter_var(env('DB_DISABLE_PREPARES', false), FILTER_VALIDATE_BOOLEAN)
                ? [PDO::PGSQL_ATTR_DISABLE_PREPARES => true]
                : []),
        ],

        // DEPLOY-RUNTIME-001 (D-27): koneksi khusus langkah rilis (usulan D-27: pooler mode SESSION port 5432,
        // karena koneksi langsung Supabase hanya IPv6; koneksi langsung tetap bisa dipakai bila jaringan mendukung):
        //   php artisan migrate --database=pgsql_direct --force
        //   php artisan db:seed --database=pgsql_direct --force
        // Tanpa opsi emulate prepares (prepared statements bawaan PDO). Tiap nilai DB_DIRECT_* yang kosong
        // jatuh ke DB_* yang sama (praktis untuk lokal/CI). Di lingkungan yang aplikasinya lewat pooler
        // (DB_URL / DB_HOST pooler), DB_DIRECT_URL atau DB_DIRECT_HOST WAJIB diisi: pooler dan koneksi
        // langsung Supabase memakai host dan username yang berbeda.
        'pgsql_direct' => [
            'driver' => 'pgsql',
            'url' => env('DB_DIRECT_URL'),
            'host' => env('DB_DIRECT_HOST', env('DB_HOST', '127.0.0.1')),
            'port' => env('DB_DIRECT_PORT', env('DB_PORT', '5432')),
            'database' => env('DB_DIRECT_DATABASE', env('DB_DATABASE', 'laravel')),
            'username' => env('DB_DIRECT_USERNAME', env('DB_USERNAME', 'root')),
            'password' => env('DB_DIRECT_PASSWORD', env('DB_PASSWORD', '')),
            'charset' => env('DB_CHARSET', 'utf8'),
            'prefix' => '',
            'prefix_indexes' => true,
            'search_path' => 'public',
            'sslmode' => env('DB_DIRECT_SSLMODE', env('DB_SSLMODE', 'prefer')),
            'sslrootcert' => env('DB_DIRECT_SSLROOTCERT', env('DB_SSLROOTCERT')),
        ],

        'sqlsrv' => [
            'driver' => 'sqlsrv',
            'url' => env('DB_URL'),
            'host' => env('DB_HOST', 'localhost'),
            'port' => env('DB_PORT', '1433'),
            'database' => env('DB_DATABASE', 'laravel'),
            'username' => env('DB_USERNAME', 'root'),
            'password' => env('DB_PASSWORD', ''),
            'charset' => env('DB_CHARSET', 'utf8'),
            'prefix' => '',
            'prefix_indexes' => true,
            // 'encrypt' => env('DB_ENCRYPT', 'yes'),
            // 'trust_server_certificate' => env('DB_TRUST_SERVER_CERTIFICATE', 'false'),
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Migration Repository Table
    |--------------------------------------------------------------------------
    |
    | This table keeps track of all the migrations that have already run for
    | your application. Using this information, we can determine which of
    | the migrations on disk haven't actually been run on the database.
    |
    */

    'migrations' => [
        'table' => 'migrations',
        'update_date_on_publish' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Redis Databases
    |--------------------------------------------------------------------------
    |
    | Redis is an open source, fast, and advanced key-value store that also
    | provides a richer body of commands than a typical key-value system
    | such as Memcached. You may define your connection settings here.
    |
    */

    'redis' => [

        'client' => env('REDIS_CLIENT', 'phpredis'),

        'options' => [
            'cluster' => env('REDIS_CLUSTER', 'redis'),
            'prefix' => env('REDIS_PREFIX', Str::slug(env('APP_NAME', 'laravel'), '_').'_database_'),
        ],

        'default' => [
            'url' => env('REDIS_URL'),
            'host' => env('REDIS_HOST', '127.0.0.1'),
            'username' => env('REDIS_USERNAME'),
            'password' => env('REDIS_PASSWORD'),
            'port' => env('REDIS_PORT', '6379'),
            'database' => env('REDIS_DB', '0'),
        ],

        'cache' => [
            'url' => env('REDIS_URL'),
            'host' => env('REDIS_HOST', '127.0.0.1'),
            'username' => env('REDIS_USERNAME'),
            'password' => env('REDIS_PASSWORD'),
            'port' => env('REDIS_PORT', '6379'),
            'database' => env('REDIS_CACHE_DB', '1'),
        ],

    ],

];
