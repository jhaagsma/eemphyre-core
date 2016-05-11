<?php
/**
 * MysqlDbQueryBuilder is a query builder for mysql
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
 * @license  All files are licensed under the MIT License.
 * @link     https://github.com/jhaagsma/emPHyre
 * @since    May 2016
 */

namespace EmPHyre;

class MysqlDbQueryBuilder
{
    public $query;
    private static $db;

    public function __construct($db = null)
    {
        $this->setDb($db);
    }

    public function run()
    {
        if ($this->query) {
            self::$db->query($this->query);
        }
    }

    public function setDb($db = null)
    {
        if ($db == null) {
            static::$db = Container::getDb();
        }
        static::$db = $db;
    }
}
