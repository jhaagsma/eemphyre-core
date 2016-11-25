<?php
/**
 * MysqlDbResult is the result object for the MysqlDb class
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
 * @since    Pulled out of MysqlDb.class.php 2016-03-15
 */

namespace EmPHyre;

class MysqlDbResult
{
    public $dbcon;
    public $result;
    public $numrows;
    public $affectedRows;
    public $insertid;
    public $querytime;

    public function __construct($result, $dbcon, $numrows, $affectedRows, $insertid, $qt)
    {
        $this->dbcon        = $dbcon;
        $this->result       = $result;
        $this->numrows      = $numrows;
        $this->affectedRows = $affectedRows;
        $this->insertid     = $insertid;
        $this->querytime    = $qt;
    }//end __construct()


    public function __destruct()
    {
        $this->free();
    }//end __destruct()


    //one row at a time
    public function fetchRow($type = DB_ASSOC)
    {
        return $this->result->fetch_array($type);
    }//end fetchRow()


    //for queries with a single column in a single row
    public function fetchField()
    {
        $ret = $this->fetchRow(DB_NUM);
        return $ret[0];
    }//end fetchField()


    //return the full set
    public function fetchFieldSet()
    {
        $ret = array();

        while ($line = $this->fetchRow(DB_NUM)) {
            if (count($line) == 1) {
                $ret[] = $line[0];
            } else {
                $ret[$line[0]] = $line[1];
            }
        }

        return $ret;
    }//end fetchFieldSet()


    //return the full set
    public function fetchRowSet($col = null, $type = DB_ASSOC)
    {
        $ret = array();

        while ($line = $this->fetchRow($type)) {
            if ($col) {
                $ret[$line[$col]] = $line;
            } else {
                $ret[] = $line;
            }
        }

        return $ret;
    }//end fetchRowSet()


    public function affectedRows()
    {
        return $this->affectedRows;
    }//end affectedRows()


    public function insertid()
    {
        return $this->insertid;
    }//end insertid()


    public function rows()
    {
        return $this->numrows;
    }//end rows()


    public function free()
    {
        if (!is_object($this->result)) {
            return false;
        }

        return $this->result->free();
    }//end free()
}//end class
