<?php
/**
 * Cors: opt-in Cross-Origin Resource Sharing for EmPHyre apps.
 *
 * PHP version 7.2
 *
 * Off by default: until the app calls Cors::allow(), no CORS headers are ever
 * sent and cross-origin preflights fail, which is what browsers want for a site
 * that doesn't need to be called from other sites.
 *
 * Usage (once, before routing):
 *
 *     \EmPHyre\Cors::allow(['https://app.example.com'], ['/api'], true);
 *
 * The second argument scopes CORS to request paths (a prefix matches itself and anything below it:
 * '/api' covers '/api' and '/api/x', not '/apix'). Only those paths get CORS: the preflight approval AND
 * the response headers. Everything else gets neither, because an allowed origin with credentials can
 * act as (and read every CORS response of) the logged-in user: ordinary pages (account settings, API
 * keys, forms) must stay out of scope. Router::route() applies it centrally with the path it routes
 * (preflight() for OPTIONS, headers() for everything else, before routing, so 404s and auth errors on
 * API paths carry it too); a script outside the Router can call Cors::headers() itself. Only the
 * configured methods (default GET, POST) get CORS, so an in-scope path routed by a fallback for another
 * method never does.
 *
 * Rules:
 *  - The request's Origin header must match an allowed origin exactly (scheme://host[:port], no path;
 *    case, a trailing slash and a default port :443/:80 are normalised). Never echo an origin that
 *    isn't on the list, and never use the Referer for this.
 *  - An invalid configured origin is a bug: allow() throws InvalidArgumentException (ADR 003).
 *  - With $credentials = true, cookies are allowed: every listed origin can act as
 *    the logged-in user. List only front-ends you control.
 *  - "Vary: Origin" is always sent when CORS is configured, so caches don't hand one
 *    origin's answer to another.
 *
 * @category Core
 * @package  EmPHyre
 * @author   Julian Haagsma <jhaagsma@gmail.com>
 * @license  All files are licensed under the MIT License.
 * @link     https://github.com/jhaagsma/emPHyre
 * @since    November 2026
 */

namespace EmPHyre;

class Cors
{
    const DEFAULT_METHODS = ['GET', 'POST'];
    const DEFAULT_HEADERS = ['Content-Type'];
    const MAX_AGE         = 600;   // seconds a browser may cache a preflight answer

    /** @var string[] allowed origins, normalised */
    private static $origins = [];

    /** @var string[] path prefixes CORS applies to */
    private static $paths = [];

    /** @var bool send Access-Control-Allow-Credentials */
    private static $credentials = false;

    /** @var string[] methods a preflight may approve */
    private static $methods = self::DEFAULT_METHODS;

    /** @var string[] request headers a preflight may approve */
    private static $headers = self::DEFAULT_HEADERS;

    /** @var bool|null result of the first headers() in this request (so repeat calls are no-ops) */
    private static $sent = null;

    /** @var callable|null header emitter for tests (null = PHP's header()) */
    private static $emitter = null;

    /** @var callable|null tests: replaces headers_sent() (null = the real check) */
    private static $headersSentCheck = null;


    /**
     * Configure CORS. Call once at startup, before routing; calling again replaces ALL settings (anything
     * not passed goes back to its default; allow([]) turns CORS off) but cannot take back headers
     * already sent in this request.
     *
     * @param string[]      $origins     Allowed origins, e.g. ['https://app.example.com']
     * @param string[]      $paths       Path prefixes CORS applies to, e.g. ['/api'] (required when origins are given)
     * @param bool          $credentials Allow cookies / HTTP auth on cross-origin requests
     * @param string[]|null $methods     Methods to approve in preflights (default GET, POST)
     * @param string[]|null $headers     Request headers to approve in preflights (default Content-Type)
     *
     * @return void
     * @throws \InvalidArgumentException for an origin that isn't scheme://host[:port], a path that isn't
     *                                   '/...', or origins without paths
     */
    public static function allow(array $origins, array $paths = [], $credentials = false, ?array $methods = null, ?array $headers = null)
    {
        $scoped = [];
        foreach ($paths as $path) {
            // leading '/', segments of [A-Za-z0-9._~-] not starting with '.', no '?', no '//', not '/' alone
            if (!is_string($path) || !preg_match('#\A(/[A-Za-z0-9_~-][A-Za-z0-9._~-]*)+/?\z#', $path)) {
                throw new \InvalidArgumentException('Cors::allow: invalid path ' . self::describe($path));
            }
            $scoped[] = rtrim($path, '/');
        }

        if ($origins && !$scoped) {
            throw new \InvalidArgumentException('Cors::allow: give the paths CORS applies to (e.g. [\'/api\'])');
        }

        $normalised = [];
        foreach ($origins as $o) {
            $n = self::normalise($o);
            if ($n === null) {
                throw new \InvalidArgumentException('Cors::allow: invalid origin ' . self::describe($o));
            }
            $normalised[] = $n;
        }

        self::$origins     = array_values(array_unique($normalised));
        self::$paths       = array_values(array_unique($scoped));
        self::$credentials = (bool)$credentials;
        self::$methods     = $methods === null ? self::DEFAULT_METHODS : array_values(array_map('strtoupper', $methods));
        self::$headers     = $headers === null ? self::DEFAULT_HEADERS : array_values($headers);
        self::$sent        = null;
    }//end allow()


