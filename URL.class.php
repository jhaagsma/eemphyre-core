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
    /**
     * Protected construct so that URLHandler can't be instantiated
     *
     * @return null
     */
    protected function __construct()
    {
    }//end __construct()


    public static function redirect($loc)
    {
        header("Location: $loc", true, 303);
        echo "Redirecting to: <a href='".htmlentities($loc)."'>$loc</a>";
        exit;
    }//end redirect()


    public static function moved($loc)
    {
        header("Location: $loc", true, 301);
        echo "Redirecting to: <a href='".htmlentities($loc)."'>$loc</a>";
        exit;
    }//end moved()


    public static function sendJsHeader()
    {
        header('Content-Type: application/javascript');
    }//end sendJsHeader()


    public static function sendJsonHeader()
    {
        header('Content-Type: application/json');
    }//end sendJsonHeader()


    public static function getUrlPart($token_num)
    {
        $parts = explode('.', $_SERVER['SERVER_NAME']);
        if ($token_num < 0) {
            return $parts[count($parts) + $token_num] ?? false;
        } else {
            return $parts[$token_num] ?? false;
        }
    }//end getUrlPart()


    public static function getCOOKIEval($name, $type = 'string', $default = null)
    {
        $var = $_COOKIE[$name] ?? $default;
        settype($var, $type);
        return $var;
    }//end getCOOKIEval()


    public static function getDomainName()
    {
        return self::getUrlPart(-2).'.'.self::getUrlPart(-1);
    }//end getDomainName()


    public static function oneLessSubdomain($domain_name = null)
    {
        $parts = explode('.', ($domain_name ? $domain_name : $_SERVER['SERVER_NAME']));
        if (count($parts) < 2) {
            return $_SERVER['SERVER_NAME'];
        }

        unset($parts[0]);

        return implode('.', $parts);
    }//end oneLessSubdomain()


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
    }//end parent()


    public static function gparent($url)
    {
        return self::parent($url, 2);
    }//end gparent()


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
    }//end returnGetData()


    //http://php.net/manual/en/function.base64-encode.php#103849
    public static function encode64($data)
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }//end encode64()

    //http://php.net/manual/en/function.base64-encode.php#103849
    public static function decode64($data)
    {
        return base64_decode(str_pad(strtr($data, '-_', '+/'), strlen($data) % 4, '=', STR_PAD_RIGHT));
    }//end decode64()
}//end class
