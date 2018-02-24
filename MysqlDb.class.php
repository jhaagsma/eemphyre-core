<?php
/**
 * MysqlDb is the db interface for the Emphyre project
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
 * The example website files were written by Julian Haagsma.
 *
 * @category Core
 * @package  EmPHyre
 * @author   Julian Haagsma <jhaagsma@gmail.com>
 * @author   Timo Ewalds <tewalds@gmail.com>
 * @author   Joe Obbish <slagpit@earthempires.com>
 * @license  https://opensource.org/licenses/MIT The MIT License (MIT)
 * @link     https://github.com/jhaagsma/emPHyre
 * @since    First release, September 3, 2012
 */

namespace EmPHyre;

define("DB_ASSOC", MYSQLI_ASSOC);
define("DB_NUM", MYSQLI_NUM);
define("DB_BOTH", MYSQLI_BOTH);

class MysqlDb
{
    private $host;
    private $db;
    private $user;
    private $pass;
    private $persist;
    private $setDb = false;

    private $con       = null;
    private $lasttime  = 0;
    public $queries    = [];
    public $querystore = 150;


    /**
     * The contruct
     * I need to do something *other than* seqtable;
     * It's too crude and non-standard;
     *
     * @param string  $host     Host address
     * @param string  $db       Database name
     * @param string  $user     User name
     * @param string  $pass     Password
     * @param boolean $persist  Whether or not to keep the connection open
     * @param string  $seqtable The table to use for sequencing
     */
    public function __construct(
        $host,
        $db,
        $user,
        $pass,
        $persist = false,
        $seqtable = null
    ) {
        $this->host     = $host;
        $this->db       = $db;
        $this->user     = $user;
        $this->pass     = $pass;
        $this->persist  = $persist;
        $this->seqtable = $seqtable;
        $this->querystore; //number of queries to log in memory
        $this->con = null;
    }//end __construct()


    /**
     * Destructor
     */
    public function __destruct()
    {
        $this->close();
    }//end __destruct()


    /**
     * Test if we can connect to the database, and try to handle it
     *
     * @return bool Whether or not we can connect
     */
    private function canConnect()
    {
        if (!$this->persist) {
            $this->con = new \mysqli($this->host, $this->user, $this->pass);
        } else {
            $this->con = new \mysqli('p:'.$this->host, $this->user, $this->pass);
        }

        if ($this->con->connect_errno) {
            return false;
        }

        return true;
    }//end canConnect()


    /**
     * Actually connect to the database
     *
     * @return bool Always true if we didn't exit
     */
    private function connect()
    {
        if (!$this->setDb) {
            $this->con->select_db($this->db);
            $this->setDb = true;
        }

        if ($this->con) {
            if ($this->lasttime > time() - 10) {
                return $this->con->ping();
            }

            return true;
        }

        if (!$this->canConnect()) {
            $connErr = 'Connect Error ('.$this->con->connect_errno.') ';
            trigger_error($connErr.$this->con->connect_error, E_USER_ERROR);
            $this->con = null;
            exit; //if the redirect doesn't exist??
        }

        return true;
    }//end connect()


    /**
     * Close the connection
     *
     * @return null
     */
    private function close()
    {
        if ($this->con) {
            $this->con->close();
            $this->con = null;
        }
    }//end close()


    /**
     * Deletes a column???
     * I actually have no idea what this does
     *
     * @param  array   $array  the input array?
     * @param  integer $offset Some offset?
     *
     * @return [type]         [description]
     */
    public function deleteCol(&$array, $offset)
    {
        return array_walk(
            $array,
            function (&$v) use ($offset) {
                array_splice($v, $offset, 1);
            }
        );
    }//end deleteCol()


    /**
     * trims files in a column
     *
     * @param  array $files an array of filenames
     *
     * @return [type]        [description]
     */
    public function trimFiles(array $files)
    {
        $list = [];
        foreach ($files as $f) {
            $list[] = explode('/', $f);
        }

        $total = count($list);
        for ($i = 0; $i <= $total; $i++) {
            $column = array_column($list, 0);
            if (count(array_unique($column) === 1)) {
                $this->deleteCol($list, 0);
            }
        }

        foreach ($list as $l => $f) {
            $list[$l] = implode('/', $f);
        }

        return $list;
    }//end trimFiles()


