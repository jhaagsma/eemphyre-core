<?php
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
 * @license  https://opensource.org/licenses/MIT The MIT License (MIT)
 * @link     https://github.com/jhaagsma/emPHyre
 * @since    Recombined from empiresPHPframework extensions on Nov 4 2016
 */

namespace EmPHyre;

class Auth
{

    /**
     * Authenticate a username and password;
     * THIS REDIRECTS AUTOMATICALLY ON FAIL
     *
     * @param  string $user_name The username
     * @param  string $password  The password
     *
     * @return User              The User Object
     */
    public static function authUsernamePassword($user_name, $password)
    {
        $user = Container::newUserFromName($user_name, true);
        if (!is_object($user) || !$user->user_id) {
            URL::redirect("/login?result=FAIL");
        } elseif (!$user->checkPassword($password)) {
            URL::redirect("/login?result=FAIL");
        } elseif ($user->isDisabled()) {
            // make result= from Result returned from class?
            URL::redirect("/login?result=DISABLED_USER");
        }

        $user->loggedIn();
        return $user;
    }//end authUsernamePassword()

    /**
     * Authenticate public
     * This can return a null user, but for now does nothing
     *
     * @param  array $data The incoming GET/POST
     * @param  Path  $path The Path object
     * @param  User  $user Either a User object or null
     *
     * @return bool        For now returns nothing
     */
    public static function authPublic($data, $path, $user)
    {
        return false;
    }//end authPublic()

    /**
     * Authenticate for login; just checks if there's a user object
     * THIS REDIRECTS AUTOMATICALLY ON FAIL
     *
     * @param  array $data The GET/POST
     * @param  Path  $path The Path object
     * @param  User  $user The User object
     *
     * @return bool        Returns false or REDIRECTS
     */
    public static function authLogin($data, $path, $user)
    {
        if (!$user) {
            URL::redirect('/login?result=NOT_LOGGED_IN');
        }

        return false;
    }//end authLogin()
}//end class
