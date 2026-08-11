<!--
  Repository-owned worksheet. The engineering-framework plugin ships the
  generic version (templates/data-design.md); this one is the Laravel/Eloquent
  specialisation of it and names the actual base classes, casts and audit
  columns this API uses. Fill this in for schema and migration work.
-->

# Database design: [Change]

- **Related plan/ticket:**
- **Owner:**
- **Framework:** Laravel 13 (Eloquent)
- **Database:** MySQL
- **Risk:** Low | Medium | High | Critical

## Model lifecycle

| Model | Owner/context | Soft/hard delete | Ownership scope | Audit columns |
|---|---|---|---|---|
| | | `SoftDeletes` via `BaseModel` | | `created_by`/`updated_by`/`deleted_by` |

## Schema

| Column | Type | Null/default | Invariant | Cast | Sensitive |
|---|---|---|---|---|---|
| | | | | | |

- `DatabaseTableConstant` entry:
- `casts()` additions (method, never a `$casts` property):
- `BigDecimalCast` on every money/decimal column:
- `@property` PHPDoc block updated:
- `$fillable` stays empty; assignment is explicit in the repository:

## Constraints and indexes

| Invariant/query | Constraint/index | Covers | Notes |
|---|---|---|---|
| | | | |

Index what the repository actually filters, sorts, and searches on — including
the ownership predicate, not only the primary lookup.

## Query and authorization

- repository method(s):
- ownership predicate:
- soft-delete behaviour (default scope, `withTrashed` uses):
- eager loading via `meta->relations`:
- selected columns via `meta->columns`:
- pagination/order:
- N+1 risk:
- index support:

## Transactions and concurrency

- invariant boundary:
- `DB::transaction` scope:
- isolation / lock / version column:
- lost-update prevention:
- duplicate/idempotency:
- counter semantics:
- deadlock/retry behaviour:

## Migration

- new migration filename (`php artisan make:migration`):
- **every attribute restated** on a modified column (nullable, default, length,
  unsigned, charset):
- expand step:
- application rollout:
- bounded/resumable backfill:
- constraints/indexes added:
- contract/removal step:
- mixed-version behaviour:
- lock/downtime/storage risk:
- abort threshold:
- `down()` correctness, or the roll-forward plan replacing it:

Never edit a migration that has already run anywhere. Never run a migration
against the development database as part of design or implementation — the test
harness owns the database that may be rebuilt.

## Verification

- `./test.sh` against the test database:
- SQL review of the generated migration:
- clean database migration:
- migration against representative existing data:
- uniqueness behaviour:
- soft-delete visibility:
- ownership isolation:
- concurrency:
- query plan / performance:
- reconciliation:
