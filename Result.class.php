<?php
/**
 * Result is the result object for the PhaseWeb project
 *
 * PHP version 7
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

class Result
{

    public $type;

    /**
     * The construct
     *
     * @param string  $type    The result tag/string/type/whatever
     * @param mixed   $val     Usually an int, like user_id, group_id
     * @param boolean $success Whether or not it was a successful result
     * @param boolean $error   Whether or not it was an UNSUCCESSFUL result
     */
    public function __construct($type, $val = null, $success = false, $error = true)
    {
        $this->type    = $type;
        $this->val     = $val;
        $this->error   = $success ? false : $error;
        $this->success = $success;

    }//end __construct()

    /**
     * Set the success level to true
     *
     * @return null
     */
    protected function setSuccess()
    {
        $this->error   = false;
        $this->success = true;

    }//end setSuccess()

    /**
     * Set the object to neutral
     *
     * @return null
     */
    protected function setNeutral()
    {
        $this->error   = false;
        $this->success = false;

    }//end setNeutral()

    /**
     * Parse the error message
     *
     * @param  Array $bits Array of bits of the message
     *
     * @return string      Parsed string
     */
    private function restOfTheBits($bits)
    {
        unset($bits[0]);
        foreach ($bits as $k => $bit) {
            $bits[$k] = ucwords(strtolower($bit));
        }

        return implode(' ', $bits);

    }//end restOfTheBits()

    /**
     * Check the $type for a pattern
     *
     * @return String|Bool The message, or FALSE
     */
    protected function patternedMessage()
    {
        $bits = explode('_', $this->type);
        if ($bits[0] == 'ADDED') {
            $this->setSuccess();
            return self::restOfTheBits($bits).' added successfully.';
        } elseif ($bits[0] == 'EDITED') {
            $this->setSuccess();
            return self::restOfTheBits($bits).' edited successfully.';
        } elseif ($bits[0] == 'DISABLED') {
            $this->setSuccess();
            return self::restOfTheBits($bits).' disabled successfully.';
        } elseif ($bits[0] == 'EXISTS') {
            return 'This '.self::restOfTheBits($bits).' already exists.';
        } elseif ($bits[0] == 'INVALID') {
            return 'Invalid '.self::restOfTheBits($bits).($this->val ? ': '.$this->val : null);
        } elseif ($bits[0] == 'REQUIRED') {
            return 'You must specify a '.self::restOfTheBits($bits).'.';
        } elseif ($bits[0] == 'UNCHANGED') {
            $this->setNeutral();
            return 'The '.self::restOfTheBits($bits).' was unchanged.';
        } elseif ($bits[0] == 'LOGGED') {
            $this->setNeutral();
            return 'The '.self::restOfTheBits($bits).' was logged.';
        }//end if

        return false;
    }//end patternedMessage()

    /**
     * Return a message for custom types of flags
     *
     * @return string The error message
     */
    public function message()
    {
        // I don't like having custom error messages stored in /core
        // Consider making a non-core file, perhaps result.messages.php in / dir,
        // with custom error messages
        // and perhaps call a global function from this file in the
        // Result __construct (with some sort of "is_defined")
        // to load all the custom error messages.
        $patterned = $this->patternedMessage();
        if ($patterned !== false) {
            return $patterned;
        }

        $messageList = self::messageList();

        if (isset($messageList[$this->type])) {
            return $messageList[$this->type];
        } else {
            return $messageList['DEFAULT'];
        }

    }//end message()

    /**
     * Returns a list of messages
     *
     * @return array List of messages
     */
    private function messageList()
    {
        return [
            'NOT_LOGGED_IN'
                => 'You must be logged in to access this page.',
            'NOT_ADMIN'
                => 'You must be an administrator to access this page.',
            'EMAIL_NOT_VALID'
                => 'Email is not valid.',
            'PASSWORD_NOMATCH'
                => 'Passwords do not match.',
            'PASSWORD_SHORT'
                => 'Password is too short.'.($this->val ? " (Minimum {$this->val})" : null),
            'PASSWORD_NO_LETTER'
                => 'Password must contain a letter (as well as a number and special character).',
            'PASSWORD_NO_NUMBER'
                => 'Password must contain a number (as well as a special character).',
            'PASSWORD_NO_SPECIAL'
                => 'Password must contain a special character (as well as a number).',
            'INVALID_INPUT'
                => 'Invalid Input'.($this->val ? ': '.$this->val : null),
            'INSERT_FAIL'
                => 'Could not insert into Database.'.($this->val ? ': '.$this->val : null),
            'UPDATE_FAIL'
                => 'Could not update the Database.'.($this->val ? ': '.$this->val : null),
            'DEFAULT'
                => 'Error: '.$this->type.($this->val ? ': '.$this->val : null),
        ];
    }//end messageList()

    /**
     * Determine if the message was an error message
     *
     * @return boolean Returns true if error, false otherwise
     */
    public function isError()
    {
        return $this->error ? true : false;

    }//end isError()

    /**
     * Turn the error object into something printable, by returning the type
     *
     * @return string String of the error type
     */
    public function __toString()
    {
        return $this->type;

    }//end __toString()

    /**
     * Make the error object into something that can be put in a url
     * Includes values as &result_val=($this->val)
     * Includes success as &result_success=1
     *
     * @return String The error object in URL form
     */
    public function toURL()
    {
        return '&result='.urlencode($this->type)
            .($this->val ? '&result_val='.urlencode($this->val) : null)
            .($this->success ? '&result_success=1' : null);

    }//end toURL()

    /**
     * Make the object able to cast to a bool
     *
     * @return bool True or false, based on "success"
     */
    public function __toBool()
    {
        /*
            Success evaluates us to false so we can do the following for failure:
            if ($result = someFunction()) {
            return $result;
            }
        */
        return $this->success ? false : true;

    }//end __toBool()
}//end class
