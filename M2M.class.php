<?php
/**
 * M2M is the Many-to-Many base class to extend for various
 * classes and objects
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
 * @since    November 2016
 */

namespace EmPHyre;

/**
 * This class is designed to be the glue between two different tables/classes
 * users_permission_groups or some such, for example
 *
 * @category Database_Interface
 * @package  EmPHyre
 * @author   Julian Haagsma <jhaagsma@gmail.com>
 * @link     https://github.com/jhaagsma/emPHyre
 */
class M2M
{
    // The database.
    protected static $db;
    protected static $tableName    = null;
    protected static $primaryKey_1 = 'id1';
    protected static $primaryKey_2 = 'id2';


    public function __construct($table_name, $primary_key_1 = 'id1', $primary_key_2 = 'id2')
    {
        static::$tableName    = $table_name;
        static::$primaryKey_1 = $primary_key_1;
        static::$primaryKey_2 = $primary_key_2;
        $this->initialize();

    }//end __construct()


    public function setDb($db = null)
    {
        if ($db == null) {
            static::$db = Container::getDb();
        }

        static::$db = $db;

    }//end setDb()


    /**
     * Simple version for basic classes
     */
    public static function db()
    {
        if (!static::$db) {
            static::$db = Container::getDb();
        }

    }//end db()


    public function initialize()
    {
        if (!static::$tableName) {
            trigger_error('TABLE NAME NOT SET IN '.get_class($this));
        }

    }//end initialize()


    public function checkArray($pk1_value = 0, $pk2_array = [])
    {
        if (empty($pk2_array)) {
            return false;
        }

        static::db();
        return static::$db->pquery(
            "SELECT 1 FROM `".static::$tableName.
            "` WHERE `".static::$primaryKey_1."`=? AND `".
            static::$primaryKey_2."` IN(?)",
            $pk1_value,
            $pk2_array
        )->fetchField();

    }//end checkArray()


    public function getM2M($pk_value = 0, $use_pk2 = false)
    {
        static::db();
        return static::$db->pquery(
            "SELECT `".
            ($use_pk2 ? static::$primaryKey_1 : static::$primaryKey_2).
            "` FROM `".static::$tableName.
            "` WHERE `".
            ($use_pk2 ? static::$primaryKey_2 : static::$primaryKey_1 )."`=?",
            $pk_value
        )->fetchFieldSet();

    }//end getM2M()


    public function countM2M($pk_value = 0, $use_pk2 = false)
    {
        static::db();
        return static::$db->pquery(
            "SELECT COUNT(`".
            ($use_pk2 ? static::$primaryKey_1 : static::$primaryKey_2 ).
            "`) FROM `".static::$tableName.
            "` WHERE `".
            ($use_pk2 ? static::$primaryKey_2 : static::$primaryKey_1 )."`=?",
            $pk_value
        )->fetchField();

    }//end countM2M()


    public function add($pk1_value, $pk2_value, $use_pk2 = false)
    {
        static::db();
        return static::$db->pquery(
            "INSERT INTO `".static::$tableName.
            '` SET `'.static::$primaryKey_1."`=?, `".
            static::$primaryKey_2."`=?",
            $pk1_value,
            $pk2_value
        )->affectedRows();

    }//end add()


    public function delete($pk1_value, $pk2_value, $use_pk2 = false)
    {
        static::db();
        return static::$db->pquery(
            "DELETE FROM `".static::$tableName.
            '` WHERE `'.static::$primaryKey_1."`=? AND `".
            static::$primaryKey_2."`=?",
            $pk1_value,
            $pk2_value
        )->affectedRows();

    }//end delete()
}//end class
