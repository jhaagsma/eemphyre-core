# ADR 002: Unit Test Standards

## Status

Accepted (2026-11-16)

## Context

ADR 001 covers tests written in response to a bug. This ADR covers proactive tests and the quality
bar for every test. The code most worth testing here is pure logic where a wrong value is worse than
a crash: `TypeValidator` coercion (SI prefixes, `u_int` clamping), `preparePart()` quoting, CORS
origin matching, `Math`, `Result` message parsing, route tree building. A green run must mean
something, so tests that pass for the wrong reason are not wanted either.

The harness is PHPUnit (`vendor/bin/phpunit`, `phpunit.xml.dist`, `tests/*Test.php`); it exists on
branch `cors-allowlist`, not yet on `master` (see ADR 001).

## Decision

1. Public functions that are pure (no database, APCu, headers, cookies, superglobals or static state)
   have unit tests when they are new or extracted. Priority: input validation and escaping, data
   transformation, parsing, collection operations, utilities.
2. One test file per class, `tests/<Class>Test.php`. Test method names state the scenario and the
   expected behavior (`testOtherOriginsAreNeverEchoed`).
3. Assertions that are not self-explanatory carry a message saying what should be true, in the
   present tense. `assertTrue`, `assertFalse`, `assertNull` and exception assertions always carry
   one (PHPUnit: the message is the last argument; use `expectException()` with a specific class).
   Equality assertions may omit it when the test name makes the intent obvious.
4. Every test has a named way to fail, and the author can state it:
   - a regression test was run against the broken code and went red (ADR 001);
   - a test on new logic names the mutation that breaks it ("drop the exact-match check and this
     fails") in a comment or the commit body;
   - a contract test (boundary, empty input, round trip) fails when "someone changes the contract".
5. Two tests with the same named failure are one test. Keep the one with the clearer name.
6. Static state is reset in `setUp()` (as `CorsTest` resets `Cors::allow([])`), because classes
   here keep configuration in static properties and test order must not matter.
7. When several code paths produce similar results or messages, assert on the part that
   distinguishes them.
8. A compound condition (`a || b`) gets a case per operand.
9. Minimum cases by kind of function:
   - conversion or validation: identity case, at least two non-trivial values, empty input, negative
     or out-of-range input where meaningful, wrong type (array where a string is expected);
   - parsing: valid, empty or null, malformed;
   - collection or algorithm: empty, single element, boundary.
10. Tests that read source files as text to guard an architectural rule are valid unit tests.
11. Tests are PHP 7.2-compatible (ADR 001 rule 10).

## Rationale

A test that asserts what the code happens to do is worse than no test. "What breaks this?" is a cheap
bloat check that needs no mutation-testing tool. Assertion messages make a failure readable from the
PHPUnit output alone.

## Consequences

- New pure functions get coverage before they cause subtle errors in a consuming app.
- Legacy code: applies to new tests and to pure functions that are new or extracted. Most of the
  core mixes logic with `MysqlDb` calls and statics; it gains testable units through ADR 001 rule 5
  (extract, then test). There is no coverage target.

## Exceptions

- Code that cannot run without MySQL, APCu or a web request is out of scope for unit tests; check it
  in a dev copy of a consuming app instead (never against a live database).

## Related

ADR 001.
