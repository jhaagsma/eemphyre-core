<?php namespace EmPHyre;

/**
 * Auth is the authentication object for the EmPHyre project
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
 * @author   Timo Ewalds <tewalds@gmail.com>
 * @author   Dave McVittie <dave.mcvittie@gmail.com>
 * @author   Joe Obbish <slagpit@earthempires.com>
 * @license  All files are licensed under the MIT License.
 * @link     https://github.com/jhaagsma/emPHyre
 * @since    Recombined from empiresPHPframework extensions on Nov 4 2016
 */


class Auth
{
    public static function authUsernamePassword($username, $password)
    {
        $user = Container::newUserFromName($username, true);
        if (!is_object($user) || !$user->user_id) {
            URL::redirect("/login?result=FAIL");
        } elseif (!$user->checkPassword($password)) {
            URL::redirect("/login?result=FAIL");
        } elseif ($user->isDisabled()) {
            //make result= from Result returned from class?
            URL::redirect("/login?result=DISABLED_USER");
        }
        $user->loggedIn();
        return $user;
    }

    public static function authPublic($data, $path, $user)
    {
        return false;
    }

    public static function authLogin($data, $path, $user)
    {
        if (!$user) {
            URL::redirect('/login?result=NOT_LOGGED_IN');
        }

        return false;
    }
}
