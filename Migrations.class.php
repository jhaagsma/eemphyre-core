<?php namespace EmPHyre;

/**
 * Migrations is the migration abstract class for migrations
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
 * @since    May 2016
 */

abstract class Migrations
{
    protected static $db;
    // the database
    protected $version;


    public function __construct($name)
    {
        $this->db();
        $this->version = $name;

    }//end __construct()


    public static function db($db = null)
    {
        static::$db = Container::getDb();

    }//end db()


    abstract public function up();


    abstract public function down();


    public function out($string)
    {
        trigger_error($string, E_USER_NOTICE);

    }//end out()


    protected function outUpgraded()
    {
        $this->out("Upgraded to version ".$this->version);

    }//end outUpgraded()


    protected function outDowngraded()
    {
        $this->out("Downgraded to version ".$this->version);

    }//end outDowngraded()
}//end class
