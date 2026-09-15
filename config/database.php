<?php

declare(strict_types=1);

/**
 * Database configuration — MariaDB 11.6 on the application server.
 *
 * PORTABILITY NOTE
 * ================
 * The server runs MariaDB 11.6, which is no longer a drop-in equivalent of
 * MySQL 8: there is no native JSON column type (it is a LONGTEXT alias), the
 * default collation changed to utf8mb4_uca1400_ai_ci in 11.5, and functional
 * index syntax differs.
 *
 * All schema SQL is therefore written portably, and the collation is pinned
 * explicitly below rather than inherited from the server. RDS does not
 * currently offer MariaDB 11.6 — a future managed migration would land on
 * MariaDB 11.4 or MySQL 8 — so portable SQL is a requirement, not a preference.
 */

return [
    'default' => 'mysql',

    'connections' => [
        'mysql' => [
            'host'     => env('DB_HOST', '127.0.0.1'),
            'port'     => (int) env('DB_PORT', 3306),
            'database' => env('DB_DATABASE', 'threeam'),

            /*
             * Runtime credentials. This user holds DML only — SELECT, INSERT,
             * UPDATE, DELETE on this schema and nothing else. It cannot run DDL
             * and has no grants on any other schema, so a compromise of this
             * site cannot drop a table or read a single row belonging to
             * /armonyx or /trebl.
             *
             * Migration credentials are separate and deliberately absent from
             * this file — see bin/migrate.php.
             */
            'username' => env('DB_USERNAME', '3am_app'),
            'password' => env('DB_PASSWORD', ''),

            'charset'   => env('DB_CHARSET', 'utf8mb4'),
            'collation' => 'utf8mb4_unicode_ci',
        ],
    ],

    /*
     * Slow query threshold in milliseconds. Queries above this are logged in
     * debug mode. The homepage runs several joins across projects, media and
     * categories, and the work index is the most likely place for an N+1 to
     * appear — this is how it gets noticed before a visitor does.
     */
    'slow_query_ms' => 100,
];
