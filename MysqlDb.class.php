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
    public $host;
    public $db;
    public $user;
    public $pass;
    public $persist;

    public $con;
    public $lasttime;
    public $queries;
    public $querystore;

    public function __construct(
        $host,
        $db,
        $user,
        $pass,
        $persist = false,
        $seqtable = null,
        $logqueries = false,
        $qlog_table = 'queries'
    ) {
        //logqueries should really be false by default

        $this->host = $host;
        $this->db = $db;
        $this->user = $user;
        $this->pass = $pass;
        $this->persist = $persist;
        $this->seqtable = $seqtable;
        $this->logqueries = $logqueries;
        $this->plogged = false;
        $this->preparedq = false;
        $this->querystore = 150;
        $this->con = null;
        $this->lasttime = 0;
        $this->qlog = null;
        $this->qlog_table = $qlog_table;

        $this->queries = array();
        $this->count = 0;
        $this->querytime = 0;
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

    /*
    function log_em($query,$qtime){
        if($this->logqueries && !$this->plogged){
            //SQRT(sq_total_time/total_num - total_time*total_time/(total_num*total_num))',
            $this->plogged = true;
            if($this->preparedq != false)
                $query = $this->preparedq;

            $this->qlog = 'INSERT INTO `' . $this->qlog_table . '`... ' . $query;
            $time = time();

            //We should check this function for accuracy again;
            //I never properly checked it methinks
            $this->pquery('INSERT INTO `' . $this->qlog_table .
                '` (hash, strlen, last_time, total_num, total_time,
                min_time, max_time,avg_time,new_mean,new_s,new_stdev,query,last_page)
                VALUES (?,?,?,1,?,?,?,?,?,0,0,?,?) ON DUPLICATE KEY UPDATE
                last_time = ?, last_page = ?, total_num = total_num + 1,
                total_time = total_time + ?, min_time = if(? < min_time,?,min_time),
                max_time = if(max_time < ?, ?, max_time), avg_time = total_time/total_num,
                new_s = new_s + (? - new_mean) * (? - new_mean + (? - new_mean) / total_num),
                new_mean = new_mean + (? - new_mean) / total_num, new_stdev = SQRT(new_s / (total_num - 1))',
                md5($query), strlen($query), $time, $qtime,$qtime, $qtime, $qtime, $qtime,
                $query, (isset($_SERVER) ? $_SERVER['PHP_SELF'] : 'bot'),
                $time, (isset($_SERVER) ? $_SERVER['PHP_SELF'] : 'bot'), $qtime, $qtime, $qtime,
                $qtime, $qtime, $qtime, $qtime, $qtime, $qtime);
            //Donald Knuth's "The Art of Computer Programming, Volume 2: Seminumerical Algorithms", section 4.2.2.

            $this->plogged = false;
            $this->preparedq = false;
        }
        return;
    }
    */

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
            //lets add a bit to tell us where the damned query was called.
            $backtrace = debug_backtrace();
            $pq = ($backtrace[1]['function'] == 'pquery' ? 1 : 0);
            $line = $backtrace[$pq]['line'];
            $file_err = explode('/', $backtrace[$pq]['file']);
            $dir_this = explode('/', __DIR__);
            $i = 0;
            while (isset($file_err[$i]) && isset($dir_this[$i]) && $file_err[$i] == $dir_this[$i]) {
                unset($file_err[$i]);
                unset($file_err[$i]);
                $i++;
            }
            $file_err = implode('/', $file_err);

            $trace = null;
            foreach ($backtrace as $item) {
                $f = explode('/', $item['file']);
                $trace .= $item['function'] . '()/' . end($f) . ':' . $item['line'] . ' ';
            }

            $connErr = "Query Error:  (" . $this->con->errno . "), $file_err:$line $trace";
            //new \dBug($backtrace) && exit;

            trigger_error($connErr . $this->con->error . " : \"$query\"", E_USER_ERROR);
            exit;
        }

        $insertid = $this->con->insert_id;
        $affectedRows = $this->con->affected_rows;
        $numrows = (isset($result->num_rows) ? $result->num_rows : 0);

        $end = microtime(true);

        $this->lasttime = time();

        $this->count++;
        $qt = $this->querytime = ($end - $start);

        if ($logthis) {
            if ($this->plogged) {
                $this->queries[] = array($this->qlog, $this->querytime);
            } else {
                $this->queries[] = array($query, $this->querytime);
            }
        }
        if (count($this->queries) > $this->querystore) {
            array_shift($this->queries);
        }

        /*
        global $debug;
        if(isset($debug) && $debug)
            $this->debug_query($query);
            //this returns a little table that does the EXPLAIN of a non-EXPLAIN query in the query list
        */

        /*if($this->logqueries && substr($query, 0, 7) != "EXPLAIN")
            $this->log_em($query,$qt);
        */
        return new MysqlDbResult($result, $this->con, $numrows, $affectedRows, $insertid, $qt);
    }

    public function prepare()
    {
        if (!$this->connect()) {
            return false;
        }

        $args = func_get_args();

        if (count($args) == 0) {
            trigger_error("mysql: Bad number of args (No args)", E_USER_ERROR) && exit;
        }

        if (count($args) == 1) {
            return $args[0];
        }

        $query = array_shift($args);
        $parts = explode('?', $query);
        $query = array_shift($parts);

        if (count($parts) != count($args)) {
            trigger_error("Wrong number of args to prepare for $query", E_USER_ERROR) && exit;
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
                $backtrace = debug_backtrace();
                foreach ($backtrace as $i => $stuff) {
                    if ($stuff['function'] == 'pquery') {
                        $line = $stuff['line'];
                        $file_err = $stuff['file'];
                    }
                }

                $Err = "Bad type passed to the database!! Type: " . gettype($part) . ", $file_err:$line ";
                trigger_error($Err);
                exit;
        }
    }

    public function pquery()
    {
        $args = func_get_args();
        $this->preparedq = $args[0];
        $query = call_user_func_array(array($this, 'prepare'), $args);

        return $this->query($query);
    }

    public function pqueryArray($args)
    {
        $this->preparedq = $args[0];
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
    /*
    function debug_query($query){
        if(substr($query, 0, 7) != "EXPLAIN" && substr($query, 0, 6) == "SELECT"){
            $explain = $this->query('EXPLAIN ' . $query,false)->fetchRow();
            $text = 'EXPLAIN ' . "<br />\n<table><tr>";
            foreach($explain as $name => $var)
                $text .= '<td>' . $name . '</td>';
            $text .= '</tr><tr>';
            foreach($explain as $var)
                $text .= '<td>' . $var . '</td>';
            $text .= '</tr></table>';
            $this->queries[] = array($text, $this->querytime);
        }
        return;
    }
    */
}
