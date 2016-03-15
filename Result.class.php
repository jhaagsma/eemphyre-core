<?php

namespace EmPHyre;

class Result
{

    public $type;
    public function __construct($type, $additional = null, $success = false)
    {
        $this->type = $type;
        $this->additional = $additional;
        $this->error = true;
        $this->success = $success;
    }

    private function set_success()
    {
        $this->error = false;
        $this->success = true;
    }

    public function message()
    {
        //I don't like having custom error messages stored in /core
        //Consider making a non-core file, perhaps result.messages.php in / dir, with custom error messages
        //and perhaps call a global function from this file in the Result __construct (with some sort of "is_defined")
        //to load all the custom error messages.
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
                return 'Password is too short.' . ($this->additional ? " (Minimum {$this->additional})" : null);
            case 'PASSWORD_NO_LETTER':
                return 'Password must contain a letter (as well as a number and special character).';
            case 'PASSWORD_NO_NUMBER':
                return 'Password must contain a number (as well as a special character).';
            case 'PASSWORD_NO_SPECIAL':
                return 'Password must contain a special character (as well as a number).';
            
            case 'USERNAME_EXISTS':
                return 'This username already exists.';
            case 'GROUP_EXISTS':
                return 'This group already exists.';

            case 'USER_ADDED':
                $this->set_success();
                return 'User added successfully.';
            case 'USER_EDITED':
                $this->set_success();
                return 'User edited successfully.';
            case 'GROUP_ADDED':
                $this->set_success();
                return 'Group added successfully.';
            case 'GROUP_EDITED':
                $this->set_success();
                return 'Group edited successfully.';

            case 'CLIENT_ADDED':
                $this->set_success();
                return 'Client added successfully.';
            
            case 'UNIT_ADDED':
                $this->set_success();
                return 'Unit added successfully.';

            case 'PROPERTY_ADDED':
                $this->set_success();
                return 'Property added successfully.';

            case 'TESTTYPE_ADDED':
                $this->set_success();
                return 'Test Type added successfully.';

            case 'INVALID_INPUT':
                return 'Invalid Input' . ($this->additional ? ': ' . $this->additional : null);
            default:
                return 'Error: ' . $this->type . ($this->additional ? ' - ' . $this->additional : null);
        }
    }

    public function __toString()
    {
        return $this->type;
    }
}
