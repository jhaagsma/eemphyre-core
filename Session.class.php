<?php namespace EmPHyre;

/**
 * Session is the session object for the EmPHyre project
 *
 * PHP version 7
 *
 * ------
 * These files are part of the empiresPHPframework;
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
 * @category Core
 * @package  EmPHyre
 * @author   Julian Haagsma <jhaagsma@gmail.com>
 * @author   Timo Ewalds <tewalds@gmail.com>
 * @author   Dave McVittie <dave.mcvittie@gmail.com>
 * @author   Joe Obbish <slagpit@earthempires.com>
 * @license  All files are licensed under the MIT License.
 * @link     https://github.com/jhaagsma/emPHyre
 * @since    Recombined from empiresPHPframework extensions on Nov 4 2016
 */

//THIS IS HOW LONG WE WANT LOGIN SESSIONS TO LAST IN SECONDS (obivously)
define('SESSION_TIME', 16 * 3600);
defined('COOKIE_NAME') or define('COOKIE_NAME', 'emphyre');
defined('APC_LAST_CLEAR_SESSION') or define('APC_LAST_CLEAR_SESSION', 'em:lcs');
defined('APC_ACTIVE_SESSION_PREFIX') or define('APC_ACTIVE_SESSION_PREFIX', 'em:as-');


abstract class Session
{
    private static $db;
    private static $cookieUser = null;
    private static $cookieKey  = null;

    private static $login_id   = null;
    private static $expiretime = null;
    private static $lastreal   = null;

    public static function newSession($user_id)
    {
        self::$db = Container::getDb();

        $time = time();
        $key  = bin2hex(random_bytes(16));   // 32 hex chars, the shape parseCookie() accepts (#5)

        //THIS IS HOW LONG WE WANT LOGIN SESSIONS TO LAST IN SECONDS (obivously);
        $expire = $time + SESSION_TIME;
        //$expireday = $time + SESSION_TIME + 24*3600*60;
        //set old sessions to expire in the past
        $expire_old = $time - 300;
        //set old sessions to expire in the past...
        self::$db->pquery("UPDATE active_sessions SET expiretime = ? WHERE user_id = ?", $expire_old, $user_id);
        //...and then clear old sessions to make sure they are gone
        self::clearOldSessions(true);

        $session_id = self::$db->pquery(
            "INSERT INTO active_sessions SET user_id = ?, `cookieval` = ?, expiretime = ?",
            $user_id,
            $key,
            $expire
        )->insertid();

        setcookie(COOKIE_NAME, "$user_id:$key", 0, "/", '.'.URL::getDomainName(), false, true);
    }//end newSession()


    public static function parseCookie()
    {
        self::$cookieUser = null;
        self::$cookieKey  = null;
        self::$login_id   = null;   // set again only by activeSession() for this cookie
        self::$expiretime = null;
        self::$lastreal   = null;

        $cookie = URL::getCOOKIEval(COOKIE_NAME);
        // "<user id>:<32 hex key>" only (newSession() sets md5 hex keys). Anything else is no session,
        // so a forged cookie never reaches the SQL lookups below (#4).
        if (!is_string($cookie) || !preg_match('/^([1-9][0-9]{0,9}):([0-9a-f]{32})$/D', $cookie, $m)) {
            return null;
        }

        self::$cookieUser = $m[1];
        self::$cookieKey  = $m[2];
    }//end parseCookie()


    public static function doUserCheck()
    {
        //This function needs to be cleaned up and formalized in some manner

        self::clearOldSessions();
        self::parseCookie();

        //THIS IS ASSIGNING ACTIVE ROW, SO SINGLE EQUALS IS CORRECT
        if (!self::activeSession()) {
            return null;
        }

        $expiretime = time() + SESSION_TIME;
        self::updateActiveSession($expiretime);

        return Container::newUser(self::$cookieUser);  //Create a User class!
    }//end doUserCheck()


    //delete a specific key, or ALL of that user's sessions
    //(that, of course, assumes that you can have mulitple logins)
    public static function expireSession($single = false)
    {
        self::$db = Container::getDb();
        self::parseCookie();

        // Only the holder of a live session may end sessions: the cookie's key must match one (#10).
        // Before, a forged "<victim id>:anything" cookie deleted all of the victim's sessions.
        if (self::$cookieUser !== null && self::activeSession(true)) {
            self::clearActiveSession();
            if ($single) {
                //Just this session
                self::$db->pquery("DELETE FROM active_sessions WHERE login_id = ?", (int)self::$login_id);
            } else {
                //ALL of the user's sessions
                self::$db->pquery("DELETE FROM active_sessions WHERE user_id = ?", (int)self::$cookieUser);
            }
        }

        //clear the cookie to nothing
        setcookie(COOKIE_NAME, '', 1);
    }//end expireSession()


