<?php
/**
 * Group is the permission group object for the eemphyre project
 *
 * PHP version 5
 *
 * ------
 * This files are part of the empiresPHPframework;
 * The original framework core (specifically the mysql.php
 * the router.php and the errorlog) was started by Timo Ewalds,
 * and rewritten to use APC and extended by Julian Haagsma,
 * for use in Earth Empires (located at http://www.earthempires.com );
 * it was spun out for use on other projects.
 *
 * Written for PhaseSensors Julian Haagsma.
 *
 * @category Classes
 * @package  EmPHyre
 * @author   Julian Haagsma <jhaagsma@gmail.com>
 * @license  https://opensource.org/licenses/MIT The MIT License (MIT)
 * @link     https://github.com/jhaagsma/emPHyre
 * @since    Nov 2016
 */

namespace EmPHyre;

/**
 * Group class for EmPhyre;
 * For "groups" table
 * Link to "user" table through "user_permission_groups" table
 *
 * @category CRUD
 * @package  EmPhyre
 * @author   Julian Haagsma <jhaagsma@gmail.com>
 * @license  https://opensource.org/licenses/MIT The MIT License (MIT)
 * @link     https://github.com/jhaagsma/emPHyre
 * @since    Nov 2016
 */
class Group extends \EmPHyre\CRUD
{
    protected static $db;
    protected static $tableName  = 'groups';
    protected static $primaryKey = 'group_id';


    /**
     * Get Group members
     *
     * @return int sum of user_id belonging to group_id
     */
    public function members()
    {
        $permissions = new M2M('user_permission_groups', 'user_id', 'group_id');
        return $permissions->countM2M($this->group_id, true);

    }//end members()


    public static function add($name = null)
    {
        $group_id = parent::addByArray(['group_name' => $name]);

        if (!$group_id) {
            return new Result("FAIL_INSERT");
        }

        return new Result('ADDED_GROUP', $group_id, true);

    }//end add()


    public function edit($name = null)
    {
        $this->group_name = $name;
        $success          = $this->commit();

        if (!$success) {
            return new Result('UNCHANGED_GROUP', $this->getId(), false, false);
        }

        return new Result('EDITED_GROUP', $this->getId(), true);

    }//end edit()


    public function display()
    {
        return $this->group_name;

    }//end display()


    public function addUser($user_id)
    {
        return static::addUserGroup($user_id, $this->getId());

    }//end addUser()

    /**
     * Add a user to a group
     *
     * @param integer $user_id  The User Id
     * @param integer $group_id The Group Id
     *
     * @return Number of affected rows?
     */
    protected static function addUserGroup($user_id, $group_id)
    {
        $permissions = new M2M('user_permission_groups', 'user_id', 'group_id');
        return $permissions->add($user_id, $group_id);

    }//end addUserGroup()


    public function delUser($user_id)
    {
        return static::delUserGroup($user_id, $this->getId());

    }//end delUser()

    /**
     * Delete a user from a group
     *
     * @param integer $user_id  The User Id
     * @param integer $group_id The Group Id
     *
     * @return Number of affected rows?
     */
    protected static function delUserGroup($user_id, $group_id)
    {
        $permissions = new M2M('user_permission_groups', 'user_id', 'group_id');
        return $permissions->delete($user_id, $group_id);

    }//end delUserGroup()


    public static function userGroups($user_id)
    {
        $permissions = new M2M('user_permission_groups', 'user_id', 'group_id');
        return $permissions->getM2M($user_id);

    }//end userGroups()


    public static function alterUserGroups($user_id, $newPermissions = [])
    {
        $changed = false;

        $currentPermissions = static::userGroups($user_id);

        $add    = array_diff($newPermissions, $currentPermissions);
        $delete = array_diff($currentPermissions, $newPermissions);

        // new dBug($currentPermissions);
        // new dBug($newPermissions);
        // new dBug($add);
        // new dBug($delete);
        // exit;
        if (empty($add) && empty($delete)) {
            return new Result("UNCHANGED_USER", $user_id, true, false);
        }

        foreach ($delete as $group_id) {
            static::delUserGroup($user_id, $group_id);
        }

        foreach ($add as $group_id) {
            static::addUserGroup($user_id, $group_id);
        }

        return new Result("EDITED_USER", $user_id, true);

    }//end alterUserGroups()

    /**
     * Disable the group
     * REMOVE USERS FROM THE GROUP
     *
     * @return Result What happened
     */
    public function disable()
    {
        $worked = parent::disable();

        if (!$worked) {
            return new Result("UNCHANGED_GROUP", $this->getId(), true, false);
        }

        $members = User::users($this->group_id);
        foreach ($members as $user_id) {
            $this->delUser($user_id);
        }

        return new Result("DISABLED_GROUP", $this->getId(), true);
    }//end disable()
}//end class
