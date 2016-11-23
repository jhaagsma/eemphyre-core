<?php namespace EmPHyre;

/**
 * Validate is the validation object for the EmPHyre project
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
 * @license  All files are licensed under the MIT License.
 * @link     https://github.com/jhaagsma/emPHyre
 * @since    Recombined from empiresPHPframework extensions on Nov 4 2016
 */

class Validate
{


    public static function email($email)
    {
        $email = filter_var($email, FILTER_VALIDATE_EMAIL);
        // returns the email or false
        if ($email) {
            return false;
        }

        return new Result('EMAIL_NOT_VALID');

    }//end email()


    public static function password($pwd, $pwd2, $length = 8, $letters = true, $numbers = true, $specials = true)
    {
        if ($pwd != $pwd2) {
            return new Result('PASSWORD_NOMATCH');
        } elseif (strlen($pwd) < $length) {
            return new Result('PASSWORD_SHORT', $length);
        } elseif ($letters && !preg_match("/[A-z]/", $pwd)) {
            return new Result('PASSWORD_NO_LETTER');
        } elseif ($numbers && !preg_match("/[0-9]/", $pwd)) {
            // && preg_match("/[^A-Za-z0-9]/",$pwd)
            return new Result('PASSWORD_NO_NUMBER');
        } elseif ($specials && !preg_match("/[^A-Za-z0-9]/", $pwd)) {
            return new Result('PASSWORD_NO_SPECIAL');
        }

        return false;

    }//end password()


    public static function sanitizeName($name)
    {
        return preg_replace("/[^A-Za-z0-9\s]/", null, $name);

    }//end sanitizeName()
}//end class
