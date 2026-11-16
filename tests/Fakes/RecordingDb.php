<?php
/**
 * Stand-in for \EmPHyre\MysqlDb in tests: records every query and answers from canned rows.
 * Tests must never touch a live database (CLAUDE.md).
 */
final class RecordingDb
{
    /** @var array[] each [sql, args...] */
    public $queries = [];
    /** @var array[] SQL fragment => rows returned by any query containing it (first match wins) */
    public $answers = [];

    /** Container::getDb() calls this on master (#7) */
    public function createIfNotExists()
    {
    }

    public function pquery($sql, ...$args)
    {
        $this->queries[] = array_merge([$sql], $args);
        foreach ($this->answers as $fragment => $rows) {
            if (stripos($sql, $fragment) !== false) {
                return new RecordingResult($rows);
            }
        }
        return new RecordingResult([]);
    }

    public function pqueryArray($args)
    {
        return $this->pquery(...$args);
    }

    /** Put a class's static $db back to null (setDb(null) would ask Container for a real one). */
    public static function detach($class)
    {
        $prop = new \ReflectionProperty($class, 'db');
        $prop->setAccessible(true);
        $prop->setValue(null, null);
    }

    /** the SQL of the last query, or null */
    public function lastSql()
    {
        return $this->queries ? end($this->queries)[0] : null;
    }
}

final class RecordingResult
{
    private $rows;

    public function __construct(array $rows)
    {
        $this->rows = $rows;
    }

    public function fetchRow()
    {
        return $this->rows ? $this->rows[0] : null;
    }

    public function fetchField()
    {
        return $this->rows ? reset($this->rows[0]) : null;
    }

    public function fetchFieldSet()
    {
        return array_map(function ($row) {
            return reset($row);
        }, $this->rows);
    }

    public function fetchRowSet()
    {
        return $this->rows;
    }

    public function affectedRows()
    {
        return 1;
    }

    public function insertid()
    {
        return 1;
    }
}
