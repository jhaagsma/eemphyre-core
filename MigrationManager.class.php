<?php namespace EmPHyre;

/**
 * ABSTRACT CLASS, Migration Manager object for the EmPHyre project
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
 * @since    Recombined from empiresPHPframework extensions on Nov 4 2016
 */

abstract class MigrationManager
{
    protected static $db;
    protected static $major;
    protected static $minor;
    protected static $release;
    protected static $build;

    protected static $maxregistered;
    protected static $upgradeChain = [];


    public static function setDb($db = null)
    {
        if ($db == null) {
            self::$db = Container::getDb();
        } else {
            self::$db = $db;
        }
    }

    public static function goToLatest()
    {
        self::setDb();
        self::checkVersion();
        static::buildChain();

        if (self::$maxregistered != self::dotName()) {
            self::doUpgrades();
        }
    }

    abstract protected static function buildChain();

    protected static function dotName($maj = null, $min = null, $rel = null, $build = null)
    {
        if ($maj === null) {
            $maj = static::$major ? static::$major : 0;
            $min = static::$minor ? static::$minor : 0;
            $rel = static::$release ? static::$release : 0;
            $build = static::$build ? static::$build : null;
        }

        if ($build !== null) {
            return "$maj.$min.$rel-$build";
        } elseif ($rel !== 0) {
            return "$maj.$min.$rel";
        } else {
            return "$maj.$min";
        }
    }

    protected static function explodeDotName($dotName)
    {
        $partA = explode('-', $dotName, 2);
        $build = isset($partA[1]) ? $partA[1] : null;
        $partB =  explode('.', $partA[0], 3);
        return [
            'major' => $partB[0],
            'minor' => $partB[1],
            'release' => isset($partB[2]) ? $partB[2] : 0,
            'build' => $build
        ];
    }

    protected static function checkVersion()
    {
        if (!self::$db->tableExists('version')) {
            self::$major = self::$minor = self::$release = 0;
            self::$build = null;

            self::$db->pquery(
                "
                CREATE TABLE IF NOT EXISTS `version` (
                 `version_id` SMALLINT NOT NULL AUTO_INCREMENT ,
                 `major` TINYINT NOT NULL DEFAULT '0' ,
                 `minor` TINYINT NOT NULL DEFAULT '0' ,
                 `rel` TINYINT NOT NULL DEFAULT '0' ,
                 `build` VARCHAR(8) NULL DEFAULT 'null' ,
                 PRIMARY KEY (`version_id`)) ENGINE = InnoDB;"
            );

            self::$db->pquery(
                "INSERT INTO `version` (`major`,`minor`,`rel`,`build`)
                VALUES (0,0,0,null)"
            );
        } else {
            $version = self::$db->pquery(
                "SELECT `major`, `minor`, `rel`, `build` FROM `version`
                ORDER BY `major` DESC, `minor` DESC, `rel` DESC, `build` DESC
                LIMIT 1"
            )->fetchRow();

            static::$major = $version['major'];
            static::$minor = $version['minor'];
            static::$release = $version['rel'];
            static::$build = $version['build'];
        }
    }

    protected static function register($dotName, $class)
    {
        static::$maxregistered = $dotName;
        static::$upgradeChain[] = ['name'=>$dotName, 'class'=>$class];
    }

    protected static function doUpgrades()
    {
        foreach (self::$upgradeChain as $upgrade) {
            $name = $upgrade['name'];
            $class = $upgrade['class'];

            $dotName = self::explodeDotName($name);
            $maj = $dotName['major'];
            $min = $dotName['minor'];
            $rel = $dotName['release'];
            $bui = $dotName['build'];

            $upgrade = false;
            if ($maj > static::$major) {
                $upgrade = true;
            } elseif ($maj < static::$major) {
                continue;
            } elseif ($min > static::$minor) {
                $upgrade = true;
            } elseif ($maj < static::$major) {
                continue;
            } elseif ($rel != 0 && $rel > static::$release) {
                $upgrade = true;
            } elseif ($rel < static::$release) {
                continue;
            } elseif ($bui !== null && $bui > static::$build) {
                $upgrade = true;
            } elseif ($bui < static::$build) {
                continue;
            }

            if (!$upgrade) {
                continue;
            }

            $migration = new $class($name);
            $worked = $migration->up();

            if (!$worked) {
                $migration->out("UPGRADE FAILED");
                return; //in case migrations are dependent, return here
            }

            $version_id = self::$db->pquery(
                "INSERT INTO `version` (`major`, `minor`, `rel`, `build`)
                VALUES (?, ?, ?, ?)",
                $dotName['major'],
                $dotName['minor'],
                $dotName['release'],
                $dotName['build']
            )->insertid();

            self::out("UPGRADED TO $name");
        }
    }

    public static function out($string)
    {
        trigger_error($string, E_USER_NOTICE);
    }
}
