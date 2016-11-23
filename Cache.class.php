<?php

/**
 * This is the index, which handles all the incoming stuff
 *
 * PHP version 5
 *
 * ------
 * These files are part of the eemphyre framework;
 * Formerly: emPHyre, and empiresPHPframework
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
 * @category Index
 * @package  eemphyre: examplesite
 * @author   Julian Haagsma <jhaagsma@gmail.com>
 * @author   Timo Ewalds <tewalds@gmail.com>
 * @author   Dave McVittie <dave.mcvittie@gmail.com>
 * @author   Joe Obbish <slagpit@earthempires.com>
 * @license  All files are licensed under the MIT License.
 * @link     https://github.com/jhaagsma/eemphyre
 * @link     http://eemphyre.com
 * @since    Pulled out of errorlog.php 2016-03-15
 */

namespace EmPHyre;

class Cache
{
    public static $count   = 0;
    public static $queries = array();


    public function __construct()
    {
        // $this->queries = array();
        // $this->count = 0;
    }//end __construct()


    public function __destruct()
    {

    }//end __destruct()


    private static function addquery($stuff)
    {
        self::$queries[] = $stuff;
        self::$count++;
        if (count(self::$queries) > 1000) {
            array_shift(self::$queries);
        }

    }//end addquery()


    public static function add($key, $val, $ttl = 0)
    {
        $start   = microtime(true);
        $success = apcu_add($key, $val, $ttl);
        self::addquery(array('add', $success, microtime(true) - $start, $key, $ttl));
        return $success;

    }//end add()


    public static function store($key, $val, $ttl = 0)
    {
        $start   = microtime(true);
        $success = apcu_store($key, $val, $ttl);
        self::addquery(array('store', $success, microtime(true) - $start, $key, $ttl));
        return $success;

    }//end store()


    public static function fetch($key, $default = null)
    {
        $start = microtime(true);
        $val   = apcu_fetch($key, $success);
        self::addquery(array('fetch', $success, microtime(true) - $start, $key, null));
        return ($success ? $val : $default);

    }//end fetch()


    public static function multiFetch($keys)
    {
        $return = array();
        foreach ($keys as $key) {
            $start = microtime(true);
            $val   = apcu_fetch($key, $success);
            self::addquery(array('fetch', $success, microtime(true) - $start, $key, null));
            if ($success) {
                $return[$key] = $val;
            }
        }

        return $return;

    }//end multiFetch()


    public static function fetchPrefixKeys($prefix, $keys)
    {
        $fetch = array();
        foreach ($keys as $k) {
            $fetch[] = $prefix.$k;
        }

        return self::multiFetch($fetch);

    }//end fetchPrefixKeys()


    public static function delete($key)
    {
        $start   = microtime(true);
        $success = apcu_delete($key);
        self::addquery(array('delete', $success, microtime(true) - $start, $key, null));
        return $success;

    }//end delete()


    public static function clearUserCache()
    {
        $start   = microtime(true);
        $success = apcu_clear_cache('user');
        self::addquery(array('clear user cache', $success, microtime(true) - $start, null, null));
        return $success;

    }//end clearUserCache()


    public static function clearCache()
    {
        $start   = microtime(true);
        $success = apcu_clear_cache();
        self::addquery(array('clear cache', $success, microtime(true) - $start, null, null));
        return $success;

    }//end clearCache()
}//end class
