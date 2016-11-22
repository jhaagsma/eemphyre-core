<?php
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
 * @author   Dave McVittie <dave.mcvittie@gmail.com>
 * @author   Joe Obbish <slagpit@earthempires.com>
 * @license  All files are licensed under the MIT License.
 * @link     https://github.com/jhaagsma/emPHyre
 * @since    Pulled out of MysqlDb.class.php 2016-03-15
 */
 namespace EmPHyre;

// should we define these in the Container, or in the Cache object, perhaps...?
// define('APC_USER_PREPEND', 'd3-ul-');

/**
 * User Class
 *
 * @category CRUD
 * @package  EmPhyre
 * @author   Julian Haagsma <jhaagsma@gmail.com>
 * @license  https://opensource.org/licenses/MIT The MIT License (MIT)
 * @link     https://github.com/jhaagsma/emPHyre
 * @since    Nov 2016
 */
class User extends \EmPHyre\CRUD
{
    protected static $db;
    protected static $tableName = 'users';
    protected static $primaryKey = 'user_id';
    private static $apcu_userline;

    public function __construct($user_id)
    {
        parent::__construct($user_id);

        // ensure APC_USER_PREPEND is defined
        self::definePrependAPC();

        // this is a hack for when the user_id isn't defined
        // honestly that shouldn't ever come up,
        // but it did (error in the log manager)
        // so better to handle it than not?
        $this->user_name = null;
        $this->uuid = null;
    }

    public static function definePrependAPC($prepend = null)
    {
        //this needs to be called so that we don't conflict with other projects
        if (!defined('APC_USER_PREPEND')) {
            $prepend = ($prepend ? $prepend.'-' : null);
            define('APC_USER_PREPEND', $prepend.'ul-');
        }
    }

    public static function getUserIdFromName($user_name = null)
    {
        self::$db = Container::getDb();
        return self::$db->pquery(
            "SELECT user_id FROM users WHERE user_name = ?",
            $user_name
        )->fetchField();
    }

    public static function getUserIdFromUUID($uuid = null)
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
        self::db();
        if (!$group_id) {
            //normal query
            return parent::primaryListNotDisabled();
        }

        //query A, then "join" with users to check disabled
        $permissions = new M2M('user_permission_groups', 'user_id', 'group_id');
        $users = $permissions->getM2M($group_id, true);

        return self::filterPKArray($users, 'disabled', false);
    }

    public static function addUser($user_name, $pw1, $pw2)
    {
        if ($error = Validate::email($user_name)) {
            return $error;
        }

        self::$db = Container::getDb();

        $result = self::checkExists($user_name);
        if ($result->isError()) {
            return $result;
        }

        //need to change Validate::password with whatever password rules we want
        if ($error = Validate::password($pw1, $pw2)) {
            return $error;
        }

        $user_id = parent::addByArray(
            ['uuid'=>self::newUUID(), 'user_name'=>$user_name]
        );

        if (!$user_id) {
            return new Result("FAIL_INSERT");
        }

        $newuser = Container::newUser($user_id);
        $passResult = $newuser->changePassword($pw1, $pw2);
        if ($passResult->isError()) {
            return $passResult;
        }

        return new Result('ADDED_USER', $user_id, true);
    }

    public function isDisabled()
    {
        return $this->disabled ? true : false;
    }

    public static function checkExists($user_name = null)
    {
        if ($user_name === null) {
            return new Result('INVALID_INPUT', $user_name);
        }

        //reuse functions
        $user_id = self::getUserIdFromName($user_name);
        if ($user_id) {
            return new Result('EXISTS', $user_name);
        }

        //this is a success because this function is used for finding collisions
        return new Result('NOEXIST_USER', $user_name, true);
    }

    public function edit($user_name, $pw1, $pw2)
    {
        $changed = false;
        if ($user_name != $this->user_name) {
            if ($error = Validate::email($user_name)) {
                return $error;
            }

            if ($error = self::checkExists($user_name)) {
                return $error;
            }
        }

        if ($pw1) {
            if ($error = $this->changePassword($pw1, $pw2)) {
                return $error;
            }
            $changed = true;
        }

        if ($user_name != $this->user_name) {
            $this->user_name = $user_name;

            $result = $this->commit();
            if (is_object($result) && $result->isError()) {
                return $result;
            }

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
        $cryptPass = \EmPHyre\Password::cryptSHA512($password, $this->salt);
        return $cryptPass == $this->password ? true : false;
    }

    public function loggedIn()
    {
        $ip = ip2long($_SERVER['REMOTE_ADDR']);
        self::$db->pquery(
            "INSERT INTO user_logins SET user_id = ?, time = ?, ipv4 = ?",
            $this->user_id,
            time(),
            $ip
        );
        return;
    }

    private function getValues()
    {
        $this->ul = $this->apcUserline();
        if (!$this->ul) {
            $this->user_id = null;
            return;
        }
        // we don't want anything to be able to change their user_id,
        // that would be seriously messed up
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
        // i'm pretty sure we don't ever want to change the uuid ??
        unset($this->_data['uuid']);

        $result = parent::commit();

        if ($result) {
            return new Result("EDITED_USER", $this->getId(), true);
        } else {
            return new Result("UNCHANGED_USER", $this->getId(), false, false);
        }
    }

    private function apcUserline($column = null)
    {
        //deliberately errors if column isn't in the array
        $user_line = \EmPHyre\Cache::Fetch(self::$apcu_userline);
        if (!$user_line) {
             // this ideally shouldn't select *,
             // but for now we'll do it for convenience
            $user_line = self::$db->pquery(
                'SELECT * FROM users WHERE user_id = ?',
                $this->getId()
            )->fetchRow();
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
            "SELECT time FROM user_logins
                WHERE user_id = ? ORDER BY login_id DESC LIMIT 1",
            $this->getId()
        )->fetchField();
    }

    public function lastIP()
    {
        $long = self::$db->pquery(
            "SELECT ipv4 FROM user_logins
                WHERE user_id = ? ORDER BY login_id DESC LIMIT 1",
            $this->getId()
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
        return new Result('EDITED_PASSWORD', $this->getId(), true);
    }

    public function groups()
    {
        return Group::userGroups($this->getId());
    }

    public function editGroups($newPermissions = [])
    {
        return Group::alterUserGroups(
            $this->getId(),
            $newPermissions
        );
    }

    public function display()
    {
        return $this->user_name; //for now
    }

    public function getId()
    {
        //this function will have an analogue in each class
        return $this->user_id;
    }

    public function getUUID()
    {
        return URL::encode64($this->uuid);
    }
}
