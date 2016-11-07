<?php namespace EmPhyre;

/**
 * User is the user class for the EmPHyre Framework Example
 *
 * PHP version 7
 *
 * ------
 * This files are part of the empiresPHPframework;
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
 * @category ExampleFiles
 * @package  EmPHyre
 * @author   Julian Haagsma <jhaagsma@gmail.com>
 * @author   Timo Ewalds <tewalds@gmail.com>
 * @author   Dave McVittie <dave.mcvittie@gmail.com>
 * @author   Joe Obbish <slagpit@earthempires.com>
 * @license  All files are licensed under the MIT License.
 * @link     https://github.com/jhaagsma/emPHyre
 * @since    Pulled out of MysqlDb.class.php 2016-03-15
 */

//should we define these in the Container, or in the Cache object, perhaps...?
define('APC_USER_PREPEND', 'max-ul-');

class User
{
    public $userid;
    private static $db;
    private static $apcu_userline;

    public function __construct($userid)
    {
        $this->userid = $userid;

        //this is a hack for when the userid isn't defined
        //honestly that shouldn't ever come up, but it did (error in the log manager)
        //so better to handle it than not?
        $this->user_name = null;
        $this->uuid = null;
    }

    public function setDb($db)
    {
        self::$db = $db;
    }

    public function initialize($clearcache = false)
    {
        self::$apcu_userline = APC_USER_PREPEND . $this->userid;
        if ($clearcache) {
            $this->apcDelUserline();
        }

        $this->getValues();
    }

    public static function getUseridFromName($username = null)
    {
        self::$db = Container::getDb();
        return self::$db->pquery(
            "SELECT user_id FROM users WHERE user_name = ?",
            $username
        )->fetchField();
    }

    public static function getUseridFromUUID($uuid = null)
    {
        $uuid = URL::decode64($uuid);
        self::$db = Container::getDb();
        return self::$db->pquery(
            "SELECT user_id FROM users WHERE uuid = ?",
            $uuid
        )->fetchField();
    }

    public static function users($group_id = null)
    {
        self::$db = Container::getDb();
        if (!$group_id) {
            //normal query
            return self::$db->pquery(
                "SELECT user_id FROM users WHERE NOT disabled ORDER BY user_name ASC"
            )->fetchFieldSet();
        }

        //query A, then "join" with users to check disabled
        $users = self::$db->pquery(
            "SELECT user_id FROM user_groups WHERE group_id = ?",
            $group_id
        )->fetchFieldSet();

        return self::$db->pquery(
            "SELECT user_id FROM users WHERE user_id IN(?) AND NOT disabled ORDER BY user_name ASC",
            $users
        )->fetchFieldSet();
    }

    public static function addUser($username, $pw1, $pw2, $client_id = -1)
    {
        if ($error = Validate::email($username)) {
            return $error;
        }

        self::$db = Container::getDb();

        $result = self::checkExists($username);
        if ($result->isError()) {
            return $result;
        }

        //need to change good_password with whatever password rules we want;
        if ($error = Validate::password($pw1, $pw2)) {
            return $error;
        }

        //perhaps this should all be rolled into the user class somehow?
        //OR INTO A PASSWORD CLASS!!!!!

        //user the change password function?
        //$salt = Password::generateSalt();
        //$password = Password::cryptSHA512($pw1, $salt);

        $user_id = self::$db->pquery(
            "INSERT INTO users SET uuid = ?, user_name = ?",
            self::newUUID(),
            $username
        )->insertid();

        if (!$user_id) {
            return new Result("FAIL_INSERT");
        }

        $newuser = Container::newUser($user_id);
        if ($error = $newuser->changePassword($pw1, $pw2)) {
            return $error;
        }

        return new Result('ADDED_USER', $user_id, true);
    }

    private static function newUUID()
    {
        $uuid = self::$db->newUUID();
        while (self::checkUUIDCollision($uuid)) {
            $uuid = self::$db->newUUID();
        }
        return $uuid;
    }

    public static function checkUUIDCollision($uuid = null)
    {
        $check = self::$db->pquery(
            "SELECT uuid FROM users WHERE uuid = ?",
            $uuid
        )->fetchField();

        return $check || $uuid === null ? true : false;
    }

    public function isDisabled()
    {
        return $this->disabled ? true : false;
    }

    public static function checkExists($username = null)
    {
        if ($username === null) {
            return new Result('INVALID_INPUT', $username);
        }

        //reuse functions
        $user_id = self::getUseridFromName($username);
        if ($user_id) {
            return new Result('EXISTS_USERNAME', $username);
        }

        //this is a success because this function is used for finding collisions
        return new Result('NOEXIST_USER', $username, true);
    }