    /**
     * Is CORS configured at all?
     *
     * @return bool
     */
    public static function enabled()
    {
        return !empty(self::$origins);
    }//end enabled()


    /**
     * The given origin, normalised (lowercase, no trailing slash, no default port), or null if it
     * isn't a valid scheme://host[:port].
     *
     * @param mixed $origin
     *
     * @return string|null
     */
    private static function normalise($origin)
    {
        if (!is_string($origin)) {
            return null;
        }

        $o = strtolower(rtrim(trim($origin), '/'));
        $label = '[a-z0-9]([a-z0-9-]*[a-z0-9])?';
        if (!preg_match('#\A(https?)://(' . $label . '(\.' . $label . ')*)(:([0-9]+))?\z#', $o, $m)) {
            return null;
        }

        $port = '';
        if (isset($m[7]) && $m[7] !== '') {
            $digits = ltrim($m[7], '0');   // leading zeros don't count (:0443 = :443)
            $p      = (int)$digits;
            if (strlen($digits) > 5 || $p < 1 || $p > 65535) {
                return null;
            }
            if (!(($m[1] === 'https' && $p === 443) || ($m[1] === 'http' && $p === 80))) {
                $port = ':' . $p;
            }
        }

        return $m[1] . '://' . $m[2] . $port;
    }//end normalise()


    /**
     * Short, safe description of a bad config value for an exception message.
     *
     * @param mixed $value
     *
     * @return string
     */
    private static function describe($value)
    {
        return is_string($value) ? '"' . addcslashes(substr($value, 0, 100), "\0..\37\"\\") . '"' : gettype($value);
    }//end describe()


    /**
     * The path Router::route() matches for a URL path. Callers pass it without the query (Router::route()
     * strips it once and uses the same string for routing): leading slashes dropped, cut at the first empty segment (urlRoute() stops there:
     * '/rest//x' routes '/rest', and trailing slashes vanish the same way).
     * Limit: it can't see the route tree, so it doesn't know when urlRoute() returns a 404 early (a '0'
     * segment or an empty one at a node without a handler). Keep each scope prefix a statically
     * registered area for the allowed methods (EE: '/rest'), and those cases stay inside the scope's own
     * handlers or 404s.
     *
     * @param string $path
     *
     * @return string
     */
    public static function routedPath($path)
    {
        $segs = [];
        foreach (explode('/', ltrim((string)$path, '/')) as $seg) {
            if ($seg === '') {
                break;
            }
            $segs[] = $seg;
        }

        return '/' . implode('/', $segs);
    }//end routedPath()


    /**
     * Is this request path covered by the configured path prefixes?
     *
     * @param mixed $path the path being routed (reduced with routedPath() first)
     *
     * @return bool
     */
    public static function inScope($path)
    {
        if (!is_string($path)) {
            return false;
        }

        $path = self::routedPath($path);
        foreach (self::$paths as $prefix) {
            if ($path === $prefix || strpos($path, $prefix . '/') === 0) {
                return true;
            }
        }

        return false;
    }//end inScope()


    /**
     * The given (or current request's) Origin if it is on the allowlist, otherwise null.
     *
     * @param mixed $origin Origin to check; null = the request's Origin header
     *
     * @return string|null
     */
    public static function origin($origin = null)
    {
        if ($origin === null) {
            $origin = isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : '';
        }

        $n = self::normalise($origin);
        return ($n !== null && in_array($n, self::$origins, true)) ? $n : null;
    }//end origin()


    /**
     * The CORS headers to send for a request. Pure: sends nothing (tests use this).
     * Vary: Origin comes first whenever CORS is configured.
     *
     * @param mixed $origin    The request's Origin header ('' or null = none)
     * @param bool  $preflight true for an OPTIONS preflight
     *
     * @return string[] header lines
     */
    public static function headerLines($origin, $preflight = false)
    {
        return self::build(self::origin($origin === null ? '' : $origin), $preflight);
    }//end headerLines()


    /**
     * Header lines for an already-checked origin (null = not allowed).
     *
     * @param string|null $allowed
     * @param bool        $preflight
     *
     * @return string[]
     */
    private static function build($allowed, $preflight)
    {
        if (!self::enabled()) {
            return [];
        }

        $lines = [$preflight ? 'Vary: Origin, Access-Control-Request-Method' : 'Vary: Origin'];
        if ($allowed === null) {
            return $lines;
        }

        $lines[] = 'Access-Control-Allow-Origin: ' . $allowed;
        if (self::$credentials) {
            $lines[] = 'Access-Control-Allow-Credentials: true';
        }

        if ($preflight) {
            $lines[] = 'Access-Control-Allow-Methods: ' . implode(', ', self::$methods);
            $lines[] = 'Access-Control-Allow-Headers: ' . implode(', ', self::$headers);
            $lines[] = 'Access-Control-Max-Age: ' . self::MAX_AGE;
        }

        return $lines;
    }//end build()


