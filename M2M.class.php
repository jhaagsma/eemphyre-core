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

class M2M
{
    protected static $db; //the database
    protected static $_table_name = null;
    protected static $_primary_key_1 = 'id1';
    protected static $_primary_key_2 = 'id2';
    protected $_data;

    public function __construct($table_name, $primary_key_1 = 'id1', $primary_key_2 = 'id2')
    {
        static::$_table_name = $table_name;
        static::$_primary_key_1 = $primary_key_1;
        static::$_primary_key_2 = $primary_key_2;
        $this->initialize();
    }

    public function setDb($db = null)
    {
        if ($db == null) {
            static::$db = Container::getDb();
        }
        static::$db = $db;
    }

    //simple version for basic classes
    public static function db()
    {
        if (!static::$db) {
            static::$db = Container::getDb();
        }
    }

    public function initialize()
    {
        if (!static::$_table_name) {
            trigger_error('TABLE NAME NOT SET IN ' . get_class($this));
        }
    }

    public function checkArray($pk1_value = 0, $pk2_array = [])
    {
        if (empty($pk2_array)) {
            return false;
        }

        static::db();
        return static::$db->pquery(
            "SELECT 1 FROM `".static::$_table_name."` WHERE `".
            static::$_primary_key_1."`=? AND `".
            static::$_primary_key_2."` IN(?)",
            $pk1_value,
            $pk2_array
        )->fetchField();
    }

    public function getM2M($pk_value = 0, $use_pk2 = false)
    {
        static::db();
        return static::$db->pquery(
            "SELECT `".
            ($use_pk2 ? static::$_primary_key_1 : static::$_primary_key_2 ).
            "` FROM `".static::$_table_name."` WHERE `".
            ($use_pk2 ? static::$_primary_key_2 : static::$_primary_key_1 ).
            "`=?",
            $pk_value
        )->fetchFieldSet();
    }

    public function countM2M($pk_value = 0, $use_pk2 = false)
    {
        static::db();
        return static::$db->pquery(
            "SELECT COUNT(`".
            ($use_pk2 ? static::$_primary_key_1 : static::$_primary_key_2 ).
            "`) FROM `".static::$_table_name."` WHERE `".
            ($use_pk2 ? static::$_primary_key_2 : static::$_primary_key_1 ).
            "`=?",
            $pk_value
        )->fetchField();
    }

    public function add($pk1_value, $pk2_value, $use_pk2 = false)
    {
        static::db();
        return static::$db->pquery(
            "INSERT INTO `".static::$_table_name.'` SET `'.
            static::$_primary_key_1."`=?, `".
            static::$_primary_key_2."`=?",
            $pk1_value,
            $pk2_value
        )->affectedRows();
    }

    public function delete($pk1_value, $pk2_value, $use_pk2 = false)
    {
        static::db();
        return static::$db->pquery(
            "DELETE FROM `".static::$_table_name.'` WHERE `'.
            static::$_primary_key_1."`=? AND `".
            static::$_primary_key_2."`=?",
            $pk1_value,
            $pk2_value
        )->affectedRows();
    }
}
