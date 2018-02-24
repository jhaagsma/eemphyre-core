<?php namespace EmPHyre;

/**
 * Password is the password object for the EmPHyre project
 *
 * PHP version 5
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
 * @since    Nov 2016
 * */


class Password
{


    protected function __construct()
    {
        // this ensures nobody can instantiate
    }//end __construct()


    // FUNCTIONS FOR LOGIN AND USER REGISTRATION
    public static function cryptSHA512($password, $salt)
    {
        $bits   = explode('$', $salt, 2);
        $rounds = $bits[0];
        $salt   = $bits[1];

        for ($i = 0; $i < $rounds; $i++) {
            $password = hash('sha512', $password.$salt);
        }

        return $password;
    }//end cryptSHA512()


    public static function generateSalt()
    {
        $rounds = mt_rand(1000, 9999);
        $salt   = uniqid('', true);
        return $rounds.'$'.$salt;
    }//end generateSalt()
}//end class
