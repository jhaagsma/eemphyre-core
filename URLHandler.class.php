<?php

/**
 * URL is a class to handle all sorts of URL-related functions
 *
 * PHP version 5
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
 * @package  EmPHyre
 * @author   Julian Haagsma <jhaagsma@gmail.com>
 * @author   Timo Ewalds <tewalds@gmail.com>
 * @author   Dave McVittie <dave.mcvittie@gmail.com>
 * @author   Joe Obbish <slagpit@earthempires.com>
 * @license  All files are licensed under the MIT License.
 * @link     https://github.com/jhaagsma/emPHyre
 * @since    Pulled out of errorlog.php 2016-03-15
 */

namespace EmPHyre;

class URLHandler
{
    protected function __construct()
    {
        //protected construct so that URLHandler can't be instantiated
    }

    public static function redirect($loc)
    {
        header("Location: $loc", true, 303);
        echo "Redirecting to: <a href='" . htmlentities($loc) . "'>$loc</a>";
        exit;
    }

    public static function moved($loc)
    {
        header("Location: $loc", true, 301);
        echo "Redirecting to: <a href='" . htmlentities($loc) . "'>$loc</a>";
        exit;
    }

    public static function sendJsHeader()
    {
        header('Content-Type: application/javascript');
    }

    public static function sendJsonHeader()
    {
        header('Content-Type: application/json');
    }

    public static function getUrlPart($token_num)
    {
        $parts = explode('.', $_SERVER['SERVER_NAME']);
        if ($token_num<0) {
            return def($parts[count($parts)+$token_num], false);
        } else {
            return def($parts[$token_num], false);
        }
    }

    public static function getCOOKIEval($name, $type = 'string', $default = null)
    {
        $var = (isset($_COOKIE[$name]) ? $_COOKIE[$name] : $default);
        settype($var, $type);
        return $var;
    }

    public static function getDomainName()
    {
        return self::getUrlPart(-2) . '.' . self::getUrlPart(-1);
    }

    public static function oneLessSubdomain($domain_name = null)
    {
        $parts = explode('.', ($domain_name ? $domain_name : $_SERVER['SERVER_NAME']));
        if (count($parts) < 2) {
            return $_SERVER['SERVER_NAME'];
        }
        
        unset($parts[0]);
        
        return implode('.', $parts);
    }
}
