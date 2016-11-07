<?php namespace EmPHyre;

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

//prototype from http://code.tutsplus.com/tutorials/dependency-injection-huh--net-26903
class Container
{
    protected function __construct()
    {
        //protected so it can't be instantiated
    }
    /**
    * Array of database connections, mysqli
    **/

    private static $instances = array();
    public static $params = array();
    public static $userNamespace = null;

    /*public function __construct($params)
    {
        self::_params = $params;
    }*/

    private static function getDbName($database = null)
    {
        return ($database == null ? self::$params['db']['default']['db_name'] : $database);
    }

    private static function getDbHost($database = null)
    {
        $database = self::getDbName($database);

        return ( isset(self::$params['db'][$database]['db_host']) ?
            self::$params['db'][$database]['db_host'] :
            self::$params['db']['default']['db_host'] );
    }

    private static function getDbUser($database = null)
    {
        $database = self::getDbName($database);

        return ( isset(self::$params['db'][$database]['db_user']) ?
            self::$params['db'][$database]['db_user'] :
            self::$params['db']['default']['db_user'] );
    }

    private static function getDbPass($database = null)
    {
        $database = self::getDbName($database);

        return ( isset(self::$params['db'][$database]['db_pass']) ?
            self::$params['db'][$database]['db_pass'] :
            self::$params['db']['default']['db_pass'] );
    }

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
            ); //make 3 more things to handle more parameters
        }

        self::$instances[$database]->createIfNotExists();

        return self::$instances[$database];
    }

    public static function newUserFromUUID($uuid = null, $clearcache = false)
    {
        $userid = User::getUserIdFromUUID($uuid);
        return self::newUser($userid, $clearcache);
    }

    public static function newUser($userid = 0, $clearcache = false)
    {
        if (static::$userNamespace) {
            $class = "\\".static::$userNamespace."\\User";
            $user = new $class($userid);
        } else {
            $user = new User($userid);
        }
        $user->setDb(self::getDb()); //use the default database
        $user->initialize($clearcache);
        return $user;
    }

    public static function newUserFromName($username = null, $clearcache = false)
    {
        $userid = User::getUserIdFromName($username);
        return self::newUser($userid, $clearcache);
    }
}
