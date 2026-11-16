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
 *     \EmPHyre\Cors::allow(['https://app.example.com'], true);
 *
 * then, in each handler that is MEANT to be called cross-origin (e.g. a JSON API), and only there:
 *
 *     \EmPHyre\Cors::headers();
 *
 * Opt-in per handler on purpose: an allowed origin with credentials can read every response that carries
 * these headers as the logged-in user, so ordinary pages (account settings, API keys, forms with CSRF
 * tokens) must not get them. Router::route() answers preflight (OPTIONS) requests with Cors::preflight().
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


    /**
     * Configure CORS. Call once at startup, before routing; calling again replaces ALL settings (anything
     * not passed goes back to its default; allow([]) turns CORS off) but cannot take back headers
     * already sent in this request.
     *
     * @param string[]      $origins     Allowed origins, e.g. ['https://app.example.com']
     * @param bool          $credentials Allow cookies / HTTP auth on cross-origin requests
     * @param string[]|null $methods     Methods to approve in preflights (default GET, POST)
     * @param string[]|null $headers     Request headers to approve in preflights (default Content-Type)
     *
     * @return void
     * @throws \InvalidArgumentException for an origin that isn't scheme://host[:port]
     */
    public static function allow(array $origins, $credentials = false, ?array $methods = null, ?array $headers = null)
    {
        $normalised = [];
        foreach ($origins as $o) {
            $n = self::normalise($o);
            if ($n === null) {
                throw new \InvalidArgumentException('Cors::allow: invalid origin ' . var_export($o, true));
            }
            $normalised[] = $n;
        }

        self::$origins     = array_values(array_unique($normalised));
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
        if (!preg_match('#\A(https?)://(' . $label . '(\.' . $label . ')*)(:([0-9]{1,5}))?\z#', $o, $m)) {
            return null;
        }

        $port = '';
        if (isset($m[7]) && $m[7] !== '') {
            $p = (int)$m[7];   // also drops leading zeros (:0443 = :443)
            if ($p < 1 || $p > 65535) {
                return null;
            }
            if (!(($m[1] === 'https' && $p === 443) || ($m[1] === 'http' && $p === 80))) {
                $port = ':' . $p;
            }
        }

        return $m[1] . '://' . $m[2] . $port;
    }//end normalise()


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
        if (!self::enabled()) {
            return [];
        }

        $lines   = ['Vary: Origin'];
        $allowed = self::origin($origin === null ? '' : $origin);
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
    }//end headerLines()


    /**
     * Send the CORS headers for a real (non-preflight) response. Call it in handlers meant for
     * cross-origin callers only (see the class comment); does nothing unless CORS is configured.
     * Idempotent within a request.
     *
     * @return bool true if the request's origin was allowed (and its headers sent)
     */
    public static function headers()
    {
        return self::send(false);
    }//end headers()


    /**
     * Answer a preflight (OPTIONS) request and stop. Allowed origins get the approval headers;
     * everyone else gets an empty 204, which the browser treats as "no".
     *
     * @return void (exits)
     */
    public static function preflight()
    {
        self::send(true);
        http_response_code(204);
        exit;
    }//end preflight()


    /**
     * Emit headerLines() for the current request.
     *
     * @param bool $preflight true for an OPTIONS preflight
     *
     * @return bool true if the request's origin was allowed (and its headers sent)
     */
    private static function send($preflight)
    {
        if (!self::enabled()) {
            return false;
        }

        if (self::$sent !== null && !$preflight) {   // already done for this request
            return self::$sent;
        }

        if (self::$emitter === null && headers_sent($file, $line)) {   // say so once, instead of failing quietly
            trigger_error("Cors: headers already sent (output started at $file:$line); CORS headers not sent", E_USER_WARNING);
            return self::$sent = false;
        }

        $lines = self::headerLines(isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : '', $preflight);
        foreach ($lines as $i => $line) {
            // line 0 is Vary: add it to any Vary the app sets instead of replacing it
            self::$emitter === null ? header($line, $i !== 0) : call_user_func(self::$emitter, $line, $i !== 0);
        }

        return self::$sent = (count($lines) > 1);   // more than Vary = Allow-Origin was sent
    }//end send()


    /**
     * Tests only: capture headers instead of sending them (null restores header()); also forgets the
     * per-request state.
     *
     * @param callable|null $emitter function (string $line, bool $replace)
     *
     * @return void
     */
    public static function setEmitter(?callable $emitter)
    {
        self::$emitter = $emitter;
        self::$sent    = null;
    }//end setEmitter()
}//end class
