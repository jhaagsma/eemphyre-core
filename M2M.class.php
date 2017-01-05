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
    protected static $tableName   = null;
    protected static $primaryKeys = ['id1', 'id2'];
    protected static $pkCount     = 2;

    /**
     * The construct
     *
     * @param string       $table_name    The name of the table
     * @param string|array $primary_key_1 A list of keys, or a single key
     * @param string|array $primary_key_2 A list of keys, or a single key
     */
    public function __construct($table_name, $primary_key_1 = [], $primary_key_2 = null)
    {
        static::$tableName = $table_name;

        $part1 = is_array($primary_key_1) ? $primary_key_1 : [$primary_key_1];
        $part2 = [];
        if ($primary_key_2 !== null) {
            $part2 = is_array($primary_key_2) ? $primary_key_2 : [$primary_key_2];
        }

        static::$primaryKeys = array_merge($part1, $part2);
        static::$pkCount     = count(static::$primaryKeys);
        $this->initialize();

    }//end __construct()

    /**
     * Basic setDb function; basically, initiate/set the database handle
     *
     * @param null|MysqlDb $db Optional, provide a $db
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
     * Simple version for basic classes
     *
     * @return null
     */
    public static function db()
    {
        if (!static::$db) {
            static::$db = Container::getDb();
        }

    }//end db()

    /**
     * Throw some errors if things aren't set up right
     *
     * @return null
     */
    public function initialize()
    {
        if (!static::$tableName) {
            trigger_error('TABLE NAME NOT SET IN '.get_class($this));
        }

        if (count(static::$primaryKeys) < 2) {
            trigger_error('NOT ENOUGH PRIMARY KEYS SET?');
        }
    }//end initialize()

    /**
     * This will search through the M2M for fixed primary keys
     * with a range for one, to see if that range exists or not
     *
     * @param  array|integer $pksFixedValues Array of fixed PK values, or single
     * @param  array         $pkSearchValues Array of variable PK to search
     * @param  null|integer  $pkIndex        Index of PK to search on, optional
     *
     * @return bool                          Returns 1 if the search was success
     */
    public function checkArray($pksFixedValues = 0, $pkSearchValues = [], $pkIndex = null)
    {
        if (empty($pkBSearch)) {
            return false;
        }

        if (!is_array($pksFixedValues)) {
            $pksFixedValues = [0 => $pksFixedValues];
        }

        $pkElim = static::$primaryKeys;

        $call_args    = [];
        $call_args[0] = null;
        foreach ($pksFixedValues as $pk => $fixedValue) {
            $searchConstraints .= self::getKey($pk)."`=? AND `";
            $call_args[]        = $fixedValue;

            if (!is_numeric($pk)) {
                $pk = array_search($pk, $pkElim);
            }

            unset($pkElim[$pk]);
        }

        if ($pkIndex != null) {
            $pkSearch = static::$primaryKeys[$pkIndex];
        } else {
            //BE WARNED, THIS WILL JUST TAKE THE FIRST UNCONSTRAINED PK
            $pkSearch = current($pkElim);
        }

        $query = "SELECT 1 FROM `".static::$tableName."` WHERE `".
            $searchConstraints.$pkSearch."` IN(?) LIMIT 1";

        $call_args[]  = $pkSearchValues;
        $call_args[0] = $query;

        static::db();
        return static::$db->pqueryArray($call_args)->fetchField();

    }//end checkArray()

    /**
     * This will get a FieldSet or RowSet based on the number of pks...
     * Baseically this is a SEARCH of an M2M table, with one (or more) element
     * fixed
     *
     * @param  integer|array $pksFixedValues Array of fixed PK values, or single
     * @param  null|integer  $pkIndex        Index of PK to search on, optional
     *
     * @return array|array(arrays)           Returns a FieldSet or RowSet
     */
    public function getM2M($pksFixedValues = 0, $pkIndex = null)
    {

        list($pksFixedValues, $pkIndex)
            = self::inputCompatibility($pksFixedValues, $pkIndex);

        $pkElim = static::$primaryKeys;

        $call_args    = $searchBits = [];
        $call_args[0] = null;
        foreach ($pksFixedValues as $pk => $fixedValue) {
            $searchBits[] = "`".self::getKey($pk)."`=?";
            $call_args[]  = $fixedValue;

            if (!is_numeric($pk)) {
                $pk = array_search($pk, $pkElim);
            }

            unset($pkElim[$pk]);
        }

        $fieldSet = true;
        if ($pkIndex != null) {
            $pkSearch = static::$primaryKeys[$pkIndex];
        } else {
            //decide if we're returning a FieldSet or RowSet
            $fieldSet = count($pkElim) == 1 ? true : false;
            $pkSearch = "`".implode("`, `", $pkElim)."`";
        }

        //$pkSearch is pre-escaped above
        $query = "SELECT $pkSearch FROM `".static::$tableName."` WHERE ".
            implode(' AND ', $searchBits);

        $call_args[0] = $query;


        static::db();
        $result = static::$db->pqueryArray($call_args);

        if ($fieldSet) {
            return $result->fetchFieldSet();
        } else {
            return $result->fetchRowSet();
        }
    }//end getM2M()

    /**
     * This will get a single number
     * Baseically this is a COUNT of an M2M table, with one (or more) element
     * fixed
     *
     * @param  integer|array $pksFixedValues Array of fixed PK values, or single
     * @param  null|integer  $pkIndex        Index of PK to search on, optional
     *
     * @return integer                       Returns a COUNT()
     */
    public function countM2M($pksFixedValues = 0, $pkIndex = null)
    {
        //new dBug($pksFixedValues);
        //new dBug($pkIndex);

        list($pksFixedValues, $pkIndex)
            = self::inputCompatibility($pksFixedValues, $pkIndex);

        //new dBug(static::$primaryKeys);
        //new dBug($pksFixedValues);
        //new dBug($pkIndex);

        $pkElim = static::$primaryKeys;

        //new dBug($pkElim);
        $call_args    = $searchBits = [];
        $call_args[0] = null;

        foreach ($pksFixedValues as $pk => $fixedValue) {
            $searchBits[] = "`".self::getKey($pk)."`=?";
            $call_args[]  = $fixedValue;

            if (!is_numeric($pk)) {
                $pk = array_search($pk, $pkElim);
            }

            unset($pkElim[$pk]);
            //new dBug($pkElim);
        }

        //$pkSearch is pre-escaped above
        $query = "SELECT COUNT(1) FROM `".static::$tableName."` WHERE ".
            implode(' AND ', $searchBits);

        $call_args[0] = $query;

        //new dBug($call_args);

        static::db();
        return static::$db->pqueryArray($call_args)->fetchField();
    }//end countM2M()

    /**
     * This massages the datatypes to handle compatibility with old style
     * It also allows simpler usage for 2-column PK tables
     *
     * @param  val|array     $pksFixedValues Fixed values
     * @param  null|int|bool $pkIndex        The primary key index
     *
     * @return array                         Returns an array of fixed vars
     */
    private static function inputCompatibility($pksFixedValues = 0, $pkIndex = null)
    {
        if (!is_array($pksFixedValues)) {
            //this is for compatibility with bool flag for switching order
            if ($pkIndex === true) {
                $pksFixedValues = [1 => $pksFixedValues];
                $pkIndex        = 1;
            } else {
                $pksFixedValues = [0 => $pksFixedValues];
                $pkIndex        = 0;
            }
        }

        return [$pksFixedValues, $pkIndex];
    }//end inputCompatibility()

    /**
     * This enables using an index-based or name; like 0 => val, or user_id => val
     *
     * @param  [type] $pk [description]
     *
     * @return [type]     [description]
     */
    private static function getKey($pk)
    {
        if (!is_numeric($pk) && in_array($pk, static::$primaryKeys)) {
            return $pk;
        }

        return isset(static::$primaryKeys[$pk]) ? static::$primaryKeys[$pk] : null;
    }//end getKey()

    /**
     * Insert into the M2M PK table
     *
     * @param array|value $pkValues This is an array of PK=>Value, ideally
     *                              old style is just a number, for the first PK
     * @param value       $pk2Value This is for old style
     *
     * @return int                  Number of affected rows
     */
    public function add($pkValues, $pk2Value = null)
    {
        if (!is_array($pkValues)) {
            $pkValues = [0 => $pkValues, 1 => $pk2Value];
        }

        $call_args    = $bits = [];
        $call_args[0] = null;
        foreach ($pkValues as $pk => $value) {
            $bits[]      = "`".self::getKey($pk)."` = ?";
            $call_args[] = $value;
        }

        $query = "INSERT INTO `".static::$tableName.'` SET '.implode(", ", $bits);

        $call_args[0] = $query;

        static::db();
        return static::$db->pqueryArray($call_args)->affectedRows();

    }//end add()

    /**
     * Delete from the M2M PK table
     *
     * @param array|value $pkValues This is an array of PK=>Value, ideally
     *                              old style is just a number, for the first PK
     * @param value       $pk2Value This is for old style
     *
     * @return int                  Number of affected rows
     */
    public function delete($pkValues, $pk2Value = null)
    {
        if (!is_array($pkValues)) {
            $pkValues = [0 => $pkValues, 1 => $pk2Value];
        }

        $call_args    = $bits = [];
        $call_args[0] = null;
        foreach ($pkValues as $pk => $value) {
            $bits[]      = "`".self::getKey($pk)."` = ?";
            $call_args[] = $value;
        }

        $query = "DELETE FROM `".static::$tableName.'` WHERE '.implode(" AND ", $bits);

        $call_args[0] = $query;

        static::db();
        return static::$db->pqueryArray($call_args)->affectedRows();
    }//end delete()
}//end class
