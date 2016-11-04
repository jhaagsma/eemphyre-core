<?php
/**
 * MigrationManager for PhaseWeb project
 *
 * PHP version 5
 *
 * ------
 * These files are part of the PhaseWeb project;
 * This project uses the EmPHyre microframework,
 * and is built off the EmPHyre example files
 *
 * Written for PhaseSensors Julian Haagsma.
 *
 * @category Classes
 * @package  PhaseWeb
 * @author   Julian Haagsma <jhaagsma@gmail.com>
 * @license  All files are licensed under the MIT License.
 * @link     https://demo3.phasesensors.com
 * @since    PhaseWeb was created 2014-09, modernized to current in 2016-03
 */

namespace EmPHyre;

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
        self::buildChain();

        if (self::$maxregistered != self::dotName()) {
            self::doUpgrades();
        }
    }

    abstract protected static function buildChain();

    protected static function dotName($maj = null, $min = null, $rel = null, $build = null)
    {
        if ($maj === null) {
            $maj = self::$major ? self::$major : 0;
            $min = self::$minor ? self::$minor : 0;
            $rel = self::$release ? self::$release : 0;
            $build = self::$build ? self::$build : null;
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
                CREATE TABLE `version` (
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
            )->fetchField();

            self::$major = $version['major'];
            self::$minor = $version['minor'];
            self::$release = $version['rel'];
            self::$build = $version['build'];
        }
    }

    protected static function register($dotName, $class)
    {
        self::$maxregisetered = $dotName;
        self::$upgradeChain[] = ['name'=>$dotName, 'class'=>$class];
    }

    protected static function doUpgrades()
    {
        foreach (self::$upgradeChain as $upgrade) {
            $name = $upgrade['name'];
            $class = $upgrade['class'];

            $migration = new $class($name);
            $worked = $migration->up();

            if (!$worked) {
                $migration->out("UPGRADE FAILED");
                return; //in case migrations are dependent, return here
            }

            $dotNameParts = self::explodeDotName($name);

            $version_id = self::$db->pquery(
                "INSERT INTO `version` (`major`,`minor`,`rel`,`build`
                VALUES (?,?,?,?)",
                $dotNameParts['major'],
                $dotNameParts['minor'],
                $dotNameParts['release'],
                $dotNameParts['build']
            )->insertid();
        }
    }
}