    public function edit($username, $pw1, $pw2, $client_id)
    {
        $changed = false;
        if ($username != $this->user_name) {
            if ($error = Validate::email($username)) {
                return $error;
            }

            if ($error = self::checkExists($username)) {
                return $error;
            }
        }

        if ($pw1) {
            if ($error = $this->changePassword($pw1, $pw2)) {
                return $error;
            }
            $changed = true;
        }

        if ($username != $this->user_name) {
            $this->user_name = $username;

            $result = $this->commit();
            if ($result->isError()) {
                return $result;
            }

            $changed = true;
        }

        $result = $this->editClientid($client_id);

        if ($result->success) {
            $changed = true;
        }

        if (!$changed) {
            return new Result('UNCHANGED_USER', $this->getId(), false, false);
        }

        return new Result('EDITED_USER', $this->getId(), true);
    }

    public function disableUser()
    {
        $this->disabled = true;
        $result = $this->commit();
        if ($result->isError()) {
            return $result;
        }

        return new Result('DISABLED_USER', $this->getId(), true);
    }

    public function checkPassword($password)
    {
        return \EmPHyre\Password::cryptSHA512($password, $this->salt) == $this->password ? true : false;
    }

    public function loggedIn()
    {
        $ip = ip2long($_SERVER['REMOTE_ADDR']);
        self::$db->pquery("INSERT INTO user_logins SET user_id = ?, time = ?, ipv4 = ?", $this->userid, time(), $ip);
        return;
    }

    private function getValues()
    {
        $this->ul = $this->apcUserline();
        if (!$this->ul) {
            $this->userid = null;
            return;
        }
        //we don't want anything to be able to change their userid, that would be seriously messed up
        unset($this->ul['user_id']);
        array_to_obj_vals($this, $this->ul);
    }

    private function refreshValues()
    {
        $this->apcDelUserline();
        $this->getValues();
    }

    public function commit()
    {
        //we want don't anything to be able to change their userid, that would be seriously messed up
        unset($this->ul['user_id']);

        //i'm pretty sure we don't ever want to change the uuid ??
        unset($this->ul['uuid']);

        $partcount = 0;
        $partsA = $partsB = array();
        foreach ($this->ul as $k => $v) {
            if ($v != $this->$k) {
                $partsA[] = "$k = ?";
                $partsB[] = $this->$k;
                $partcount++;
            }
        }
        //check if there are things to update
        if (count($partsA)==0) {
            //neutral result;
            return new Result("UNCHANGED_USER", $this->userid, false, false);
        }

        $query = "UPDATE users SET " . implode(", ", $partsA) . " WHERE user_id = ?";
        $call_args = array();
        $call_args[] = $query;
        $checkcount = 0;
        foreach ($partsB as $p) {
            $call_args[] = $p;
            $checkcount++;
        }
        $call_args[] = $this->userid;

        if ($partcount != $checkcount) {
         //this should never happen, but we should check regardless
            return new Result("FAIL_PARTCOUNT");
        }

        $row = self::$db->pqueryArray($call_args);

        if ($row->affectedRows()) {
            $this->refreshValues($this->userid);
            return new Result("CHANGED_USER", $this->userid, true);
        } else {
            return new Result("UNCHANGED_USER", $this->userid, false, false);
        }
    }

    private function apcUserline($column = null)
    {
        //deliberately errors if column isn't in the array
        $user_line = \EmPHyre\Cache::Fetch(self::$apcu_userline);
        if (!$user_line) {
             //this ideally shouldn't select *, but for now we'll do it for convenience
            $user_line = self::$db->pquery('SELECT * FROM users WHERE user_id = ?', $this->userid)->fetchRow();
            \EmPHyre\Cache::Store(self::$apcu_userline, $user_line, 1800);
        }
        return ($column ? $user_line[$column] : $user_line);
    }

    private function apcDelUserline()
    {
        \EmPHyre\Cache::Delete(self::$apcu_userline);
    }

    public function lastLogin()
    {
        //can merge the queries from this and the last_ip if we like
        return self::$db->pquery(
            "SELECT time FROM user_logins WHERE user_id = ? ORDER BY loginid DESC LIMIT 1",
            $this->userid
        )->fetchField();
    }

    public function lastIP()
    {
        $long = self::$db->pquery(
            "SELECT ipv4 FROM user_logins WHERE user_id = ? ORDER BY loginid DESC LIMIT 1",
            $this->userid
        )->fetchField();

        return long2ip((float)$long);
    }

    public function changePassword($pw1 = null, $pw2 = null)
    {
        if ($error = Validate::password($pw1, $pw2)) {
            return $error;
        } else {
            $salt = Password::generateSalt();
            $password = Password::cryptSHA512($pw1, $salt);
            $this->password = $password;
            $this->salt = $salt;

            $result = $this->commit();
            if ($result->isError()) {
                return $result;
            }
        }
        return new Result('EDITED_PASSWORD', $this->userid, true);
    }

    public function display()
    {
        return $this->user_name; //for now
    }

    public function getId()
    {
        //this function will have an analogue in each class
        return $this->userid;
    }

    public function getUUID()
    {
        return URL::encode64($this->uuid);
    }
}
