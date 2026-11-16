# ADR 004: Single Data Access Layer

## Status

Accepted (2026-11-16)

## Context

Earth Empires has its own MysqlDb and sessions; the core's data classes matter most to the apps that
use them directly (demo3, sterling) and to any new code. When code reaches for `mysqli`, `apcu_*`
or `curl_*` itself, it skips the escaping, timing log (`MysqlDb::$queries`, `Cache::$queries`) and
error handling the core already has, and a later change (PHP 8 mysqli exceptions, #9; a reconnect,
#7) becomes a repo-wide audit.

## Decision

1. All database, cache and outbound HTTP access goes through the layers below. Nothing outside
   `MysqlDb` calls `mysqli`; nothing outside `Cache` calls `apcu_*`; nothing outside `Script` calls
   `curl_*`.
2. Connections come only from `Container::getDb()`. No `new MysqlDb(...)` elsewhere.
3. Every query with a value in it goes through `MysqlDb::pquery()` / `pqueryArray()` with `?`
   placeholders, so `prepare()` / `preparePart()` escape it. No value is concatenated into SQL.
4. Identifiers (table and column names) are never taken from request data. Where a query is built
   from names (`CRUD`, `M2M`), the names come from the class definition or a fixed list in code.
5. SQL text lives in the classes that own the table (domain classes and the `CRUD`/`M2M` base
   classes). Routes, page functions, `Auth` and `Router` call domain methods such as
   `User::getUserIdFromName()`; they do not write SQL.
6. A new data operation is added as a method on the owning class, never as a query in the caller. If
   it is slow because of many round trips, the fix is a batch method there (ADR 006).
7. Code moved into the layer is reshaped to fit it (placeholders, owning class), not pasted.

### Layers

| Level | Classes | Owns |
|---|---|---|
| Executor | `MysqlDb` (`query`, `pquery`, `pqueryArray`, `prepare`, `preparePart`), `MysqlDbResult` | the mysqli connection, escaping, query timing, `queryError()` |
| Connection | `Container::getDb()` | one `MysqlDb` per database name, from `Container::$params['db']` |
| Generic table access | `CRUD` (one row by primary key), `M2M` (link tables) | query assembly from class-defined names |
| Domain | `User`, `Group`, `Action`, `Session`, `MigrationManager`, `Migrations` | the SQL for their tables |
| Cache | `Cache` (APCu, `add`/`store`/`fetch`/`delete`, serial and JSON variants) | all `apcu_*` calls and key timing |
| Outbound HTTP | `Script::post()` / `Script::get()` | all `curl_*` calls |
| Callers | `Router`, `Auth`, consuming apps' page functions | no SQL, no `apcu_*`, no `curl_*` |

`MysqlDbQueryBuilder` is an unused stub (its `setDb()` discards the default); do not build on it.

### Known legacy (fix when touched)

- `preparePart()` returns numeric strings unquoted, so `string_col = ?` with `"0"` compares
  numerically: tracked in #4.
- `CRUD` interpolates column names unescaped (`addByArray()`, `filterColumn()`, `getUniqueId()`,
  `filterPKArray()`), so a caller passing request keys (`addByArray($_POST)`) breaks rule 4: #8.
- `M2M` keeps `$tableName` / `$primaryKeys` static, so a second instance redirects the first: #8.
- `Container::getDb()` runs `createIfNotExists()` (a `SHOW DATABASES`, possibly `CREATE DATABASE`)
  on every call: #7.

## Rationale

One layer means escaping, timing and error handling are applied once, the rule can be checked by a
search (`mysqli`, `apcu_`, `curl_`, SQL keywords outside the classes above), and the PHP 8 mysqli
change is a one-class fix.

## Consequences

- Adding any I/O means adding a method to the owning class first.
- Legacy code: active immediately for new and modified code; the violations above are on-touch debt,
  not a sweep. Mark a decided replacement `TODO(ADR-004): ...`.

## Exceptions

- Error logging (`ErrorHandler.function.php`) does its own file I/O, because it must work when the
  data layer is what failed.
- Tests, `setup/` and developer tooling.

## Related

ADR 003 (error handling), ADR 005 (ownership checks live in this layer), ADR 006 (batching).
