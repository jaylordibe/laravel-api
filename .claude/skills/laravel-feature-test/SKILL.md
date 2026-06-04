---
name: laravel-feature-test
description: Use when writing or running tests (tests/Feature/*, tests/Unit/*) — the Docker-backed live MySQL test database, the ./test.sh wrapper and single-test form, the Tests\TestCase auth helpers (loginSystemAdminUser / login / getAuthUser), model factories, PHPUnit attribute style (#[Test]), and assertion conventions.
---

# Tests

Tests run **inside the `laravel-api` container against a real MySQL test database** (`laravel-db-test`) — not sqlite, not in-memory. `phpunit.xml` sets `APP_ENV=testing`. Suites are `tests/Unit` and `tests/Feature`; the split is by subject, not isolation — both hit the DB via factories.

## Running tests

Always go through the wrapper (handles the container + test DB):
- **Full run** — `./test.sh` (migrate:fresh --seed --env=testing, recreate passport clients, clear caches, `php artisan test --parallel`).
- **Single class/method** — `./test.sh <FilterName> <path>`, e.g. `./test.sh AppVersionFeatureTest tests/Feature/AppVersionFeatureTest.php` or `./test.sh testCreate tests/Feature/AppVersionFeatureTest.php` (uses `--filter`, runs `--parallel --functional`).
- **CI** — `./test-pipeline.sh`.

The full run rebuilds + reseeds the DB (slow) — while iterating on one module run only the affected test via the single-test form. Run the full suite when a module is complete, before a deploy, or when asked.

If you can't use `./test.sh` because the harness has no TTY (it uses `docker exec -it`), replicate it without `-t`:
```
docker exec laravel-api bash -c "php artisan test --filter=XFeatureTest --env=testing"
```
(ensure the test DB is migrated first: `docker exec laravel-api bash -c "php artisan migrate:fresh --seed --env=testing"`).

## Harness (`Tests\TestCase`)

Extend `Tests\TestCase`:
- `login(string $identifier, string $password = 'password'): string` — POSTs `/api/auth/sign-in`, returns the bearer token.
- `loginSystemAdminUser(): string` — logs in the seeded sysad (`config('custom.sysad_email')` / `sysad_password`); the default actor for most tests.
- `getAuthUser(string $token): UserData` — fetches `/api/users/auth`.

Authenticate with `$this->withToken($token)->post(...)` / `->get(...)` / `->put(...)` / `->delete(...)`.

## Writing a feature test

```php
namespace Tests\Feature;

use App\Models\X;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class XFeatureTest extends TestCase
{
    private string $resource = '/api/<resources>';

    #[Test]
    public function testCreate(): void
    {
        $token = $this->loginSystemAdminUser();
        $payload = [ /* build via factories for FK ids */ ];
        $response = $this->withToken($token)->post($this->resource, $payload);
        $response->assertCreated()->assertJson([ /* echoed fields */ ]);
    }

    #[Test]
    public function testGetPaginated(): void
    {
        $token = $this->loginSystemAdminUser();
        X::factory()->count(15)->create();
        $response = $this->withToken($token)->get($this->resource);
        $response->assertOk()->assertJsonStructure(['data', 'links', 'meta']);
    }
}
```

Conventions:
- Mark tests with the **`#[Test]` attribute**.
- Use **model factories** for setup (incl. FK ids: `X::factory()->create()->id`); `fake()` for values; enum values via `fake()->randomElement(SomeEnum::cases())->value`.
- Assert with `assertCreated()` / `assertOk()` + `assertJson([...])` (subset) or `assertJsonStructure([...])` (paginated lists expose `data`/`links`/`meta`).
- **Errors** (thrown `BadRequestException` / validation / `ProcessingException`) all return `{"success": false, "message": ...}` — assert on `success`/`message`, not an `error` key. `getById` on a missing record is a 400, not 200.
- BigDecimal fields serialize as numbers/strings — assert on the value the API returns, don't recompute with float math.
