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
}//end class
