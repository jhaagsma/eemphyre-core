<?php
/*---------------------------------------------------
These files are part of the empiresPHPframework;
The original framework core (specifically the mysql.php
the router.php and the errorlog) was started by Timo Ewalds,
and rewritten to use APC and extended by Julian Haagsma,
for use in Earth Empires (located at http://www.earthempires.com );
it was spun out for use on other projects.

The general.php contains content from Earth Empires
written by Dave McVittie and Joe Obbish.


The example website files were written by Julian Haagsma.

All files are licensed under the MIT License.

First release, September 3, 2012
---------------------------------------------------*/

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

    private $con = null;
    private $lasttime = 0;
    public $queries = [];
    public $querystore = 150;

    public function __construct(
        $host,
        $db,
        $user,
        $pass,
        $persist = false,
        $seqtable = null
    ) {
        $this->host = $host;
        $this->db = $db;
        $this->user = $user;
        $this->pass = $pass;
        $this->persist = $persist;
        $this->seqtable = $seqtable;
        $this->querystore; //number of queries to log in memory
        $this->con = null;
    }

    public function __destruct()
    {
        $this->close();
    }

    private function canConnect()
    {
        if (!$this->persist) {
            $this->con = new \mysqli($this->host, $this->user, $this->pass, $this->db);
        } else {
            $this->con = new \mysqli('p:' . $this->host, $this->user, $this->pass, $this->db);
        }

        if ($this->con->connect_errno) {
            return false;
        }

        return true;
    }

    private function connect()
    {
        if ($this->con) {
            if ($this->lasttime > time()-10) {
                return $this->con->ping();
            }

            return true;
        }

        if (!$this->canConnect()) {
            $connErr = 'Connect Error (' . $this->con->connect_errno . ') ';
            trigger_error($connErr . $this->con->connect_error, E_USER_ERROR);
            $this->con = null;
            exit; //if the redirect doesn't exist??
        }

        return true;
    }

    private function close()
    {
        if ($this->con) {
            $this->con->close();
            $this->con = null;
        }
    }

    public function deleteCol(&$array, $offset)
    {
        return array_walk($array, function (&$v) use ($offset) {
            array_splice($v, $offset, 1);
        });
    }

    /**
     * trims files in a column
     *
     * @param  array  $files an array of filenames
     *
     * @return [type]        [description]
     */
    public function trimFiles(array $files)
    {
        $list = [];
        foreach ($files as $f) {
            $list[] = explode('/', $f);
        }

        for ($i = 0; $i <= count($list); $i++) {
            $column = array_column($list, 0);
            if (count(array_unique($column) === 1)) {
                $this->deleteCol($list, 0);
            }
        }

        foreach ($list as $l => $f) {
            $list[ $l] = implode('/', $f);
        }

        return $list;
    }

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
        $stuff = [];
        while ($trace = next($backtrace)) {
            $a['file'] = isset($trace['file']) ? $trace['file'] : null;
            $a['line'] = isset($trace['line']) ? $trace['line'] : null;
            $a['function'] = explode('\\', $trace['function']);
            $stuff[] = $a;
        }

        $files = $this->trimFiles(array_column($stuff, 'file'));

        foreach ($stuff as $k => $e) {
            $fn = end($e['function']);
            $stuff[$k]['file'] = $files[$k];
            $stuff[$k]['function'] = $fn;
            $stuff[$k] = implode(':', $stuff[$k]);
            if (in_array($fn, ['query','pquery'])) {
                unset($stuff[$k]);
            }
        }

        $stuff = implode(", ", $stuff);
        $err = (isset($this->con->errno) ? $this->con->errno.":" : null);
        $err .= (
            $error ?
            $error :
            (isset($this->con->error) ? $this->con->error : 'ERROR')
        );
        if ($this->con->errno == 1064) {
            $err = 'SQL Syntax: '.substr($err, 134); //make it WAY shorter
        }

        $connErr = "QueryErr:".$err.", $stuff";
        //new \dBug($backtrace) && exit;

        trigger_error($connErr." \"$query\"", E_USER_ERROR);
        exit;
    }

    public function query($query, $logthis = true)
    {
        $insertid = 0;
        $affectedRows = 0;
        $numrows = 0;
        $qt = 0;

        if (!$this->connect()) {
            return false;
        }

        $start = microtime(true);

        $result = $this->con->query($query);

        if (!$result) {
            $this->queryError($query);
        }

        $insertid = $this->con->insert_id;
        $affectedRows = $this->con->affected_rows;
        $numrows = (isset($result->num_rows) ? $result->num_rows : 0);

        $end = microtime(true);

        $this->lasttime = time();

        $qt = ($end - $start);

        if ($logthis) {
            $this->queries[] = array($query, $qt);
        }
        if (count($this->queries) > $this->querystore) {
            array_shift($this->queries);
        }

        return new MysqlDbResult($result, $this->con, $numrows, $affectedRows, $insertid, $qt);
    }

    public function prepare()
    {
        if (!$this->connect()) {
            return false;
        }

        $args = func_get_args();

        if (count($args) == 0) {
            $this->queryError($query, "Wrong number of arguments supplied (No args).");
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

        for ($i = 0; $i < count($args); $i++) {
            $query .= $this->preparePart($args[$i]) . $parts[$i];
        }

        return $query;
    }

    private function prepareArray($query, $array)
    {
        return call_user_func_array($query, $array);
    }

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
                return "'" . $this->con->real_escape_string($part) . "'"; // mysql_real_escape_string($part, $this->con)
            case 'boolean':
                return ($part ? 1 : 0);
            case 'NULL':
                return 'NULL';
            case 'array':
                $ret = array();
                foreach ($part as $v) {
                    $ret[] = $this->preparePart($v);
                }
                return implode(',', $ret);
            default:
                $this->queryError(gettype($part), "Bad type passed to the database!!");
        }
    }

    public function pquery()
    {
        $args = func_get_args();
        $query = call_user_func_array(array($this, 'prepare'), $args);

        return $this->query($query);
    }

    public function pqueryArray($args)
    {
        $query = call_user_func_array(array($this, 'prepare'), $args);

        return $this->query($query);
    }

    public function newUUID()
    {
        return $this->query("SELECT UNHEX(REPLACE(UUID(),'-',''))")->fetchField();
    }

    public function getSeqID($id1, $id2, $area, $table = false, $start = false)
    {
        if (!$table) {
            $table = $this->seqtable;
            if (!$table) {
                return false;
            }
        }
        $inid = $this->pquery(
            "UPDATE " . $table . " SET max = LAST_INSERT_ID(max+1)
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
            "INSERT IGNORE INTO " . $table . "
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
    }
}
