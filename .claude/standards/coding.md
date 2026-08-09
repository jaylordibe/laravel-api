# Coding standard — project edition

`CLAUDE.md` is authoritative.

## Naming

Use full intention-revealing domain names everywhere.

Avoid cryptic abbreviations, vague placeholders, and single-letter locals or bare
index counters except the established idioms `CLAUDE.md` allows (`id`, `url`,
`db`, `ttl`, and `catch (Throwable $e)`).

Declared names — classes, methods, enums, Data objects, files — carry the highest
readability bar, because they are read far more often than a local.

Method and function names are camelCase without exception, including test
methods (`public function testCreate()`, never `test_create`). snake_case is a
database column, an array key, or an enum value — never a PHP identifier.

## Responsibilities

- controllers: transport, route metadata, `ResponseUtil` wrapping;
- Form Requests: validation and typed Data construction;
- Data/FilterData: transport shape;
- services: behaviour and orchestration;
- repositories: every query-builder and Eloquent access;
- models: casts, relations, audit stamping;
- Resources: response shape;
- `app/Utils`: pure reusable transforms;
- `app/Constants` and `config/custom.php`: static tables, registries, defaults.

Do not place large data registries or reusable pure functions above a class body.

## Errors and responses

- services throw `App\Exceptions\BadRequestException` on any failure;
- controllers never branch on a service result;
- every API error keeps the one uniform shape
  (`{"success": false, "message": "…"}`);
- re-throw `BadRequestException` before a generic `catch (Throwable)` so its
  message is not swallowed;
- return Resources, never raw models or query results;
- never expose internal exception text, SQL, or driver detail to a client.

## Data and persistence

- validate and normalise at the Request boundary;
- never trust client-computed authoritative fields;
- keep `$fillable` empty and assign explicitly in repositories;
- constrain route ids with `config('custom.numeric_regex')`;
- include ownership/tenant scope in the query, not only in a check;
- address concurrency on read-modify-write flows;
- avoid N+1 — apply `meta->relations` through `->with()`;
- avoid unbounded reads; list endpoints paginate;
- keep pagination deterministic;
- money and decimals use `Brick\Math\BigDecimal` with `BigDecimalCast`,
  `BaseRequest::bigDecimal()`, and `MathUtil::divide()` — never float.

## External operations

- wrap every integration behind an `app/Utils/<Domain>Util` boundary;
- credentials come from config, never `env()` at the call site;
- explicit timeouts on every `Http` call;
- bounded transient retries;
- idempotent retried writes;
- duplicate-safe queued jobs;
- safe payload and log handling.

## Style

`php artisan app:format` is the formatter — **never Pint**. Apply it after every
change and verify with `--check` before declaring done. The rules it cannot
enforce mechanically are still yours: naming, full type hints on every parameter,
return type, closure and arrow function, `new Foo()` parentheses, and complete
PHPDoc (`@property` blocks, `@param`/`@return`/`@throws`).

## Completion hygiene

No debug output, `dd()`/`dump()`/`ray()` leftovers, TODO deferrals for in-scope
migration, focused or skipped tests, commented-out code, obsolete parallel
implementations, or accidental `composer.lock` changes.
