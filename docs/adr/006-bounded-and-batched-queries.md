# ADR 006: Bounded and Batched Queries

## Status

Accepted (2026-11-16)

## Context

Two query shapes cause most data-access performance problems: N+1 (one query for a list, then one
per row) and unbounded lists over tables that keep growing. Both look fine on a dev database and
fail on a game database years old. The core has examples of both, and it serves Earth Empires, where
most traffic is bots and scrapers, so an unbounded query on a public route is also a cheap way to
load the server.

## Decision

1. No query is issued once per item of a collection. Related rows are fetched for the whole set:
   a join, or one `WHERE id IN (?)` with the id array passed to `pquery()` (`preparePart()` expands
   arrays into a comma list; `CRUD::filterPKArray()` and `M2M::checkArray()` already do this).
   Writes for a set use one statement (`DELETE ... WHERE login_id IN (?)`) where the semantics allow.
2. An empty id array is handled before the query (`IN ()` is a syntax error), as
   `filterPKArray()` does.
3. Every list query over a table that grows with use (`users`, `user_actions`, `user_logins`,
   `active_sessions`, link tables) has a `LIMIT`, with a default and a maximum page size set by the
   method. `LIMIT` and `OFFSET` values are cast to `int` before they reach `pquery()`.
4. A method that accepts `$limit` / `$offset` parameters applies them. A parameter that is accepted
   and ignored is a bug.
5. Batching is the data layer's job (ADR 004): callers may ask in whatever shape is natural, and the
   owning class provides the batch method.
6. Before adding a limit to a query an existing consumer relies on, check what EE, demo3 and
   sterling call it with; do not silently truncate results a caller expects in full.
7. Slow queries are found with MySQL's slow query log and `MysqlDb::$queries` (the last
   `$querystore` = 150 queries with their timings) before guessing.
8. Caching repeated single-row lookups through `Cache` (the idea behind `User::apcUserline()`,
   which is itself broken, #8) is an acceptable interim step, not a substitute for rule 1.

### Known legacy (fix when touched)

- `CRUD::primaryList()` and `filterColumn()` take `$limit` / `$offset` and ignore them; both return
  the whole table or match set (breaks rules 3 and 4). `Action::actions()` has no limit either.
- `Session::clearOldSessions()` selects every expired row and deletes them one by one.
- `Group::alterUserGroups()` and `Group::disable()` add or delete link rows one query per group or
  member.
- Statement timeouts: none are set anywhere (no `max_execution_time` hint, no driver option). Which
  queries need one: TBD.

## Rationale

Batching turns a cost that grows with the number of rows into a fixed number of queries. Bounds make
response time predictable and remove an easy denial of service. Checking consumers first avoids
breaking an app that depends on getting everything.

## Consequences

- Query counts and response sizes stop growing with data volume.
- Callers of list methods must handle paging once limits apply.
- Legacy code: applies to new and touched queries. Fix the worst offenders first (from the slow
  query log or `MysqlDb::$queries`); add limits to list methods as they are touched. Do not rewrite
  every loop.

## Exceptions

- Small fixed loops (a handful of iterations) where batching would obscure the code.
- Maintenance and migration code (`MigrationManager`, `Migrations`) that processes a whole table, in
  chunks.
- Row-by-row work that must stay that way for transactional reasons, with a comment saying so.

## Related

ADR 004 (the data layer owns batching).
