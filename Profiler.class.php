<?php
/**
 * This is the Profiler for the EmPHyre project
 *
 * PHP version 7
 *
 * ------
 * These files are part of the empiresPHPframework;
 * The original framework core (specifically the mysql.php
 * the router.php and the errorlog) was started by Timo Ewalds,
 * and rewritten to use APC and extended by Julian Haagsma,
 * for use in Earth Empires (located at http://www.earthempires.com );
 * it was spun out for use on other projects.
 *
 * The general.php contains content from Earth Empires
 * written by Dave McVittie and Joe Obbish.
 *
 * The example website files were written by Julian Haagsma.
 *
 * @category Core
 *
 * @package  EmPHyre
 *
 * @author   Julian Haagsma <jhaagsma@gmail.com>
 *
 * @license  All files are licensed under the MIT License.
 *
 * @link     https://github.com/jhaagsma/emPHyre
 *
 * @since    February 24, 2018
 */

namespace EmPHyre;

/**
 * The profiling class
 */
class Profiler
{
    private static $time_start = 0;
    private static $timers     = [];

    /**
     * Set the start time
     *
     * @param integer $start The start time
     *
     * @return null
     */
    public static function setStart($start = 0)
    {
        self::$time_start = $start;
    }//end setStart()

    /**
     * Add another timer line item
     *
     * @param  string $timer The line item to add
     *
     * @return null
     */
    public static function profile($timer)
    {
        self::$timers[$timer] = self::codetime(true);
    }//end profile()

    /**
     * Find how long it's been since the start
     *
     * @param  boolean $detail Whether or not we want fractions of a ms
     *
     * @return folat           A time
     */
    private static function codetime($detail = false)
    {
        if ($detail) {
            return (microtime(true) - self::$time_start) * 1000;
        } else {
            return ceil((microtime(true) - self::$time_start) * 1000);
        }
    }//end codetime()

    /**
     * Get the profile!
     *
     * @return array The profile
     */
    public static function getProfile()
    {
        return self::$timers;
    }//end getProfile()

    /**
     * Get the elapsed time without detail
     *
     * @return int Number of ms
     */
    public static function getElapsed()
    {
        return self::codetime();
    }//end getElapsed()
}//end class
