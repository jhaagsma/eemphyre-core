<?php

/**
 * Container is the class creation object for the EmPHyre project
 *
 * PHP version 7
 *
 * ------
 * This project uses the EmPHyre microframework,
 * and is built off the EmPHyre example files
 *
 * Written by Julian Haagsma.
 *
 * @category Classes
 * @package  EmPHyre
 * @author   Julian Haagsma <jhaagsma@gmail.com>
 * @license  All files are licensed under the MIT License.
 * @link     https://demo3.phasesensors.com
 * @since    March 2016
 */

namespace EmPHyre;

// prototype from http://code.tutsplus.com/tutorials/dependency-injection-huh--net-26903
class Container
{
    /**
     * Array of database connections, mysqli
     **/

    private static $instances    = array();
    public static $params        = array();
    public static $userNamespace = null;

    /*
        public function __construct($params)
        {
        self::_params = $params;
    }*/

    /**
     * Get the default database name, or return what's given
     *
     * @param  string $database A database name
     *
     * @return string           A database name
     */
    private static function getDbName($database = null)
    {
        return ($database == null ? self::$params['db']['default']['db_name'] : $database);

    }//end getDbName()

    /**
     * Get the default database host, or return what's given
     *
     * @param  string $database A database host
     *
     * @return string           A database host
     */
    private static function getDbHost($database = null)
    {
        $database = self::getDbName($database);

        if (isset(self::$params['db'][$database]['db_host'])) {
            return self::$params['db'][$database]['db_host'];
        }

        return self::$params['db']['default']['db_host'];
    }//end getDbHost()

    /**
     * Get the default database user, or return what's given
     *
     * @param  string $database A database user
     *
     * @return string           A database user
     */
    private static function getDbUser($database = null)
    {
        $database = self::getDbName($database);

        if (isset(self::$params['db'][$database]['db_user'])) {
            return self::$params['db'][$database]['db_user'];
        }

        return self::$params['db']['default']['db_user'];
    }//end getDbUser()

    /**
     * Get the default database password, or return what's given
     *
     * @param  string $database A database password
     *
     * @return string           A database password
     */
    private static function getDbPass($database = null)
    {
        $database = self::getDbName($database);

        if (isset(self::$params['db'][$database]['db_pass'])) {
            self::$params['db'][$database]['db_pass'];
        }

        return self::$params['db']['default']['db_pass'];
    }//end getDbPass()

    /**
     * Get a database instance, based on database name
     *
     * @param  string $database A database name
     *
     * @return object           MysqlDb Object
     */
    public static function getDb($database = null)
    {
        $database = self::getDbName($database);

        if (empty(self::$instances[$database])) {
            self::$instances[$database] = new \EmPHyre\MysqlDb(
                self::getDbHost($database),
                $database,
                self::getDbUser($database),
                self::getDbPass($database),
                false,
                'counters',
                false
            );
            // make 3 more things to handle more parameters
        }

        self::$instances[$database]->createIfNotExists();

        return self::$instances[$database];

    }//end getDb()

    /**
     * Get a new user, using a UUID
     *
     * @param  string  $uuid       The UUID
     * @param  boolean $clearcache Whether or not to clear cache; mostly for login
     *
     * @return object              A User Object
     */
    public static function newUserFromUUID($uuid = null, $clearcache = false)
    {
        $userid = User::getUserIdFromUUID($uuid);
        return self::newUser($userid, $clearcache);

    }//end newUserFromUUID()

    /**
     * Get a new user, using a User Id
     *
     * @param  integer $userid     The User Id
     * @param  boolean $clearcache Whether or not to clear cache; mostly for login
     *
     * @return object              A User Object
     */
    public static function newUser($userid = 0, $clearcache = false)
    {
        if (static::$userNamespace) {
            $class = "\\".static::$userNamespace."\\User";
            $user  = new $class($userid);
        } else {
            $user = new User($userid);
        }

        $user->setDb(self::getDb());
        // use the default database
        $user->initialize($clearcache);
        return $user;

    }//end newUser()

    /**
     * Get a new user, using a user name
     *
     * @param  string  $username   The User nmae
     * @param  boolean $clearcache Whether or not to clear cache; mostly for login
     *
     * @return object              A User Object
     */
    public static function newUserFromName($username = null, $clearcache = false)
    {
        $userid = User::getUserIdFromName($username);
        return self::newUser($userid, $clearcache);

    }//end newUserFromName()
}//end class
