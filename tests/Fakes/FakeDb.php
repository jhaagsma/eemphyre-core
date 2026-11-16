<?php
/**
 * Stand-in for \EmPHyre\MysqlDb in tests: records pquery() calls and answers SELECTs from
 * active_sessions with an in-memory table. Tests must never touch a live database (CLAUDE.md).
 */
final class FakeDb
{
    /** @var array[] each [sql, args...] */
    public $queries = [];
    /** @var array[] rows of active_sessions: login_id, user_id, cookieval, expiretime */
    public $sessions = [];

    public function createIfNotExists()
    {
        return true;
    }

    public function pquery($sql, ...$args)
    {
        $this->queries[] = array_merge([$sql], $args);
        $rows = [];
        if (stripos($sql, 'SELECT login_id, cookieval, expiretime FROM active_sessions') === 0) {
            foreach ($this->sessions as $r) {
                // MySQL with a case-insensitive collation: compare like it would
                if ($r['user_id'] == $args[0] && strcasecmp($r['cookieval'], $args[1]) === 0) {
                    $rows[] = $r;
                }
            }
        }
        return new FakeResult($rows);
    }

    /** the DELETE statements run, as [sql, args...] */
    public function deletes()
    {
        return array_values(array_filter($this->queries, function ($q) {
            return stripos($q[0], 'DELETE') === 0;
        }));
    }
}

final class FakeResult
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

    public function fetchRowSet()
    {
        return $this->rows;
    }

    public function affectedRows()
    {
        return 1;
    }
}
