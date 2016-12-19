<?php
/**
 * Settings is the settings class for the EmPHyre Framework
 * This class was built off of code from StackOverflow by RobertPitt
 *
 * PHP version 7
 *
 * ------
 *
 * @category ExampleFiles
 * @package  EmPHyre
 * @author   Julian Haagsma <jhaagsma@gmail.com>
 * @license  https://opensource.org/licenses/MIT The MIT License (MIT)
 * @link     https://github.com/jhaagsma/emPHyre
 * @link     http://stackoverflow.com/a/3724689/2444435
 * @since    Dec 2016
 */

namespace EmPHyre;

abstract class Settings
{
    static private $protected = []; // For DB / passwords etc
    static private $public    = []; // For public strings such as meta stuff

    /**
     * Get a protected setting
     *
     * @param  string $key The setting to be returned
     *
     * @return mixed       The value of the setting
     */
    public static function getProtected($key)
    {
        return isset(self::$protected[$key]) ? self::$protected[$key] : null;
    }//end getProtected()


    /**
     * Get a public string
     *
     * @param  string $key The setting to be returned
     *
     * @return mixed       The value of the setting
     */
    public static function getPublic($key)
    {
        return isset(self::$public[$key]) ? self::$public[$key] : null;
    }//end getPublic()


    /**
     * Set a protected string
     *
     * @param  string $key   The setting to be set
     * @param  mixed  $value The value to set the setting to
     *
     * @return mixed         The value of the setting
     */
    public static function setProtected($key, $value)
    {
        self::$protected[$key] = $value;
    }//end setProtected()


    /**
     * Set a public string
     *
     * @param  string $key   The setting to be set
     * @param  mixed  $value The value to set the setting to
     *
     * @return mixed         The value of the setting
     */
    public static function setPublic($key, $value)
    {
        self::$public[$key] = $value;
    }//end setPublic()



    /**
     * Overload the get so we can return on using ->
     * THIS ONLY WORKS FOR PUBLIC VARIABLES, WHICH IS KINDOF THE POINT
     * $this->key
     * returns public->key
     *
     * @param  string $key The setting to be returned
     *
     * @return mixed       The value of the setting
     */
    public function __get($key)
    {
        return isset(self::$public[$key]) ? self::$public[$key] : null;
    }//end __get()


    /**
     * Overload the isset so we can check if a setting is set
     *
     * @param  string $key The setting to be checked
     *
     * @return boolean      Whether or not the setting is set
     */
    public function __isset($key)
    {
        return isset(self::$public[$key]);
    }//end __isset()
}//end class
