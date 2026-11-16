# eemphyre-core

**EmPHyre**, the small PHP microframework under Earth Empires (namespace `EmPHyre`). Owner: Julian
(GitHub `jhaagsma`). Started by Timo Ewalds and Julian for EE, later spun out; Dave McVittie and
Joe Obbish (slagpit) contributed. MIT.

## Who uses it

- **Earth Empires** (production, players worldwide) as a git submodule. EE has its own MysqlDb and
  sessions; before changing a public method, check whether EE calls it. A change reaches EE when
  its submodule pointer is bumped, so a merge to `master` is one step from production.
- **demo3** and **sterling**: old consumers (submodule `eemphyre-core`), last touched 2019 and 2016.
  They use the core's `MysqlDb`, `CRUD`, `Session`, `User` and `Auth` directly.

## Constraints

- **PHP 7.2**: production runs 7.2 and every file (tests included) must stay 7.2-compatible: no
  typed properties, arrow functions, `??=`, union types, `match`, named arguments. Local CLI here is PHP 8.3, so "it runs"
  locally proves nothing about 7.2.
- PHP 8 migration comes later and is tracked in #9; do not mix 8-only fixes into other PRs.
- No runtime dependencies. Composer is dev-only (PHPUnit); apps load the core through
  `Autoloader.php`, never `vendor/autoload.php`.

## Layout

- `Autoloader.php`: PSR-4-style loader; `Autoloader::register()` maps `EmPHyre\` to `*.class.php` in
  the root, `Routing/` and `Cache/`. Defines `EMPHYRE_DIR`.
- Data: `MysqlDb` (`pquery()` / `pqueryArray()` with `?` placeholders, escaping in `preparePart()`),
  `MysqlDbResult`, `Container::getDb()`, base classes `CRUD` and `M2M`. `MysqlDbQueryBuilder` is an
  unused stub.
- Cache: `Cache/Cache.class.php` (APCu wrapper).
- Routing: `Routing/` (`Router`, `RouteCacher`, `TreeBuilder`, `PathNode`, `Path`, `Route`,
  `TypeValidator`, `FourOhFour`; `Cors` on branch `cors-allowlist`).
- Sessions and auth: `Session`, `Auth`, `User`, `Password`, `Group`, `Validate`, `Result`.
- Errors: `trigger_error` throughout; `ErrorHandler.function.php` and `errorlog.php` are broken (#7).
- Other: `Action`, `Math`, `Profiler`, `Script` (outbound HTTP via curl), `Settings`, `URL`,
  `Migrations` / `MigrationManager` (dormant), `setup/` (Apache example), `ruleset/` (PHPCS).

## Tests

PHPUnit, dev only: `composer install && vendor/bin/phpunit` (config `phpunit.xml.dist`, tests in
`tests/*Test.php`, bootstrap `tests/bootstrap.php`). **Exists on branch `cors-allowlist`, not yet on
`master`.** PHPUnit ^8.5 runs on 7.2, ^9.6 on newer PHP. Tests must never touch a live database.

## Branches and PRs

- `master` is the main branch. Work on a branch, open a PR into `master`; no direct commits.
- Review loop: review the PR (`pr-review` or `full-review`), fix, and re-review after the fixes.
  Repeat until a round finds no correctness or security problems in the diff.
- Problems found in code the PR did not change go to GitHub issues, not into the PR.
- Open issues: #4 `preparePart()` / Session lookup, #5 session keys and password hashing, #6 cookies
  and CSRF, #7 robustness (errors, reconnect, `getDb()` CREATE DATABASE, RouteCacher `HTTP_HOST`),
  #8 bugs (M2M statics, `User::edit()`, CRUD identifiers, TypeValidator), #9 PHP 8.

## ADRs (docs/adr/)

Legacy code: the rules apply to new and touched code, no sweeps (except 005). Full text and known
violations in each file; index in `docs/adr/README.md`.

- **001 Regression tests for bug fixes**: a fix to deterministic logic ships with a PHPUnit test
  seen to fail first; otherwise the commit says why ("no test: needs live MySQL").
- **002 Unit test standards**: new or extracted pure functions get tests; messages on
  `assertTrue`/`assertFalse`/`assertNull`; reset statics in `setUp()`; every test has a named way
  to fail.
- **003 Error handling**: return or raise; `Result` only for expected user-facing outcomes; no new
  `false`-means-error, no `@`, no empty catch; never show SQL or paths to users.
- **004 Single data access layer**: `mysqli` only in `MysqlDb`, `apcu_*` only in `Cache`, `curl_*`
  only in `Script`; connections from `Container::getDb()`; values only via `pquery()` placeholders;
  identifiers never from request data.
- **005 Ownership checks on every write**: every update/delete checks the row belongs to the
  authenticated `User` (from `Session::doUserCheck()`); identity never from request data or an
  unverified cookie. Security rule: gaps get issues and fixes, not on-touch.
- **006 Bounded and batched queries**: no query per row (use `IN (?)` or a join); every list over a
  growing table has a `LIMIT`; accepted `$limit` parameters are applied.

## Review skills (installed in ~/.claude/skills)

- `full-review`: every reviewer on the branch, one consolidated report. Use before opening a PR.
- `pr-review`: sanity check of a PR before merge, including whether earlier findings were addressed.
- `adr-review`: checks a diff against `docs/adr/`; also part of `full-review`.
- `playbook-bug`: workflow for small fixes (most of #4 to #8).
- `playbook-cleanup`: non-functional changes (dead code, warnings, ADR compliance fixes).
