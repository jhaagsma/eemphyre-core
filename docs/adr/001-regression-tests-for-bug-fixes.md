# ADR 001: Regression Tests for Bug Fixes

## Status

Accepted (2026-11-16)

## Context

A fix verified by hand can be undone later by an unrelated refactor, and in this repo nobody would
notice until a consuming app (Earth Empires, demo3, sterling) hit it again. Much of the fixable logic
here is mechanical and cheap to test in isolation: `MysqlDb::preparePart()` quoting,
`TypeValidator::validate()` coercion, `Cors::headerLines()`, `Result` messages, route tree building.
Other fixes sit in code that needs a live MySQL server, APCu, cookies or a web server, where a test
would be brittle. The policy takes the cheap wins without demanding busywork.

Test harness: PHPUnit, installed by composer as a dev dependency (`composer install &&
vendor/bin/phpunit`, config `phpunit.xml.dist`, tests in `tests/*Test.php`, bootstrap
`tests/bootstrap.php` which registers the `Autoloader`). **This exists on branch `cors-allowlist`
and is not on `master` yet**; until it merges, a branch that adds the first test brings the harness
with it. Composer and PHPUnit are never needed at runtime: the consuming apps load the core through
`Autoloader.php` only.

## Decision

1. When fixing a bug, ask: "Can I write a test that fails before the fix and passes after?" If yes,
   the fix includes that test.
2. The test reproduces the input that triggered the bug and asserts the correct output, not merely
   that nothing errors.
3. The test has been seen to fail once: run against the pre-fix code or with the fix reverted. The
   commit body records it in one line ("fails on `<sha>` with `<message>`").
4. The test is named after the bug's behavior, not after the fix, and carries a comment with the
   GitHub issue when one exists (`// Regression: #8`).
5. If the buggy logic is buried in a method that also queries the database, reads cookies, sends
   headers or calls `exit`, the deterministic part is extracted into a function with no I/O and the
   test targets that (the pattern `Cors::headerLines()` follows on `cors-allowlist`).
6. When a refactor extracts a new pure function, that function gets a unit test at extraction time.
   A purely structural move with no logic change needs no new test.
7. Regression tests live in `tests/` and run with `vendor/bin/phpunit`. There is no CI yet, so the
   suite is run before every PR and in its review; a test kept outside the suite does not count.
8. When a fix ships without a test, the commit message says why in a few words ("no test: needs
   live MySQL").
9. After fixing a bug, search the repo for other instances of the same pattern before committing
   (for example every caller of `preparePart()`'s numeric shortcut, #4).
10. Tests must run under PHP 7.2 (PHPUnit 8.5) as well as newer PHP: no syntax newer than 7.2 in
    `tests/` either.

## Rationale

The broken version already exists when a bug is fixed, so the "red" step is free and proves the test
can detect the bug. Extraction for testability makes the fix sturdier. The suite then grows around
the places that have really broken; the open review issues (#4 to #9) are the first candidates.

## Consequences

- Known-bad inputs stay covered, and bug-fix commits document themselves.
- Tangled methods get split as a side effect of fixing them.
- Legacy code: applies to bug fixes from now on. No backfilling tests for old fixes, and no sweep.

## Exceptions

A test is not required, and the fix is documented in the commit message and issue instead, when the
fix:

- needs a live MySQL server, APCu, a real request (headers, cookies, `exit`) or a consuming app;
- depends on a specific MySQL or PHP version's behavior (for example the PHP 8 changes in #9);
- is cosmetic (message text, comments);
- is only testable by mocking every collaborator and asserting each was called. Extract the logic
  (rule 5) or skip the test.

Exempt from a unit test does not mean exempt from evidence: say how it was checked (a run in a
consuming app's dev copy, never against a live database).

## Related

ADR 002 (unit test standards).
