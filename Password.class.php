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

    /**
     * Hash a new password (#5). The result is self-describing (algorithm, cost, salt), so the
     * users.salt column is not used for these; store '' there.
     *
     * @param  string $password The plain password
     *
     * @return string           The hash, for users.password (char(255))
     */
    public static function hash($password)
    {
        return password_hash((string)$password, PASSWORD_DEFAULT);
    }//end hash()


    /**
     * Check a password against a stored hash: password_hash() format, or the old iterated
     * sha512 ("<rounds>$<salt>" in the salt column), compared in constant time.
     *
     * @param  string $password The plain password tried
     * @param  string $stored   users.password
     * @param  string $salt     users.salt (only used by old hashes)
     *
     * @return array            [bool $ok, bool $needsRehash]: rehash old or outdated hashes
     */
    public static function verify($password, $stored, $salt)
    {
        $password = (string)$password;
        $stored   = (string)$stored;
        $salt     = (string)$salt;

        if (self::isModernHash($stored)) {
            $ok = password_verify($password, $stored);
            return [$ok, $ok && password_needs_rehash($stored, PASSWORD_DEFAULT)];
        }

        // old format: 128 hex chars and a "<rounds>$<salt>" salt; anything else (e.g. 'unset') never matches
        if (!preg_match('/^[0-9a-f]{128}$/D', $stored) || !preg_match('/^[0-9]+\$./', $salt)) {
            return [false, false];
        }

        $ok = hash_equals($stored, self::cryptSHA512($password, $salt));
        return [$ok, $ok];
    }//end verify()


    /**
     * @param  string $stored users.password
     *
     * @return boolean        Whether it is a password_hash() hash (they start with '$')
     */
    public static function isModernHash($stored)
    {
        return strncmp((string)$stored, '$', 1) === 0;
    }//end isModernHash()


    /**
     * The old iterated sha512. Only used to check (and then upgrade) old hashes; new passwords
     * use hash().
     */
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
