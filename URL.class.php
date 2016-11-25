<?php namespace EmPHyre;

/**
 * URL is a class to handle all sorts of URL-related functions
 * An object for the EmPHyre project
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
 * @package  EmPHyre
 * @author   Julian Haagsma <jhaagsma@gmail.com>
 * @author   Dave McVittie <dave.mcvittie@gmail.com>
 * @license  All files are licensed under the MIT License.
 * @link     https://github.com/jhaagsma/emPHyre
 * @since    Recombined from empiresPHPframework extensions on Nov 4 2016
 */

class URL
{
    protected function __construct()
    {
        //protected construct so that URLHandler can't be instantiated
    }

    public static function redirect($loc)
    {
        header("Location: $loc", true, 303);
        echo "Redirecting to: <a href='".htmlentities($loc)."'>$loc</a>";
        exit;
    }

    public static function moved($loc)
    {
        header("Location: $loc", true, 301);
        echo "Redirecting to: <a href='".htmlentities($loc)."'>$loc</a>";
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
        if ($token_num < 0) {
            return def($parts[count($parts) + $token_num], false);
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
        return self::getUrlPart(-2).'.'.self::getUrlPart(-1);
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

    public static function parent($url, $levels = 1)
    {
        $url   = trim($url, '/');
        $url   = explode('/', $url);
        $count = count($url);
        if ($count > $levels - 1) {
            $i = 1;
            while ($i <= $levels) {
                unset($url[$count - $i]);
                $i++;
            }
        }

        $url = '/'.implode('/', $url);
        return $url;
    }

    public static function gparent($url)
    {
        return self::parent($url, 2);
    }

    public static function returnGetData($data)
    {
        $get = null;
        foreach ($data as $key => $val) {
            if (is_array($val)) {
                foreach ($val as $subkey => $subval) {
                    $get .= '&'.urlencode($key).'['.$subkey.']='.urlencode($subval);
                }

                continue;
            }

            $get .= '&'.urlencode($key).'='.urlencode($val);
        }

        return $get;
    }

    //http://php.net/manual/en/function.base64-encode.php#103849
    public static function encode64($data)
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
    //http://php.net/manual/en/function.base64-encode.php#103849
    public static function decode64($data)
    {
        return base64_decode(str_pad(strtr($data, '-_', '+/'), strlen($data) % 4, '=', STR_PAD_RIGHT));
    }
}
