<?php

namespace App\Utils;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Throwable;

/**
 * Dependency probes for the readiness endpoint.
 *
 * These live behind a Util rather than in the controller because CLAUDE.md puts
 * all database access in a repository and keeps controllers free of branching —
 * and because the infrastructure boundaries this touches (a raw connection, a
 * Redis PING) are exactly what `app/Utils/<Domain>Util` is for. A controller in
 * the template is reference code that forks copy; it should not be the one place
 * that reaches for `DB::` directly.
 *
 * Every probe is BOUNDED IN TIME and returns a boolean. A readiness check that
 * can hang is worse than none: it holds a php-fpm worker for the pool's request
 * timeout, and enough concurrent hung probes take the replica down instead of
 * reporting on it.
 */
class HealthUtil
{

    /**
     * Verify the database answers a trivial query, within a bounded time.
     *
     * `SELECT 1` rather than a connection check: an established but broken
     * connection — a failed-over primary, an exhausted pool, a dropped socket the
     * driver has not noticed — still reports as connected. Only a round trip
     * proves the path works end to end.
     *
     * The connect timeout in config/database.php bounds the HANDSHAKE only, which
     * covers a database that is down and not one that is merely slow. The
     * dangerous case is a server that accepts the connection and then blocks —
     * during a failover or under saturation — so the statement itself is bounded
     * here with a transaction-local `statement_timeout`. `SET LOCAL` is used
     * deliberately: it reverts when the transaction ends, so this cannot leak a
     * short timeout onto a pooled connection that later runs a real query.
     *
     * @return bool
     */
    public static function isDatabaseReady(): bool
    {
        try {
            $timeoutInMilliseconds = (int) config('custom.health.database_timeout_milliseconds');

            DB::transaction(function () use ($timeoutInMilliseconds): void {
                if (DB::connection()->getDriverName() === 'pgsql') {
                    DB::statement("SET LOCAL statement_timeout = {$timeoutInMilliseconds}");
                }

                DB::select('select 1');
            });

            return true;
        } catch (Throwable $e) {
            report($e);

            return false;
        }
    }

    /**
     * Verify Redis answers a PING.
     *
     * Checked because it is not optional: the queue connection, the cache store
     * and the rate limiters all depend on it, so a replica that cannot reach
     * Redis cannot serve a normal request even though it can still boot. The
     * read/connect timeouts on the connection bound this call.
     *
     * @return bool
     */
    public static function isRedisReady(): bool
    {
        try {
            Redis::connection()->ping();

            return true;
        } catch (Throwable $e) {
            report($e);

            return false;
        }
    }

}
