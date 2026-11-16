# Architecture Decision Records

Rules for this repo, adopted from the general ADRs in `jhaagsma/claude-skills` (`adr/`) and adapted
to EmPHyre. Where this copy differs from the general one, this copy wins.

This is a legacy codebase: each rule applies to new code and to code touched for another reason, not
through a sweep. ADR 005 (ownership) is the exception: gaps are closed deliberately. A known
violation whose fix is decided may be marked `TODO(ADR-NNN): what should change`.

| No. | Title | Rule in one line | From general ADR |
|---|---|---|---|
| [001](001-regression-tests-for-bug-fixes.md) | Regression tests for bug fixes | A fix to deterministic logic ships with a PHPUnit test seen to fail first, or says why not | 002 |
| [002](002-unit-test-standards.md) | Unit test standards | New or extracted pure functions get tests; messages on boolean asserts; every test has a named way to fail | 003 |
| [003](003-error-handling-and-recovery.md) | Error handling and recovery | Return or raise; `Result` only for expected user-facing outcomes; no silent catch; no SQL or paths shown to users | 006 |
| [004](004-single-data-access-layer.md) | Single data access layer | `mysqli` only in `MysqlDb`, `apcu_*` only in `Cache`, `curl_*` only in `Script`; values via `pquery()` placeholders | 016 |
| [005](005-ownership-checks-on-every-write.md) | Ownership checks on every write | Every write checks the row belongs to the authenticated `User`; identity never from request data | 022 |
| [006](006-bounded-and-batched-queries.md) | Bounded and batched queries | No query per row; `IN (?)` or joins; every growing list has a `LIMIT` | 023 |

New ADRs: copy `000-template.md` from the general set, take the next number, add a row here and a
line in the root `CLAUDE.md`.
