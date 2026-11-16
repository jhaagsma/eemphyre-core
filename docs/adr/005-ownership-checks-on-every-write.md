# ADR 005: Ownership Checks on Every Write

## Status

Accepted (2026-11-16)

## Context

Apps on the core are multi-user: rows in `active_sessions`, `user_logins`, `user_actions` and
`user_permission_groups` belong to a `user_id`. The authenticated identity is the `User` that
`Session::doUserCheck()` returns after matching the cookie against `active_sessions`; the router
passes it to the route's auth function (`Auth::authLogin($data, $path, $user)` and the app's own).

The core has no ownership mechanism yet. `CRUD::commit()`, `disable()` and `enable()` update by
primary key alone, and `Auth::authLogin()` only checks that someone is logged in, so whether a write
is the requester's own is left to every caller. Sequential ids make that guessable.

## Decision

1. Every update and delete verifies that the target row belongs to the requester before changing
   anything. If not, it returns the same error as a missing row and changes nothing.
2. The requester's identity comes from the authenticated `User` (via `Session::doUserCheck()`), never
   from `$_GET`, `$_POST`, a hidden field, or an unverified cookie value.
3. Reads are scoped the same way: list queries filter by owner, single-row reads compare the owner
   after loading.
4. The check lives in the data-layer method that does the write (ADR 004), which takes the
   authenticated `User` or owner id as an argument, or in the route's auth function; not inline in
   page code. A shared mechanism in `CRUD` (for example an owner column declared on the subclass and
   added to `commit()`'s `WHERE`): TBD, not decided.
5. State-changing queries include the owner in the `WHERE` clause (`... WHERE login_id = ? AND
   user_id = ?`) and check `affectedRows()`, or load and compare first.
6. For rows owned through a parent, the check follows the chain to the owner.
7. Ownerless tables (`groups`, `action_types`, `version`) are writable only behind an administrative
   check, or the skipped check has a comment saying why (`Action::getActionType()` inserts unknown
   flags into `action_types` for any caller and has no such comment yet). How "administrator" is decided in the core (a `Group` membership?): TBD.
8. Hiding a link or button is a convenience, never authorization.
9. Names and identifiers are as sensitive as the data: another user's name in a list, an error
   message or a log line is a leak too.
10. Failed authorization attempts are logged with the identity and the attempted operation
    (`trigger_error` until there is a logger).
11. Changes to `Session`, `Auth`, `User::checkPassword()` and any ownership check get a careful
    review and are never merged without one.

### Known legacy

- `Session::activeSession()` cookie lookup goes through `preparePart()`'s numeric shortcut, so a
  crafted cookie can match another user's key: #4. Session keys and password hashing: #5. Cookie
  scope and CSRF (a cross-site POST acts as the user): #6.
- `Session::expireSession()` with the default `$single = false` deletes every session of the
  cookie's `user_id` without checking the cookie's key, so a forged cookie can log another user
  out (breaks rule 2): not filed yet.
- `CRUD::commit()` and `M2M::add()` / `delete()` have no owner check; callers must supply it until
  rule 4's mechanism exists.

## Rationale

One place for the check turns "every handler must remember" into "the method cannot forget". Reading
identity from the verified session closes the route of claiming to be someone else. The identical
"not found" stops probing for other users' rows.

## Consequences

- One extra condition or lookup per write.
- Legacy code: this is a security rule, so it is not "new and touched code only". Gaps found are
  closed deliberately: each gets a GitHub issue and a fix, starting with entry points that take a
  row id or user id from the request. New code uses the rule from day one.

## Exceptions

- Administrative operations meant to cross owners, behind an explicit role check and with logging.
- Ownerless shared tables (rule 7).

## Related

ADR 004 (the check belongs in the data layer).
