<?php

namespace App\Utils;

use Illuminate\Support\Facades\DB;

/**
 * Small helpers for the handful of places where SQL genuinely differs between
 * database engines.
 *
 * This exists so repositories can stay engine-agnostic without sprinkling driver
 * checks through their query building. Keep it small: anything that needs more
 * than a couple of helpers here is a sign the query belongs in the query builder
 * rather than in raw SQL.
 */
class DatabaseUtil
{

    /**
     * The operator that performs a CASE-INSENSITIVE pattern match on the current
     * connection.
     *
     * This is not a portability nicety, it is a behaviour fix. On MySQL, `LIKE`
     * is case-insensitive because the column collation (utf8mb4_unicode_ci) makes
     * it so. On PostgreSQL, `LIKE` is case-SENSITIVE and the equivalent operator
     * is `ILIKE`. Moving this template from MySQL to PostgreSQL therefore turned
     * every `LIKE '%term%'` search into one that silently stopped matching
     * anything the user did not capitalise exactly — a change with no error, no
     * failing query and no log line.
     *
     * @return string
     */
    public static function caseInsensitiveLikeOperator(): string
    {
        return match (DB::connection()->getDriverName()) {
            'pgsql' => 'ilike',
            default => 'like',
        };
    }

    /**
     * Escape the wildcard characters in a user-supplied search term so they are
     * matched literally.
     *
     * Without this, a search for `_` matches every single character and a search
     * for `%` matches everything — so the "no results" case a user expects
     * becomes "every row in the table". The term is still passed as a bound
     * parameter by the caller, so this is about correctness of the match, not
     * about SQL injection.
     *
     * Backslash is the default LIKE escape character on both PostgreSQL and
     * MySQL, so no explicit ESCAPE clause is needed. Backslash itself is escaped
     * first, otherwise it would escape the escapes added afterwards.
     *
     * @param string $term - the raw search term from the request
     *
     * @return string - the term with LIKE wildcards neutralised
     */
    public static function escapeLikeWildcards(string $term): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $term);
    }

    /**
     * Build a "contains" pattern from a user-supplied search term, with its
     * wildcards escaped.
     *
     * @param string $term - the raw search term from the request
     *
     * @return string - a `%term%` pattern safe to bind to a LIKE/ILIKE comparison
     */
    public static function containsPattern(string $term): string
    {
        return '%' . self::escapeLikeWildcards($term) . '%';
    }

}
