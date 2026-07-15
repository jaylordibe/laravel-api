---
name: laravel-new-resource
description: Use when adding or scaffolding a new CRUD resource (Controller + Request + Data + FilterData + Service + Repository + Model + Resource + migration + factory + tests) in this Laravel API template. Covers the app:generate-resource scaffolder, the strict layered pipeline, the BadRequestException service convention, the XData/XFilterData + MetaData query envelope, repository save()/getPaginated() conventions, BaseModel audit columns, table-name constants, and route registration.
---

# New resource (canonical layered pattern)

Every resource follows the **same** pipeline. Trace `AppVersion` (cleanest CRUD) or `User` (adds auth) before writing a new one — controller, service, repository, request, data, model, resource. Do not invent alternate patterns.

```
Route (routes/api.php)
  → Controller (thin)            app/Http/Controllers/<X>Controller.php
    → Request (validate + map)   app/Http/Requests/<X>Request.php   extends BaseRequest
      → Data (typed transport)   app/Data/<X>Data.php + <X>FilterData.php   extends BaseData
        → Service (business)     app/Services/<X>Service.php
          → Repository (DB)      app/Repositories/<X>Repository.php
            → Model              app/Models/<X>.php   extends BaseModel
  → Resource (JSON shape)        app/Http/Resources/<X>Resource.php
```

**Validate the ask before you scaffold.** A request for "a new resource" is an outcome, not always the right shape — confirm the model/table/endpoints are genuinely new (not an extension of an existing resource, and not contradicted by current migrations/routes) before generating. If the prescribed shape is wrong or a better fit exists, recommend it *with the trade-off* first; scaffolding the wrong resource is still wrong.

## Scaffold first — ALWAYS use the generator

This is the project owner's own tool and the required starting point — do NOT hand-write the layers from scratch. Run it FIRST (the container is normally up; check `docker ps`):

```
docker exec laravel-api bash -c "php artisan app:generate-resource <ModelName>"
```

(`jaylordibe/laravel-resource-generator`.) It stamps Controller/Request/Data/FilterData/Service/Repository/Resource/Model/migration/factory + Unit & Feature test stubs, and — when the Model does not yet exist — it ALSO auto-updates `DatabaseTableConstant` and registers the route block in `routes/api.php`. So generate BEFORE creating any of those files yourself; if the model already exists it skips files and will NOT wire the route. Then fill in the stubs (migration columns, model `casts()`/relations, request `rules()` + `toData()`/`toFilterData()`, Data/FilterData fields, service rules, repository queries, Resource relations, factory, test payloads). The generated route block uses `config('custom.numeric_regex')` and tabs — just fix the tab indentation to 4 spaces.

## Per-layer responsibilities

**Controller** — constructor-injects the Service only. Build typed data via `$request->toData()` / `toFilterData()`, call the service, wrap with `ResponseUtil`. No business logic, no Eloquent, **no error branching**:
- create/getPaginated/getAll/getById/update → `return ResponseUtil::resource(XResource::class, $result);`
- delete / action endpoints → `$this->xService->delete($id); return ResponseUtil::success('X deleted successfully.');`
- list/get with no typed request → accept `GenericRequest`, re-hydrate via `XRequest::createFrom($request)->toFilterData()`.

**Request** (`extends BaseRequest`) — `rules()`, `messages()`, `toData()`, `toFilterData()`. Use `BaseRequest` helpers, never raw input: `bigDecimal()`, `integer()`, `string()`, `enum($key, Enum::class, $default)`, `boolean($key, $default)`, `arrayIds()`. Always set `id: $this->route('<x>Id')`, `authUser: $this->getAuthUserData()`, `meta: $this->getMetaData()`. Validate enums with `Rule::enum(...)`.

**Data** (`extends BaseData`) — `XData` (full record) and `XFilterData` (list params). `BaseData` already carries `id`, audit fields, `authUser`, `meta` — don't redeclare. **`MetaData`** is the universal query envelope (relations/columns/search/sortField/sortDirection/page/perPage/offset/groupBy/filters); repositories read list behavior off `$filterData->meta`.

**Service** — the only place for business rules. **Throw `App\Exceptions\BadRequestException('message')` on any failure** (400, rendered as `{"success":false,"message":...}`). Use `ProcessingException` (422) for a downstream/processing failure when you want to distinguish it. Return payloads directly: `create`/`update`/`getById` → `?X`; `getPaginated` → `LengthAwarePaginator`; `getAll` → `Collection`; `delete` → `bool`; action endpoints → `void`. For `getById`, fetch then `if (empty($x)) { throw new BadRequestException('X not found.'); }`. Never reintroduce a `ServiceResponseData`/`failed()` pattern. In a `try/catch`, re-throw `BadRequestException` before the generic `catch (Throwable)` so its message isn't swallowed.

**Repository** — plain class, no shared base. `save(XData $data, ?Model $model = null): ?X` (instantiate when null, assign each column explicitly — `$fillable` is empty — `save()`, `return $model->refresh()`). `findById()`, `exists()`, `getPaginated()` (apply `meta->relations`/`columns`/explicit filters/`search`/`sortField` + `meta->sortDirection ?? AppConstant::DEFAULT_SORT_DIRECTION`, then `->paginate($data->meta->perPage)`), `getAll()`, `delete()`. Domain finders live here too.

**Model** (`extends BaseModel`) — `protected $table = DatabaseTableConstant::<X>;`, `protected $fillable = [];`, a `casts()` method (enums → enum class, money → `BigDecimalCast::class`), relation methods, and a class-level `@property` PHPDoc block. `BaseModel` provides `SoftDeletes`, `HasFactory`, and auto-stamps `created_by`/`updated_by`/`deleted_by` from `Auth::id()` — don't re-implement.

**Resource** — shape the JSON; expose relations with `$this->whenLoaded('rel')`.

## Money / decimals

Any money/decimal column is `Brick\Math\BigDecimal`, never float — cast with `BigDecimalCast`, parse input with `BaseRequest::bigDecimal()`, divide via `App\Utils\MathUtil::divide()`. See the `laravel-money-precision` skill.

## Tests

Every resource gets a feature test (and a unit test for non-trivial calc). See `laravel-feature-test` for the harness.

## Formatting — `php artisan app:format`, never Pint

The project style is enforced by a first-class artisan command (`app/Console/Commands/FormatCommand.php`), NOT Pint. After scaffolding and filling in the stubs, run `docker exec laravel-api bash -c "php artisan app:format"` (verify with `--check`) — this fixes the whitespace/brace/`!` rules mechanically. The `app:generate-resource` stubs already follow the layout, but after any `make:*` generator run `app:format`. The naming (camelCase methods incl. tests), type-hint-everything (closures too), `new Foo()` parens (anonymous migration classes keep none), and full `@param`/`@return`/`@throws` PHPDoc rules are yours to apply by hand — see CLAUDE.md "Code style". Do NOT run Pint: its preset fights these conventions and can't express the blank-line rules.
