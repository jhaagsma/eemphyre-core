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
    protected static $tableName  = null;
    protected static $primaryKey = 'id';
    protected $data;



    /**
     * GET the primary key
     * this from FuelPHP
     *
     * @return string The name of the primary key column
     */
    protected static function primaryKey()
    {
        return isset(static::$primaryKey) ? static::$primaryKey : 'id';
    }//end primaryKey()


    /**
     * The construct!! takes a primary key
     *
     * @param integer $primary_key The primary key
     *
     * @return The object... obviously..?
     */
    public function __construct($primary_key = 0)
    {
        //do nothing, for now
        $pk        = static::$primaryKey;
        $this->$pk = $primary_key;
    }//end __construct()


    /**
     * Set the database...
     *
     * @param Object $db A MysqlDb object
     *
     * @return null
     */
    public function setDb($db = null)
    {
        if ($db == null) {
            static::$db = Container::getDb();
        }


        static::$db = $db;
    }//end setDb()


    /**
     * Set the database, simplified version
     * (simple version for basic classes)
     *
     * @return [type] [description]
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

        $pk = static::$primaryKey;
        //changed $info to $this->_data; adopting FuelPHP ideas
        $this->_data = static::$db->pquery(
            'SELECT `'.static::$tableName.'`.* '.'FROM `'.static::$tableName.'` WHERE `'.static::$primaryKey.'`=?',
            $this->$pk
        )->fetchRow();

        $this->setVars();
    }//end initialize()


    protected function setVars()
    {
        if (!isset($this->_data)) {
            return;
        }

        //implements array_to_obj_values from general.php; by Slagpit
        foreach ($this->_data as $key => $value) {
            $this->$key = $value;
        }
    }//end setVars()


    /**
     * Commit the things that have been changed
     *
     * @return boolean Returns if an update was successful
     */
    protected function commit()
    {
        $update = [];

        $pk = static::$primaryKey;

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

        $call_args    = $bits = [];
        $call_args[0] = null;

        foreach ($update as $key => $value) {
            $bits[]      = "`$key` = ?";
            $call_args[] = $value;
        }

        $query = "UPDATE `".static::$tableName
            ."` SET ".implode(", ", $bits)
            .' WHERE `'.static::$primaryKey.'`=?';

        $call_args[0] = $query;
        $call_args[]  = $this->$pk;


        $updated = static::$db->pqueryArray($call_args)->affectedRows();

        if ($updated) {
            $this->initialize();
            return true;
        } else {
            return false;
        }
    }//end commit()


    protected static function addByArray($keyValue = [])
    {
        static::db();
        if (empty($keyValue)) {
            return;
        }

        $call_args    = $bits = [];
        $call_args[0] = null;

        foreach ($keyValue as $key => $value) {
            $bits[]      = "`$key` = ?";
            $call_args[] = $value;
        }

        $query = "INSERT INTO `".static::$tableName."` SET ".implode(", ", $bits);

        $call_args[0] = $query;

        //return insertid; not sure what to do for insert fail...
        return static::$db->pqueryArray($call_args)->insertid();
    }//end addByArray()


    public static function primaryList($limit = null, $offset = 0, $asc = true)
    {
        static::db();

        $dir = ($asc ? 'ASC' : 'DESC');

        return static::$db->pquery(
            'SELECT `'.static::$primaryKey.'` FROM `'.static::$tableName.'` ORDER BY `'.static::$primaryKey.'` '.$dir
        )->fetchFieldSet();
    }//end primaryList()


    /**
     * This returns primary keys FROM table WHERE $column = $value
     *
     * @param  string  $column The column you want to specify a value of
     * @param  mixed   $value  The value you want to specify for column
     * @param  integer $limit  The limit; this is not enabled??
     * @param  integer $offset The offset; this is not implemented??
     * @param  boolean $asc    The sort direction
     *
     * @return array           Primary Keys!!
     */
    public static function filterColumn($column, $value, $limit = 0, $offset = 0, $asc = true)
    {
        static::db();

        $dir = ($asc ? 'ASC' : 'DESC');

        return static::$db->pquery(
            'SELECT `'.static::$primaryKey.'` FROM `'
            .static::$tableName.'` WHERE `'.$column.'`=?'
            .' ORDER BY `'.static::$primaryKey.'` '.$dir,
            $value
        )->fetchFieldSet();

    }//end filterColumn()

    /**
     * This returns the primary key FROM table WHERE (UNIQUE!!) $column = $value
     * ASSUMES that the column is, in fact, unique
     *
     * @param  string $column The column you want to specify a value of
     * @param  mixed  $value  The value you want to specify for column
     *
     * @return array           Primary Key!!
     */
    public static function getUniqueId($column, $value)
    {
        static::db();


        return static::$db->pquery(
            'SELECT `'.static::$primaryKey.'` FROM `'
            .static::$tableName.'` WHERE `'.$column.'`=?',
            $value
        )->fetchField();

    }//end getUniqueId()

    public static function filterPKArray($keys, $column, $value, $limit = null, $offset = 0, $asc = true)
    {
        static::db();

        $dir = ($asc ? 'ASC' : 'DESC');

        if (empty($keys)) {
            return [];
        }

        return static::$db->pquery(
            'SELECT `'.static::$primaryKey
            .'` FROM `'.static::$tableName
            .'` WHERE `'.static::$primaryKey.'` IN(?) AND `'.$column.'`=?'
            .' ORDER BY `'.static::$primaryKey.'` '.$dir,
            $keys,
            $value
        )->fetchFieldSet();
    }//end filterPKArray()


    public static function primaryListNotDisabled($limit = null, $offset = 0, $asc = true)
    {
        return static::filterColumn('disabled', 'false');
    }//end primaryListNotDisabled()

    /**
     * Disable the object
     * THIS KINDOF ASSUMES THERE'S A "DISABLED" COLUMN
     *
     * @return boolean If it worked or not
     */
    public function disable()
    {
        $this->disabled = true;
        return $this->commit();
    }//end disable()


    /**
     * Enable the object; this probaly won't get used...?
     * THIS KINDOF ASSUMES THERE'S A "DISABLED" COLUMN
     *
     * @return boolean If it worked or not
     */
    public function enable()
    {
        $this->disabled = false;
        return $this->commit();
    }//end enable()


    public static function verifyExists($primary_key)
    {
        if (!static::$db) {
            static::$db = Container::getDb();
        }

        return static::$db->pquery(
            'SELECT `'.static::$primaryKey.'` FROM `'.static::$tableName.'` WHERE `'.static::$primaryKey.'`=?',
            $primary_key
        )->fetchField();
    }//end verifyExists()


    protected static function newUUID($check = true, $uuidColumn = 'uuid')
    {
        static::db();
        $uuid = static::$db->newUUID();
        while ($check && static::checkUUIDCollision($uuid, $uuidColumn)) {
            $uuid = static::$db->newUUID();
        }

        return $uuid;
    }//end newUUID()


    protected static function checkUUIDCollision($uuid = null, $uuidColumn = 'uuid')
    {
        static::db();
        $check = static::$db->pquery(
            "SELECT `".$uuidColumn."` FROM `".static::$tableName."` WHERE uuid = ?",
            $uuid
        )->fetchField();

        return $check || $uuid === null ? true : false;
    }//end checkUUIDCollision()




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
            'select' => array(static::$tableName.'.*'),
            'where' => array(),
            'order_by' => array(),
            'limit' => null,
            'offset' => 0,
        );

        extract($config); //this is okay because we know exactly what it has

        //this is a clever way of doing this.
        is_string($select) && $select = array($select);

        $query = 'SELECT ' . implode(',',$select);
        $query .= ' FROM ' . static::$tableName;
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

    /**
     * A basic display type function
     *
     * @return integer The (primaryKey) value
     */
    public function display()
    {
        //default return the primary key
        return $this->getId();
    }//end display()


    /**
     * A basic display type function
     *
     * @return integer The (primaryKey) value
     */
    public function getId()
    {
        $pk = static::$primaryKey;
        return $this->$pk;
    }//end getId()
}//end class
