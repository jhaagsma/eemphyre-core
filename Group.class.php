<?php namespace EmPHyre;

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
 * @license  All files are licensed under the MIT License.
 * @link     https://github.com/jhaagsma/emPHyre
 * @since    Nov 2016
 */

class Group extends \EmPHyre\CRUD
{
    protected static $db;
    protected static $_table_name = 'groups';
    protected static $_primary_key = 'group_id';

    public function members()
    {
        $permissions = new M2M('user_permission_groups', 'user_id', 'group_id');
        return $permissions->countM2M($this->group_id, true);
    }

    public static function add($name = null)
    {
        $group_id = parent::addByArray(['group_name'=>$name]);

        if (!$group_id) {
            return new Result("FAIL_INSERT");
        }

        return new Result('ADDED_GROUP', $group_id, true);

    }

    public function edit($name = null)
    {
        $this->group_name = $name;
        $success = $this->commit();

        if (!$success) {
            return new Result('UNCHANGED_GROUP', $this->getId(), false, false);
        }

        return new Result('EDITED_GROUP', $this->getId(), true);
    }

    public function display()
    {
        return $this->group_name;
    }
}
