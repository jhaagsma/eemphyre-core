# ADR 003: Error Handling and Recovery

## Status

Accepted (2026-11-16)

## Context

The core signals failure in several ways today, and a caller cannot tell which without reading the
implementation:

- `MysqlDb::queryError()` and a failed connect call `trigger_error(..., E_USER_ERROR)` and `exit`;
- domain methods (`User::add()`, `User::edit()`, `Group::alterUserGroups()`, `Validate::*`) return a
  `Result` object whose `isError()` says how it went;
- some methods return `false` or `null` for failure (`MysqlDb::query()` when not connected,
  `Cache::store()` on a bad key, `getSeqID()` with no table; `Script::post()` when `curl_exec` failed, until #11);
- misconfiguration (`TABLE NAME NOT SET`) is a default-level `trigger_error` and execution continues.

Apart from `Script::post()` / `get()` throwing on a failed request (#11), there are no exceptions, `try` or `catch` in the core. `ErrorHandler.function.php` and
`errorlog.php` are broken (undefined variables, a misspelled include path), and failing SQL text can
reach the browser when `display_errors` is on: known legacy, tracked in #7. PHP 8.1 makes mysqli
throw by default, which bypasses `queryError()` entirely: tracked in #9.

## Decision

1. A function returns its result or signals an error; it does not do both through one value. An
   expected "not found" on a lookup returns `null`. Infrastructure failures (database, connection,
   APCu, outbound HTTP) raise: today via `MysqlDb::queryError()`, and in new code by throwing an
   exception (SPL types such as `\RuntimeException`, `\InvalidArgumentException`, or a subclass).
2. `Result` is the sanctioned return type for expected, user-facing outcomes of a domain operation
   (validation failed, already exists, unchanged). It is not used for infrastructure failures, and a
   function returning `Result` returns it on every path (no `Result`-or-`false`).
3. No new function returns `false`, `0` or an empty array to mean "error". A boolean return is fine
   for a real yes/no question (`tableExists()`, `isDisabled()`).
4. Catch the narrowest exception you can handle. If you do not know what to expect, do not catch.
5. A catch-all (`catch (\Throwable $e)`) is allowed only at the boundaries listed below. Every catch
   logs, rethrows or returns an error the caller can see; empty catches are prohibited.
6. Rethrow with `throw $e;`; wrap only to add context at a layer boundary, passing the original as
   the previous exception (`new \RuntimeException($msg, 0, $e)`).
7. No `@` suppression in new code.
8. Errors shown to a user carry a message they can act on, never SQL text, file paths or stack
   traces. Detail goes to the log (`trigger_error`, which lands in the PHP error log or the consuming
   app's error handler).
9. Code must behave the same on PHP 7.2 and 8.x: new mysqli code does not rely on 7.2's
   "return false" behavior without also handling the 8.1 exception (#9).
10. Misconfiguration found at startup (missing table name, conflicting routes) is a bug: fail loudly
    rather than continue.

### Error boundaries

| Boundary | Where | Behavior |
|---|---|---|
| Request | the consuming app's front controller, outside this repo | catch-all allowed there: log, show a generic error page. What EE, demo3 and sterling do today: TBD |
| Fatal stop | `MysqlDb::queryError()` / connect failure | `E_USER_ERROR` + `exit`; must log detail and show the user nothing internal (#7) |
| Per-item loops in maintenance scripts | TBD | TBD |

### Error domains

| Domain | Strategy | Log | User sees |
|---|---|---|---|
| SQL error (`MysqlDb::query()`) | fail the request; it is a bug or an outage | `E_USER_ERROR` with query and call site | generic error (today: possibly the SQL, #7) |
| DB connect / "gone away" | fail the request; no reconnect exists (#7) | `E_USER_ERROR` | generic error |
| Validation / duplicate / unchanged | return `Result` | none | `Result::message()` |
| Route not found | `FourOhFour::notFound()` | none | the app's `404.php` |
| Bad request input | `TypeValidator` coerces to the declared type or default; no error | none | n/a |
| Auth failure | `URL::redirect()` to `/login?result=...` | TBD (none today) | login page |
| APCu miss or failed store | treat as a miss and read the source | `E_USER_WARNING` on a bad key | nothing |
| Outbound HTTP (`Script::post/get`) | timeouts (connect 5 s, total 15 s, `Script::setTimeouts()`); curl error, timeout or non-2xx throws `\RuntimeException` (code 0 or the HTTP status, #11) | the caller's or the request boundary's | the caller decides |
| Misconfiguration (table name, route conflicts) | bug to fix | `trigger_error` | n/a |

## Rationale

When a non-null return is always a valid result, errors surface where they happen. Naming the
boundaries makes every other catch-all a review finding. Keeping `Result` for expected outcomes
matches what the domain classes already do instead of inventing a second convention.

## Consequences

- Callers can trust return values; the PHP 8 move (#9) gets one documented failure model to target.
- Legacy code: new code complies immediately. Existing `false`-on-failure returns and the
  trigger-and-exit pattern are converted when the function is modified, not in a sweep. The
  fail-loud stop in `queryError()` stays; softening it (reconnect, retry) is a deliberate change
  (#7). Mark a known violation `TODO(ADR-003): ...` only where the replacement is decided.

## Exceptions

- A boolean return for a genuine yes/no question.
- `URL::redirect()` and other deliberate `exit` calls that end a request on purpose.

## Related

ADR 004 (the data layer owns database error handling).
