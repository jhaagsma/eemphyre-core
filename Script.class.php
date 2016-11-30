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
     * Make a POST request
     *
     * @param  string $address The address to post to
     * @param  mixed  $data    The data to post
     *
     * @return string          The server output
     */
    public static function post($address = 'http://localhost', $data = null)
    {
        $address = self::urlfix($address);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $address);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $serverOutput = curl_exec($ch);

        curl_close($ch);

        return $serverOutput;
    }//end post()

    /**
     * Make a GET request
     *
     * @param  string $address The address to request to
     * @param  mixed  $data    The data to send
     *
     * @return string          The server output
     */
    public static function get($address, $data)
    {
        $address = self::urlfix($address);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $address.'?'.http_build_query($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $serverOutput = curl_exec($ch);

        curl_close($ch);

        return $serverOutput;
    }//end get()

    /**
     * Fix the url to have http:// in front if it doesn't have it
     *
     * @param  string $url The url
     *
     * @return string      The fixed url
     */
    private static function urlfix($url)
    {
        if (strpos($url, "http://") !== false) {
            $url = 'http://'.$url;
        }

        return $url;
    }//end urlfix()
}//end class
