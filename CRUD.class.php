<?php
/**
 * Route is the route information holder for the routing object
 *
 * PHP version 5
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
 * @since    Pulled out of PHPRouter.class.php 2016-03-15
 */

namespace EmPHyre;

class CRUD
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
        //implements array_to_obj_values from general.php; by Slagpit
        foreach ($this->_data as $key => $value) {
            $this->$key = $value;
        }
    }

    public static function primaryList($limit = null, $offset = 0)
    {
        if (!static::$db) {
            static::$db = Container::getDb();
        }

        return static::$db->pquery(
            'SELECT `' . static::$_primary_key .
            '` FROM `' . static::$_table_name .
            '` ORDER BY `' . static::$_primary_key . '` ASC'
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
