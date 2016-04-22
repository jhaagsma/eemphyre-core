<?php
/**
 * Container is the class creation object for the PhaseWeb project
 *
 * PHP version 5
 *
 * ------
 * These files are part of the PhaseWeb project;
 * This project uses the EmPHyre microframework,
 * and is built off the EmPHyre example files
 *
 * Written for PhaseSensors Julian Haagsma.
 *
 * @category Classes
 * @package  PhaseWeb
 * @author   Julian Haagsma <jhaagsma@gmail.com>
 * @license  All files are licensed under the MIT License.
 * @link     https://demo3.phasesensors.com
 * @since    PhaseWeb was created 2014-09, modernized to current in 2016-03
 */

namespace EmPHyre;

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

        return self::$instances[$database];
    }
}
