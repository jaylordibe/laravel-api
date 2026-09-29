---
name: feature-testing
user-invocable: false
description: Use when writing or running tests (tests/Feature/*, tests/Unit/*) — the Docker-backed live PostgreSQL test database, the ./test.sh wrapper and single-test form, authenticating with Passport::actingAs / actingAsSystemAdmin (real sign-in only for token tests), model factories, PHPUnit attribute style (#[Test]), and assertion conventions.
---

# Tests

Tests run **inside the `laravel-api` container against a real PostgreSQL test database** (`laravel-db-test`) — not sqlite, not in-memory. Suites are `tests/Unit` and `tests/Feature`; the split is by subject, not isolation. Feature tests and any unit test that touches the container or database extend `Tests\TestCase`; pure unit tests may extend `PHPUnit\Framework\TestCase`.

## Isolation (`Tests\TestCase`)

`Tests\TestCase` uses Laravel's `RefreshDatabase`:
- The first test in a process migrates a fresh database and seeds it with `TestDatabaseSeeder` (the app's `DatabaseSeeder` plus the Passport personal access client sign-in needs). **Never migrate or seed the test database by hand.**
- Every test then runs inside a transaction that is rolled back — no test sees another's rows, and a test may freely create, change or delete data, including roles and permissions.
- Under `--parallel` each worker gets its own database (`laravel_test_test_N`).
- `phpunit.xml` forces the standard test environment: array cache/session/mail, sync queue, `BCRYPT_ROUNDS=4`.

## Running tests

- **Full run** — `./test.sh` (clears cached config/routes, then `php artisan test --parallel`).
- **Single class/method** — `./test.sh <FilterName> <path>`, e.g. `./test.sh AppVersionFeatureTest tests/Feature/AppVersionFeatureTest.php` (uses `--filter`, runs `--parallel --functional`).
- **CI** — `./test-pipeline.sh`.
- **One run at a time per stack.** Every run rebuilds its test database(s) on start, so two overlapping runs (two agents, or an agent and a developer) drop each other's tables mid-run and fail at random.
- No TTY (an agent): `docker exec laravel-api bash -c "php artisan test --compact --filter=XFeatureTest"`.

## Harness helpers

- Authenticate with **`Passport::actingAs($user)`**, or `$this->actingAsSystemAdmin()` for the seeded sysad. Prefer a factory user with the role under test (`User::factory()->withRole(UserRole::X)->create()`) when the actor matters.
- Only tests about sign-in, tokens or sessions sign in for real: `login($identifier, $password = 'password')` returns a bearer token for `withToken()`, and `forgetAuthenticatedUsers()` resets auth between such requests.

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
        $this->actingAsSystemAdmin();
        $payload = [ /* build via factories for FK ids */ ];
        $response = $this->post($this->resource, $payload);
        $response->assertCreated()->assertJson([ /* echoed fields */ ]);
    }

    #[Test]
    public function testGetPaginated(): void
    {
        $this->actingAsSystemAdmin();
        X::factory()->count(15)->create();
        $response = $this->get($this->resource);
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
