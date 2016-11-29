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

        echo "[", date('H:i:s'), "] ", $string, ($newline ? "\n" : null);

        self::$nl_required = ($newline ? false : true);
    }//end out()


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
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $address);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
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
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $address.'?'.http_build_query($data));
        $serverOutput = curl_exec($ch);
        curl_close($ch);

        return $serverOutput;
    }//end get()
}//end class