    public static function activeSession($force = false)
    {
        self::$login_id = null;
        if (self::$cookieUser === null || self::$cookieKey === null) {
            return false;
        }

        $activerow = \EmPHyre\Cache::fetch(APC_ACTIVE_SESSION_PREFIX.self::$cookieUser);
        if (!$activerow || !hash_equals((string)$activerow['cookieval'], self::$cookieKey) || $force) {
            $activerow = self::$db->pquery(
                'SELECT login_id, cookieval, expiretime FROM active_sessions WHERE user_id = ? and `cookieval` = ?',
                (int)self::$cookieUser,
                self::$cookieKey
            )->fetchRow();

            // the SQL match is case-insensitive under most collations; the key must match exactly
            if (!$activerow || !hash_equals((string)$activerow['cookieval'], self::$cookieKey)) {
                return false;
            }

            $activerow['lastreal'] = 0;
        }

        self::$login_id   = $activerow['login_id'];
        self::$expiretime = $activerow['expiretime'];
        self::$lastreal   = $activerow['lastreal'];

        return self::$expiretime > time() ? true : false;
    }//end activeSession()


    public static function clearOldSessions($forced = false)
    {
        self::$db    = Container::getDb();
        $not_due_yet = \EmPHyre\Cache::fetch(APC_LAST_CLEAR_SESSION);
        if (!$not_due_yet || $forced) {
            \EmPHyre\Cache::store(APC_LAST_CLEAR_SESSION, true, 120);
            $clearids = self::$db->pquery('SELECT * FROM active_sessions WHERE expiretime < ?', time())->fetchRowSet();
            foreach ($clearids as $clearid) {
                \EmPHyre\Cache::delete(APC_ACTIVE_SESSION_PREFIX.$clearid['user_id']);
                self::$db->pquery('DELETE FROM active_sessions WHERE login_id = ?', $clearid['login_id']);
            }

            return true;
        }

        return false;
    }//end clearOldSessions()


    private static function updateActiveSession($expiretime)
    {
        $activerow = [
            'lastreal' => self::$lastreal,
            'login_id' => self::$login_id,
            'cookieval' => self::$cookieKey,
            'expiretime' => $expiretime
        ];

        self::$db = Container::getDb();
        if (self::$lastreal + 120 < time()) {
            $good = self::$db->pquery(
                'UPDATE active_sessions SET expiretime = ? WHERE login_id = ?',
                $expiretime,
                self::$login_id
            )->affectedRows();

            if (!$good) {
                \EmPHyre\Cache::delete(APC_ACTIVE_SESSION_PREFIX.self::$cookieUser);
                return;
            }

            self::$lastreal = $activerow['lastreal'] = time();
        }

        \EmPHyre\Cache::store(APC_ACTIVE_SESSION_PREFIX.self::$cookieUser, $activerow, 120);

        return;
    }//end updateActiveSession()


    /**
     * End all of a user's sessions except the one making this request (if it is theirs), e.g.
     * after a password change, so a stolen session cookie stops working (#16). For another user
     * (an admin changing someone's password) all their sessions end.
     *
     * @param int $user_id The user whose sessions to end
     *
     * @return null
     */
    public static function endOtherSessions($user_id)
    {
        self::$db = Container::getDb();
        $user_id  = (int)$user_id;
        // login_id is only set once activeSession() accepted this request's cookie
        $mine = (self::$cookieUser !== null && (int)self::$cookieUser === $user_id && self::$login_id);
        $keep = ($mine ? (int)self::$login_id : 0);

        self::$db->pquery('DELETE FROM active_sessions WHERE user_id = ? AND login_id != ?', $user_id, $keep);
        \EmPHyre\Cache::delete(APC_ACTIVE_SESSION_PREFIX.$user_id);
    }//end endOtherSessions()


    public static function clearActiveSession()
    {
        \EmPHyre\Cache::delete(APC_ACTIVE_SESSION_PREFIX.self::$cookieUser);
    }//end clearActiveSession()
}//end class
