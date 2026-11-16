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
 * then in any handler that serves cross-origin callers (real responses):
 *
 *     \EmPHyre\Cors::headers();
 *
 * Preflight (OPTIONS) requests are answered by Router::route() via Cors::preflight().
 *
 * Rules:
 *  - The request's Origin header must match an allowed origin EXACTLY
 *    (scheme://host[:port], no path, no trailing slash). Never echo an origin
 *    that isn't on the list, and never use the Referer for this.
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
    /** @var string[] allowed origins, exact match */
    private static $origins = [];

    /** @var bool send Access-Control-Allow-Credentials */
    private static $credentials = false;

    /** @var string[] methods a preflight may approve */
    private static $methods = ['GET', 'POST'];

    /** @var string[] request headers a preflight may approve */
    private static $headers = ['Content-Type'];

    /** @var int seconds a browser may cache a preflight answer */
    private static $max_age = 600;


    /**
     * Configure CORS. Call once at startup; calling again replaces the settings.
     *
     * @param string[] $origins     Allowed origins, e.g. ['https://app.example.com']
     * @param bool     $credentials Allow cookies / HTTP auth on cross-origin requests
     * @param string[] $methods     Methods to approve in preflights
     * @param string[] $headers     Request headers to approve in preflights
     *
     * @return void
     */
    public static function allow(array $origins, $credentials = false, array $methods = null, array $headers = null)
    {
        self::$origins = [];
        foreach ($origins as $o) {
            $o = rtrim(trim((string)$o), '/');
            if (preg_match('#^https?://[a-z0-9.-]+(:[0-9]+)?$#i', $o)) {
                self::$origins[] = strtolower($o);
            } else {
                trigger_error("Cors::allow ignored an invalid origin: $o");
            }
        }

        self::$credentials = (bool)$credentials;
        if ($methods !== null) {
            self::$methods = array_values(array_map('strtoupper', $methods));
        }

        if ($headers !== null) {
            self::$headers = array_values($headers);
        }
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
     * The request's Origin if it is on the allowlist, otherwise null.
     *
     * @return string|null
     */
    public static function origin()
    {
        $origin = isset($_SERVER['HTTP_ORIGIN']) ? strtolower(rtrim($_SERVER['HTTP_ORIGIN'], '/')) : '';
        if ($origin === '' || !in_array($origin, self::$origins, true)) {
            return null;
        }

        return $origin;
    }//end origin()


    /**
     * Send the CORS headers for a real (non-preflight) response.
     * Does nothing unless CORS is configured; sends Allow-Origin only for an allowed Origin.
     *
     * @return bool true if the request's origin was allowed
     */
    public static function headers()
    {
        if (!self::enabled() || headers_sent()) {
            return false;
        }

        header('Vary: Origin', false);
        $origin = self::origin();
        if ($origin === null) {
            return false;
        }

        header('Access-Control-Allow-Origin: ' . $origin);
        if (self::$credentials) {
            header('Access-Control-Allow-Credentials: true');
        }

        return true;
    }//end headers()


    /**
     * Answer a preflight (OPTIONS) request and stop. Allowed origins get the approval
     * headers; everyone else gets an empty 204, which the browser treats as "no".
     *
     * @return void (exits)
     */
    public static function preflight()
    {
        if (self::headers()) {
            header('Access-Control-Allow-Methods: ' . implode(', ', self::$methods));
            header('Access-Control-Allow-Headers: ' . implode(', ', self::$headers));
            header('Access-Control-Max-Age: ' . self::$max_age);
        }

        http_response_code(204);
        exit;
    }//end preflight()
}//end class