    /**
     * Split out query error stuff to make it much more useful
     *
     * @param  string $query the query being called
     * @param  string $error error text
     *
     * @return exits
     */
    public function queryError(string $query, string $error = null)
    {
        //lets add a bit to tell us where the damned query was called.
        $backtrace = debug_backtrace();
        $stuff     = [];
        while ($trace = next($backtrace)) {
            $a['file']     = isset($trace['file']) ? $trace['file'] : null;
            $a['line']     = isset($trace['line']) ? $trace['line'] : null;
            $a['function'] = explode('\\', $trace['function']);
            $stuff[]       = $a;
        }

        $files = $this->trimFiles(array_column($stuff, 'file'));

        foreach ($stuff as $k => $e) {
            $fn                    = end($e['function']);
            $stuff[$k]['file']     = $files[$k];
            $stuff[$k]['function'] = $fn;
            $stuff[$k]             = implode(':', $stuff[$k]);
            if (in_array($fn, ['query','pquery'])) {
                unset($stuff[$k]);
            }
        }

        $stuff = implode(", ", $stuff);
        $err   = (isset($this->con->errno) ? $this->con->errno.":" : null);
        $err  .= (
            $error ? $error : (isset($this->con->error) ? $this->con->error : 'ERROR')
        );
        if ($this->con->errno == 1064) {
            $err = 'SQL Syntax: '.substr($err, 134); //make it WAY shorter
        }

        $connErr = "QueryErr:".$err.", $stuff";
        //new \dBug($backtrace) && exit;

        trigger_error($connErr." \"$query\"", E_USER_ERROR);
        exit;
    }//end queryError()


    /**
     * Execute the query, return a result
     *
     * @param  String  $query   The SQL query to run
     * @param  boolean $logthis Whether or not to log the query (inactive?)
     *
     * @return MysqlDbResult    The db result object
     */
    public function query($query, $logthis = true)
    {
        $insertid     = 0;
        $affectedRows = 0;
        $numrows      = 0;
        $qt           = 0;

        if (!$this->connect()) {
            return false;
        }

        $start = microtime(true);

        $result = $this->con->query($query);

        if (!$result) {
            $this->queryError($query);
        }

        $insertid     = $this->con->insert_id;
        $affectedRows = $this->con->affected_rows;
        $numrows      = (isset($result->num_rows) ? $result->num_rows : 0);

        $end = microtime(true);

        $this->lasttime = time();

        $qt = ($end - $start);

        if ($logthis) {
            $this->queries[] = [$query, $qt];
        }

        if (count($this->queries) > $this->querystore) {
            array_shift($this->queries);
        }

        return new MysqlDbResult($result, $this->con, $numrows, $affectedRows, $insertid, $qt);
    }//end query()


    /**
     * Prepare the query & arguments
     *
     * @return string An SQL query string
     */
    public function prepare()
    {
        if (!$this->connect()) {
            return false;
        }

        $args = func_get_args();

        if (count($args) == 0) {
            $this->queryError("Wrong number of arguments supplied (No args).");
        }

        if (count($args) == 1) {
            return $args[0];
        }

        $query = array_shift($args);
        $parts = explode('?', $query);
        $query = array_shift($parts);

        if (count($parts) != count($args)) {
            $this->queryError($query, "Wrong number of arguments supplied.");
        }

        $total = count($args);
        for ($i = 0; $i < $total; $i++) {
            $query .= $this->preparePart($args[$i]).$parts[$i];
        }

        return $query;
    }//end prepare()


    /**
     * Prepare a piece of an array
     *
     * @param  mixed $part The part being prepared
     *
     * @return mixed       The part, post-preparation (real_escape_string)
     */
    private function preparePart($part)
    {
        switch (gettype($part)) {
            case 'integer':
                return $part;
            case 'double':
                return $part;
            case 'string':
                if (is_numeric($part)) {
                    return $part;
                }
                return "'".$this->con->real_escape_string($part)."'";
                //What this used to be:
                // mysql_real_escape_string($part, $this->con)
            case 'boolean':
                return ($part ? 1 : 0);
            case 'NULL':
                return 'NULL';
            case 'array':
                $ret = [];
                foreach ($part as $v) {
                    $ret[] = $this->preparePart($v);
                }
                return implode(',', $ret);
            default:
                $this->queryError(gettype($part), "Bad type passed to the database!!");
        }
    }//end preparePart()


    /**
     * Pquery; prepare the query
     *
     * @return MysqlDb Return a MysqlDB object (or false?)
     */
    public function pquery()
    {
        $args  = func_get_args();
        $query = call_user_func_array([$this, 'prepare'], $args);

        return $this->query($query);
    }//end pquery()


