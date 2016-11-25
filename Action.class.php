<?php
/**
 * Action object for the E project
 *
 * PHP version 7
 *
 * ------
 * This project uses the EmPHyre microframework,
 * and is built off the EmPHyre example files
 *
 * Written by Julian Haagsma.
 *
 * @category Classes
 * @package  EmPHyre
 * @author   Julian Haagsma <jhaagsma@gmail.com>
 * @license  All files are licensed under the MIT License.
 * @link     https://demo3.phasesensors.com
 * @since    Pulled apart Nov 2016
 */
namespace EmPHyre;

class Action extends \EmPHyre\CRUD
{
    protected static $tableName  = 'user_actions';
    protected static $primaryKey = 'action_id';
    private static $flags        = null;


    public function initialize()
    {
        parent::initialize();
        $this->action_flag = self::getActionFlag($this->action_type);

    }//end initialize()


    public static function actions($user_id = null)
    {
        self::$db = Container::getDb();
        if ($user_id != null) {
            return self::$db->pquery(
                "SELECT action_id FROM user_actions WHERE user_id = ? ORDER BY `time` DESC",
                $user_id
            )->fetchFieldSet();
        }

        return self::$db->pquery(
            "SELECT action_id FROM user_actions ORDER BY `time` DESC"
        )->fetchFieldSet();

    }//end actions()


    public function getUserID()
    {
        return $this->user_id;

    }//end getUserID()


    public function userDisplay()
    {
        $user = Container::newUser($this->user_id);
        return $user->display();

    }//end userDisplay()


    public function timeDisplay($extra = false)
    {
        $display = date('Y-m-d H:i:s', $this->time);
        if (!$extra) {
            return $display;
        }

        return $display.'&nbsp;('.datetime($this->time).')';

    }//end timeDisplay()


    public function display()
    {
        switch ($this->action_flag) {
            default:
                return $this->action_flag.': '.$this->foreign_key;
        }

    }//end display()


    public function getActionTime()
    {
        return $this->time;

    }//end getActionTime()


    public function getActionUserTime($extra = false)
    {
        return $this->userDisplay().' at '.$this->timeDisplay($extra);

    }//end getActionUserTime()


    public static function addAction(
        $user_id,
        $action_flag,
        $foreign_key = null,
        $foreign_misc = null,
        $time = 0
    ) {
    
    
    
    
    
    
    
    
    
    
    
        if ($time == 0) {
            $time = time();
        }

        self::$db = Container::getDb();

        $action_type = self::getActionType($action_flag);

        $action_id = self::$db->pquery(
            "INSERT INTO user_actions
            SET user_id=?, `time`=?, action_type=?, foreign_key=?, foreign_misc=?",
            $user_id,
            $time,
            $action_type,
            $foreign_key,
            $foreign_misc
        )->insertId();

        return new Result("ADDED_ACTION", $action_id, true);

    }//end addAction()


    public static function getActionType($action_flag)
    {
        $action_type = self::$db->pquery(
            "SELECT action_type FROM action_types WHERE action_flag = ?",
            $action_flag
        )->fetchField();

        if (!$action_type) {
            $action_type = self::$db->pquery(
                "INSERT INTO action_types SET action_flag = ?",
                $action_flag
            )->insertId();
        }

        return $action_type;

    }//end getActionType()


    public static function getActionFlag($action_type)
    {
        $action_flag = self::$db->pquery(
            "SELECT action_flag FROM action_types WHERE action_type = ?",
            $action_type
        )->fetchField();

        return $action_flag;

    }//end getActionFlag()
}//end class