    /**
     * Send the CORS headers for a real (non-preflight) response, if this request is in scope (an allowed
     * method on a configured path). Router::route() calls it for every request; a script outside the
     * Router can call it itself. Idempotent within a request. Note: a handler that later sets its own
     * Vary header must include Origin in it (header() replaces by default).
     *
     * @param string|null $path   the path being routed (null = this request's URI)
     * @param string|null $method the method it is routed as (null = this request's method)
     *
     * @return bool true if the request's origin was allowed (and its headers sent)
     */
    public static function headers($path = null, $method = null)
    {
        return self::send($path, $method, false);
    }//end headers()


    /**
     * Answer a preflight (OPTIONS) request and stop. Allowed origins get the approval headers;
     * everyone else gets an empty 204, which the browser treats as "no".
     *
     * @param string|null $path the path being routed (null = this request's URI)
     *
     * @return void (exits)
     */
    public static function preflight($path = null)
    {
        self::preflightHeaders($path);
        http_response_code(204);
        exit;
    }//end preflight()


    /**
     * The header part of preflight(), without ending the request.
     *
     * @internal Tests only: apps call preflight(), which ends the request. Approval only for an
     * allowed origin on a scoped path asking for an allowed method (Access-Control-Request-Method).
     *
     * @param string|null $path the path being routed (null = this request's URI)
     *
     * @return bool true if the preflight was approved
     */
    public static function preflightHeaders($path = null)
    {
        return self::send($path, null, true);
    }//end preflightHeaders()


    /**
     * Emit headerLines() for the current request.
     *
     * @param string|null $path      the path being routed (null = this request's URI)
     * @param string|null $method    the method it is routed as (null = this request's method)
     * @param bool        $preflight true for an OPTIONS preflight
     *
     * @return bool true if the request's origin was allowed (and its headers sent)
     */
    private static function send($path, $method, $preflight)
    {
        if (self::$sent !== null && !$preflight) {   // already sent for this request (idempotent)
            return self::$sent;
        }

        if ($path === null) {   // the same request URI the Router uses
            $path = explode('?', Router::requestUri(), 2)[0];
        }
        if (!self::enabled() || !self::inScope($path)) {   // out-of-scope paths get no CORS at all
            return false;
        }

        // the method: as routed for real requests, as requested for a preflight. A method that isn't
        // allowed (e.g. PUT when only GET/POST are) gets Vary but never Allow-Origin.
        if ($preflight) {
            $method = isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_METHOD']) ? $_SERVER['HTTP_ACCESS_CONTROL_REQUEST_METHOD'] : '';
        } elseif ($method === null) {
            $method = isset($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : 'GET';
        }
        // preflight: compared exactly, as browsers compare it with Allow-Methods; routed methods are upper case
        $methodOk = in_array($preflight ? (string)$method : strtoupper((string)$method), self::$methods, true);

        $allowed = $methodOk ? self::origin(isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : '') : null;

        $file = $line = '?';
        $alreadySent = self::$headersSentCheck === null ? headers_sent($file, $line) : (bool)call_user_func(self::$headersSentCheck);
        if ($alreadySent) {
            if ($allowed !== null) {   // CORS headers were due (allowed origin and method): a real failure
                trigger_error('Cors: headers already sent (output started at ' . basename((string)$file) . ":$line); CORS headers not sent", E_USER_WARNING);
            }
            return self::$sent = false;   // once per request
        }

        foreach (self::build($allowed, $preflight) as $i => $headerLine) {
            // line 0 is Vary: add it to any Vary the app sets instead of replacing it
            if (self::$emitter === null) {
                header($headerLine, $i !== 0);
            } else {
                call_user_func(self::$emitter, $headerLine, $i !== 0);
            }
        }

        return self::$sent = ($allowed !== null);
    }//end send()


    /**
     * Capture headers instead of sending them, for tests.
     *
     * @internal Tests only, never call from app code: capture headers instead of sending them
     * (null restores PHP's header()), optionally pretend headers were already sent; also forgets the
     * per-request state.
     *
     * @param callable|null $emitter          function (string $line, bool $replace)
     * @param callable|null $headersSentCheck function (): bool, replacing headers_sent() (null = real check)
     *
     * @return void
     */
    public static function setEmitterForTests(?callable $emitter, ?callable $headersSentCheck = null)
    {
        self::$emitter          = $emitter;
        self::$headersSentCheck = $headersSentCheck;
        self::$sent             = null;
    }//end setEmitterForTests()
}//end class