    /**
     * Prepare an entire array; args[0] must be the query (with ?)
     * and subesequent arguments are the values for the ?
     *
     * @param  array $args See above
     *
     * @return MysqlDb     The result of the query
     */
    public function pqueryArray($args)
    {
        $query = call_user_func_array([$this, 'prepare'], $args);

        return $this->query($query);
    }//end pqueryArray()


    /**
     * Get a new UUID; mysql is better at this than php...
     *
     * @return string A uuid
     */
    public function newUUID()
    {
        return $this->query("SELECT UNHEX(REPLACE(UUID(),'-',''))")->fetchField();
    }//end newUUID()


    /**
     * Get a new sequence
     *
     * @param  int            $id1   sequence id #1
     * @param  int            $id2   sequence id #2
     * @param  int            $area  area id
     * @param  string|boolean $table The table to use;
     * @param  string|boolean $start The starting index;
     *
     * @return [type]         [description]
     */
    public function getSeqID($id1, $id2, $area, $table = false, $start = false)
    {
        if (!$table) {
            $table = $this->seqtable;
            if (!$table) {
                return false;
            }
        }

        $inid = $this->pquery(
            "UPDATE ".$table." SET max = LAST_INSERT_ID(max+1)
            WHERE id1 = ? &&
            id2 = ? &&
            area = ?",
            $id1,
            $id2,
            $area
        )->insertid();

        if ($inid) {
            return $inid;
        }

        if (!$start) {
            $start = 1;
        }

        $ignore = $this->pquery(
            "INSERT IGNORE INTO ".$table."
            SET max = ?, id1 = ?, id2 = ?, area = ?",
            $start,
            $id1,
            $id2,
            $area
        );

        if ($ignore->affectedRows()) {
            return $start;
        } else {
            return $this->getSeqID($id1, $id2, $area, $table, $start);
        }
    }//end getSeqID()


    /**
     * Create a database if it doesn't exist; perhaps this shoudl be renamed
     *
     * @param  String $database Database Name
     *
     * @return null
     */
    public function createIfNotExists($database = null)
    {
        $this->setDb = true; //fake like we've set the db
        if (!$this->checkDbExists($database)) {
            $this->createDb($database);
        }

        $this->setDb = false; //un-fake
    }//end createIfNotExists()


    /**
     * Check if a database exists
     *
     * @param  string $database Database Name
     *
     * @return boolean          Whether or the db exists
     */
    public function checkDbExists($database = null)
    {
        $database = $database ? $database : $this->db;
        $exists   = $this->pquery('SHOW DATABASES LIKE ?', $database)->fetchField();
        $exists ? null : self::out("DATABASE DOEST NOT EXIST: ".$database);

        return $exists ? true : false;
    }//end checkDbExists()


    /**
     * Create a database (if not exists)
     *
     * @param  String $database Database Name
     *
     * @return null
     */
    public function createDb($database = null)
    {
        $database = $database ? $database : $this->db;

        if ($database != preg_replace("/[^a-z0-9_]/i", null, strtolower($database))) {
            return self::out("INVALID DATABASE NAME: $database");
        }

        $this->pquery("CREATE DATABASE IF NOT EXISTS $database");
        self::out("Created Database: ".$database);
    }//end createDb()

    /**
     * Check if a table exists
     *
     * @param  String $tableName The table name
     *
     * @return boolean           Whether or not the table exists
     */
    public function tableExists($tableName)
    {
        $exists = $this->pquery("SHOW TABLES LIKE ?", $tableName)->fetchField();
        return $exists ? true : false;
    }//end tableExists()


    /**
     * Check if a column exists in a table
     *
     * @param  string $columnName The column name
     * @param  string $tableName  The table name
     *
     * @return boolean            Whether or not the column exists
     */
    public function columnExists($columnName, $tableName)
    {
        //sanitize, just in case
        $tableName = $this->con->real_escape_string($tableName);
        $exists    = $this->pquery(
            "SHOW COLUMNS FROM `$tableName` LIKE ?",
            $columnName
        )->fetchField();

        return $exists ? true : false;
    }//end columnExists()


    /**
     * The function to output errors to the log file
     *
     * @param  string $string The message to log/error
     *
     * @return null
     */
    protected static function out($string)
    {
        trigger_error($string, E_USER_NOTICE);
    }//end out()
}//end class
