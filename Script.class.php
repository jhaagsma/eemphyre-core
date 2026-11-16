<?php
/**
 * Script is a collection of useful functions and things for scripting
 *
 * PHP version 7
 *
 * @category Scripting
 * @package  EmPHyre
 * @author   Julian Haagsma <jhaagsma@gmail.com>
 * @license  https://opensource.org/licenses/MIT The MIT License (MIT)
 * @link     https://github.com/jhaagsma/emPHyre
 * @since    Nov 2016
 */

namespace EmPHyre;

class Script
{
    private static $nl_required = false;

    // seconds; see setTimeouts()
    private static $connectTimeout = 5;
    private static $timeout        = 15;

    /**
     * This echos a string, and formats things and stuff
     *
     * @param  string  $string     The string to echo
     * @param  boolean $newline    Whether a newline is required at the end
     * @param  boolean $opening_nl Whether a staring newline is required
     *
     * @return null
     */
    public static function out($string, $newline = true, $opening_nl = false)
    {
        if (self::$nl_required == true || $opening_nl == true) {
            echo "\n";
        }

        $string = self::verifyType($string);

        echo "[", date('H:i:s'), "] ", $string, ($newline ? "\n" : null);

        self::$nl_required = ($newline ? false : true);
    }//end out()

    /**
     * Just json encode arrays
     *
     * @param  mixed $string The "string" to fix
     *
     * @return string        The fixed string
     */
    private static function verifyType($string = null)
    {
        if (!is_array($string)) {
            return $string;
        } else {
            return json_encode($string);
        }
    }//end verifyType()


    /**
     * Timeouts for post() and get(), in seconds
     *
     * @param  integer $connect Give up connecting after this long (default 5)
     * @param  integer $total   Give up on the whole request after this long (default 15)
     *
     * @return null
     */
    public static function setTimeouts($connect = 5, $total = 15)
    {
        // at least 1 s: curl reads 0 as "no timeout", which is what #11 fixed
        self::$connectTimeout = max(1, (int)$connect);
        self::$timeout        = max(1, (int)$total);
    }//end setTimeouts()


    /**
     * Make a POST request
     *
     * @param  string $address The address to post to
     * @param  mixed  $data    The data to post
     *
     * @return string          The server output (the body of a 2xx response)
     *
     * @throws \RuntimeException If the request fails (code 0: connect, timeout or other curl
     *                           error) or the status is not 2xx (code: the HTTP status)
     */
    public static function post($address = 'http://localhost', $data = null)
    {
        $address = self::urlfix($address);

        return self::request('POST', $address, [
            CURLOPT_POST       => 1,
            CURLOPT_POSTFIELDS => http_build_query($data),
        ]);
    }//end post()

    /**
     * Make a GET request
     *
     * @param  string $address The address to request to
     * @param  mixed  $data    The data to send
     *
     * @return string          The server output (the body of a 2xx response)
     *
     * @throws \RuntimeException As post()
     */
    public static function get($address, $data)
    {
        $address = self::urlfix($address);

        return self::request('GET', $address.'?'.http_build_query($data), []);
    }//end get()

    /**
     * Run one curl request with the timeouts; the only place that calls curl_* (ADR 004)
     *
     * @param  string $method  POST or GET, for the error message
     * @param  string $url     The full URL
     * @param  array  $options Extra CURLOPT_* options
     *
     * @return string          The body of a 2xx response
     *
     * @throws \RuntimeException As post()
     */
    private static function request($method, $url, array $options)
    {
        $ch = curl_init();
        curl_setopt_array($ch, $options + [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => self::$connectTimeout,
            CURLOPT_TIMEOUT        => self::$timeout,
            // HTTP(S) only: no file://, gopher://, ... (also for redirects, if a caller enables them)
            CURLOPT_PROTOCOLS      => CURLPROTO_HTTP | CURLPROTO_HTTPS,
            CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
        ]);

        $serverOutput = curl_exec($ch);
        $curlErrno    = curl_errno($ch);
        $curlError    = curl_error($ch);
        $status       = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);

        curl_close($ch);

        // no query string or user:password@ in the message: either can hold secrets
        $where = $method.' '.preg_replace('#^([a-z][a-z0-9+.-]*://)[^/@]*@#i', '$1', explode('?', $url, 2)[0]);

        if ($serverOutput === false || $curlErrno !== 0) {
            throw new \RuntimeException($where.' failed: '.$curlError.' (curl error '.$curlErrno.')');
        }

        if ($status < 200 || $status > 299) {
            throw new \RuntimeException($where.' failed: HTTP '.$status, $status);
        }

        return $serverOutput;
    }//end request()

    /**
     * Fix the url to have http:// in front if it doesn't have it
     *
     * @param  string $url The url
     *
     * @return string      The fixed url
     */
    private static function urlfix($url)
    {
        // add http:// only when the URL has no scheme (this test was inverted: it prefixed URLs that
        // already had http://, so every such call failed with "Could not resolve host: http")
        if (!preg_match('#^[a-z][a-z0-9+.-]*://#i', $url)) {
            $url = 'http://'.$url;
        }

        return $url;
    }//end urlfix()
}//end class
