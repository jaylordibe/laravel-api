<?php

use Illuminate\Support\Str;

/*
|--------------------------------------------------------------------------
| Redis connection factory
|--------------------------------------------------------------------------
|
| Builds one Redis connection array from the generic REDIS_* variables. Used for
| every connection below so cache, queue, Horizon, locks and rate limiting can
| never drift apart in their transport security or credentials.
|
| TLS is opt-in via REDIS_TLS_ENABLED. When it is on, `scheme => tls` makes the
| Laravel phpredis connector prefix the host with tls://, and the stream context
| below carries the verification settings.
|
| PEER VERIFICATION IS NOT CONFIGURABLE. verify_peer and verify_peer_name are
| hard-coded true, with no environment variable to weaken them, because TLS to an
| unverified peer defends against a passive listener only — and the reason to run
| Redis over TLS in the first place is that the network between the application
| and the cache is not trusted. REDIS_TLS_CA exists for the legitimate version of
| the problem it is usually disabled for: a private or internal CA, which is
| supplied rather than ignored.
|
| @param string|int $database the logical Redis database index for this connection
|
| @return array<string, mixed>
*/
$redisConnection = static function (string|int $database): array {
    $tlsEnabled = filter_var(env('REDIS_TLS_ENABLED', false), FILTER_VALIDATE_BOOL);

    $connection = [
        /*
         * A full DSN (redis://user:pass@host:port/db, or rediss:// for TLS).
         * When set it wins over the discrete values below — some managed
         * services hand out exactly one connection string, and re-splitting it
         * into parts by hand is a step at which credentials get mangled.
         */
        'url' => env('REDIS_URL'),
        'host' => env('REDIS_HOST', '127.0.0.1'),
        'username' => env('REDIS_USERNAME'),
        'password' => env('REDIS_PASSWORD'),
        'port' => env('REDIS_PORT', '6379'),
        'database' => $database,

        /*
         * Bounded connect and read timeouts. A hung Redis must surface as a
         * failed request or a failed job, not as a php-fpm worker or queue
         * process parked indefinitely on a socket read.
         */
        'timeout' => (float) env('REDIS_TIMEOUT', 5),
        'read_timeout' => (float) env('REDIS_READ_TIMEOUT', 10),
    ];

    if (!$tlsEnabled) {
        return $connection;
    }

    $connection['scheme'] = 'tls';
    $connection['context'] = [
        'ssl' => array_filter([
            'verify_peer' => true,
            'verify_peer_name' => true,

            /*
             * Path to a PEM CA bundle, for an endpoint whose certificate chains
             * to a private CA. Omitted when unset, so a publicly trusted
             * certificate verifies against the system trust store.
             */
            'cafile' => env('REDIS_TLS_CA'),

            /*
             * Only needed where the endpoint requires mutual TLS.
             */
            'local_cert' => env('REDIS_TLS_CERT'),
            'local_pk' => env('REDIS_TLS_KEY'),
        ], static fn (mixed $value): bool => $value !== null && $value !== ''),
    ];

    return $connection;
};

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

    /*
     * PostgreSQL is this template's engine. The fallback here is `pgsql` rather
     * than Laravel's stock `sqlite` so a missing DB_CONNECTION fails against the
     * real engine instead of silently succeeding against a local SQLite file
     * whose behaviour (types, transactions, case sensitivity) differs from
     * everything the application is tested on.
     */
    'default' => env('DB_CONNECTION', 'pgsql'),

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
            'busy_timeout' => null,
            'journal_mode' => null,
            'synchronous' => null,
        ],

        /*
        |----------------------------------------------------------------------
        | MySQL — retained, but NOT the engine this template targets
        |----------------------------------------------------------------------
        |
        | The template moved to PostgreSQL. This connection is kept hardened and
        | working for a fork that must run against MySQL, but the migrations,
        | tests, docker-compose services and CI all target `pgsql`. Setting
        | DB_CONNECTION=mysql is a fork's decision to own, not a supported
        | configuration of the template.
        |
        */
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
            'collation' => env('DB_COLLATION', 'utf8mb4_unicode_ci'),
            'prefix' => '',
            'prefix_indexes' => true,
            'strict' => true,
            'engine' => null,
            'options' => extension_loaded('pdo_mysql') ? array_filter([
                /*
                 * Path to a PEM bundle for the server's CA. Supplying it is what
                 * turns TLS on: with no CA there is nothing to verify against.
                 */
                Pdo\Mysql::ATTR_SSL_CA => env('MYSQL_ATTR_SSL_CA'),

                /*
                 * Certificate verification is ON whenever a CA is configured, and
                 * there is deliberately NO environment variable to turn it off.
                 * An encrypted connection to an unverified peer stops a passive
                 * eavesdropper and does nothing at all about an active one, which
                 * is the threat that TLS to a database over a shared network
                 * exists to address. A "just for now" override is how that ends
                 * up permanent.
                 *
                 * array_filter() drops the entry entirely when no CA is set, so a
                 * plaintext local connection is unaffected.
                 */
                Pdo\Mysql::ATTR_SSL_VERIFY_SERVER_CERT => env('MYSQL_ATTR_SSL_CA') ? true : null,

                /*
                 * Connection timeout in seconds. Without it, an unreachable
                 * database makes every php-fpm worker block on connect() until
                 * the OS gives up, which exhausts the pool and turns a database
                 * problem into a total outage. Bounding it lets the request fail
                 * and the worker return to service.
                 */
                PDO::ATTR_TIMEOUT => (int) env('DB_CONNECT_TIMEOUT', 5),

                /*
                 * Persistent connections are OFF by default and should stay that
                 * way for anything horizontally scaled. A persistent connection is
                 * held per PHP worker process, so the server-side connection count
                 * becomes (replicas x php-fpm workers) whether or not those workers
                 * are serving traffic — which is how a modest deployment exhausts
                 * max_connections while sitting idle. See DEPLOYMENT.md for the
                 * capacity arithmetic.
                 */
                PDO::ATTR_PERSISTENT => filter_var(env('DB_PERSISTENT', false), FILTER_VALIDATE_BOOL) ?: null,
            ], static fn (mixed $value): bool => $value !== null) : [],
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
            'collation' => env('DB_COLLATION', 'utf8mb4_unicode_ci'),
            'prefix' => '',
            'prefix_indexes' => true,
            'strict' => true,
            'engine' => null,
            'options' => extension_loaded('pdo_mysql') ? array_filter([
                Pdo\Mysql::ATTR_SSL_CA => env('MYSQL_ATTR_SSL_CA'),
            ]) : [],
        ],

        /*
        |----------------------------------------------------------------------
        | PostgreSQL — the engine this template targets
        |----------------------------------------------------------------------
        |
        | Standard Laravel/PDO configuration, driven entirely by environment
        | variables. Nothing here knows whether the server is a managed database
        | service, a container or a process on a VM — the application only needs a
        | reachable PostgreSQL endpoint.
        |
        */
        'pgsql' => [
            'driver' => 'pgsql',
            'url' => env('DB_URL'),
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', '5432'),
            'database' => env('DB_DATABASE', 'laravel'),
            'username' => env('DB_USERNAME', 'postgres'),
            'password' => env('DB_PASSWORD', ''),
            'charset' => env('DB_CHARSET', 'utf8'),
            'prefix' => '',
            'prefix_indexes' => true,
            'search_path' => env('DB_SEARCH_PATH', 'public'),

            /*
             * TRANSPORT SECURITY.
             *
             * `prefer` — libpq's default and Laravel's stock value — is the
             * dangerous one: it negotiates TLS when the server offers it and
             * SILENTLY FALLS BACK to plaintext when it does not, so a
             * misconfigured server, or an attacker who can strip the negotiation,
             * downgrades the connection with nothing logged and nothing to notice.
             *
             * The default here is `require`, which refuses to connect in
             * plaintext. Note that `require` encrypts but does NOT verify the
             * server's identity; for a managed database reachable over a network
             * you do not control, set DB_SSLMODE=verify-full and supply
             * DB_SSL_ROOT_CERT. app:check-config warns when production is not on
             * a verifying mode.
             *
             * Local development overrides this to `disable` in .env.example,
             * where the database is a container on a private compose network.
             */
            'sslmode' => env('DB_SSLMODE', 'require'),

            /*
             * CA bundle used to verify the server certificate. Required for
             * verify-ca / verify-full when the certificate chains to a private CA;
             * with a publicly trusted certificate the system trust store is used.
             */
            'sslrootcert' => env('DB_SSL_ROOT_CERT'),

            /*
             * Client certificate and key, for an endpoint that requires mutual
             * TLS. Unset for password authentication.
             */
            'sslcert' => env('DB_SSL_CERT'),
            'sslkey' => env('DB_SSL_KEY'),

            /*
             * Identifies this application in pg_stat_activity, so a DBA looking at
             * an overloaded server can tell which workload the connections belong
             * to. Distinguishing api from worker is worth the two seconds it
             * takes to set.
             */
            'application_name' => env('DB_APPLICATION_NAME', env('APP_NAME', 'laravel')),

            'options' => extension_loaded('pdo_pgsql') ? array_filter([
                /*
                 * Connection timeout in seconds. Without it, an unreachable
                 * database makes every php-fpm worker block on connect() until
                 * the OS gives up, which exhausts the pool and turns a database
                 * problem into a total outage. Bounding it lets the request fail
                 * and the worker return to service.
                 */
                PDO::ATTR_TIMEOUT => (int) env('DB_CONNECT_TIMEOUT', 5),

                /*
                 * Persistent connections are OFF by default and should stay that
                 * way for anything horizontally scaled. A persistent connection is
                 * held per PHP worker process, so the server-side connection count
                 * becomes (replicas x php-fpm workers) whether or not those workers
                 * are serving traffic — which is how a modest deployment exhausts
                 * max_connections while sitting idle. See DEPLOYMENT.md for the
                 * capacity arithmetic.
                 */
                PDO::ATTR_PERSISTENT => filter_var(env('DB_PERSISTENT', false), FILTER_VALIDATE_BOOL) ?: null,
            ], static fn (mixed $value): bool => $value !== null) : [],
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

    /*
    |--------------------------------------------------------------------------
    | Redis
    |--------------------------------------------------------------------------
    |
    | ONE set of generic connection variables serves every Redis-compatible
    | backend — a local container, an authenticated managed instance, a TLS
    | endpoint with a private CA. There are deliberately no provider-named
    | variables here: the application asks for "a Redis-compatible backend", and
    | which product answers is a deployment concern.
    |
    | Every connection is composed from the same $redisConnection() helper, so a
    | TLS or credential change applies to the cache, the queue, Horizon, locks and
    | rate limiting at once. Configuring one and forgetting another is the failure
    | this shape removes.
    |
    */
    'redis' => [

        'client' => env('REDIS_CLIENT', 'phpredis'),

        'options' => [
            'cluster' => env('REDIS_CLUSTER', 'redis'),
            'prefix' => env('REDIS_PREFIX', Str::slug(env('APP_NAME', 'laravel'), '_').'_database_'),
        ],

        'default' => $redisConnection(env('REDIS_DB', '0')),

        /*
         * A separate logical database so `cache:clear` cannot wipe queued jobs,
         * Horizon's metadata or a lock somebody is holding.
         */
        'cache' => $redisConnection(env('REDIS_CACHE_DB', '1')),

    ],

];
