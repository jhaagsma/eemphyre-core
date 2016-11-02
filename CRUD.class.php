<?php
/**
 * CRUD is the create/request/update/delete base class to extend for various
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
 * @since    April 2016
 */

namespace EmPHyre;

abstract class CRUD
{
    protected static $db; //the database
    protected static $_table_name = null;
    protected static $_primary_key = 'id';
    protected $_data;


    //this from FuelPHP
    protected static function primaryKey()
    {
        return isset(static::$_primary_key) ? static::$_primary_key : 'id';
    }

    public function __construct($primary_key = 0)
    {
        //do nothing, for now
        $pk = static::$_primary_key;
        $this->$pk = $primary_key;
    }

    public function setDb($db = null)
    {
        if ($db == null) {
            static::$db = Container::getDb();
        }
        static::$db = $db;
    }

    public static function db($db = null)
    {
        static::$db = Container::getDb();
    }

    public function initialize()
    {
        if (!static::$_table_name) {
            trigger_error('TABLE NAME NOT SET IN ' . get_class($this));
        }
        $pk = static::$_primary_key;
        //changed $info to $this->_data; adopting FuelPHP ideas
        $this->_data = static::$db->pquery(
            'SELECT `' . static::$_table_name . '`.* '.
            'FROM `'  . static::$_table_name .
            '` WHERE `' . static::$_primary_key . '`=?',
            $this->$pk
        )->fetchRow();

        $this->setVars();
    }

    protected function setVars()
    {
        if (!isset($this->_data)) {
            return;
        }
        //implements array_to_obj_values from general.php; by Slagpit
        foreach ($this->_data as $key => $value) {
            $this->$key = $value;
        }
    }

    protected function commit()
    {
        $update = [];
        $pk = static::$_primary_key;

        foreach ($this->_data as $key => $value) {
            if (!isset($this->$pk) || $key == $this->$pk || $this->$pk != $this->_data[$pk]) {
                //never commit a change to the primary key, that would be weird
                continue;
            }

            if (!isset($this->$key)) {
                //make sure the key exists
                continue;
            }

            if ($value != $this->$key) {
                $update[$key] = $this->$key;
            }
        }

        if (empty($update)) {
            return;
        }

        $call_args = $bits = [];
        $call_args[0] = null;

        foreach ($update as $key => $value) {
            $bits[] = "`$key` = ?";
            $call_args[] = $value;
        }

        $query = "UPDATE `".static::$_table_name."` SET ".implode(", ", $bits).
            ' WHERE `' . static::$_primary_key . '`=?';
        $call_args[0] = $query;
        $call_args[] = $this->$pk;


        $updated = static::$db->pqueryArray($call_args)->affectedRows();

        if ($updated) {
            $this->initialize();
            return true;
        } else {
            return false;
        }
    }

    public static function primaryList($limit = null, $offset = 0, $asc = true)
    {
        if (!static::$db) {
            static::$db = Container::getDb();
        }

        $dir = ($asc ? 'ASC' : 'DESC');

        return static::$db->pquery(
            'SELECT `' . static::$_primary_key .
            '` FROM `' . static::$_table_name .
            '` ORDER BY `'.static::$_primary_key.'` '.$dir
        )->fetchFieldSet();
    }

    public static function primaryListNotDisabled($limit = null, $offset = 0, $asc = true)
    {
        if (!static::$db) {
            static::$db = Container::getDb();
        }

        $dir = ($asc ? 'ASC' : 'DESC');

        return static::$db->pquery(
            'SELECT `' . static::$_primary_key .
            '` FROM `' . static::$_table_name .
            '` WHERE NOT disabled' .
            ' ORDER BY `'.static::$_primary_key.'` '.$dir
        )->fetchFieldSet();
    }

    public static function verifyExists($primary_key)
    {
        if (!static::$db) {
            static::$db = Container::getDb();
        }

        return static::$db->pquery(
            'SELECT `' . static::$_primary_key .
            '` FROM `' . static::$_table_name .
            '` WHERE `' . static::$_primary_key . '`=?',
            $primary_key
        )->fetchField();
    }


    /**
     * Finds all records.
     * Modified from FuelPHP
     *
     * @param    array     $config     array containing query settings
     */
/*
    public static function find($config = array())
    {
        $config = $config + array(
            'select' => array(static::$_table_name.'.*'),
            'where' => array(),
            'order_by' => array(),
            'limit' => null,
            'offset' => 0,
        );

        extract($config); //this is okay because we know exactly what it has

        //this is a clever way of doing this.
        is_string($select) && $select = array($select);

        $query = 'SELECT ' . implode(',',$select);
        $query .= ' FROM ' . static::$_table_name;
        if (!empty($where)) {
            $query .= ' WHERE ';
            $first = true;
            foreach ($where as $w) {
                if (!$first) {
                    $query .= ' AND ';
                }
                $query .= '`' . $w[0] '`' . $w[1] . '?';
            }
        }
    }
*/
    public function display()
    {
        //default return the primary key
        return $this->getId();
    }

    public function getId()
    {
        $pk = static::$_primary_key;
        return $this->$pk;
    }
}
