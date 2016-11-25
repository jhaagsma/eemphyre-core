<?php namespace EmPHyre;

/**
 * Result is the result object for the PhaseWeb project
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

class Result
{

    public $type;


    public function __construct($type, $val = null, $success = false, $error = true)
    {
        $this->type    = $type;
        $this->val     = $val;
        $this->error   = $success ? false : $error;
        $this->success = $success;

    }//end __construct()


    private function setSuccess()
    {
        $this->error   = false;
        $this->success = true;

    }//end setSuccess()


    private function setNeutral()
    {
        $this->error   = false;
        $this->success = false;

    }//end setNeutral()


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

        switch ($this->type) {
            case 'NOT_LOGGED_IN':
                return 'You must be logged in to access this page.';
            case 'NOT_ADMIN':
                return 'You must be an administrator to access this page.';
            case 'EMAIL_NOT_VALID':
                return 'Email is not valid.';
            case 'PASSWORD_NOMATCH':
                return 'Passwords do not match.';
            case 'PASSWORD_SHORT':
                return 'Password is too short.'.($this->val ? " (Minimum {$this->val})" : null);
            case 'PASSWORD_NO_LETTER':
                return 'Password must contain a letter (as well as a number and special character).';
            case 'PASSWORD_NO_NUMBER':
                return 'Password must contain a number (as well as a special character).';
            case 'PASSWORD_NO_SPECIAL':
                return 'Password must contain a special character (as well as a number).';
            case 'INVALID_INPUT':
                return 'Invalid Input'.($this->val ? ': '.$this->val : null);
            case 'INSERT_FAIL':
                return 'Could not insert into Database.'.($this->val ? ': '.$this->val : null);
            case 'UPDATE_FAIL':
                return 'Could not update the Database.'.($this->val ? ': '.$this->val : null);
            default:
                return 'Error: '.$this->type.($this->val ? ': '.$this->val : null);
        }//end switch

    }//end message()


    public function isError()
    {
        return $this->error ? true : false;

    }//end isError()


    public function __toString()
    {
        return $this->type;

    }//end __toString()


    public function toURL()
    {
        return '&result='.urlencode($this->type).($this->val ? '&result_val='.urlencode($this->val) : null).($this->success ? '&result_success=1' : null);

    }//end toURL()


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
