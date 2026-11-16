<?php

/**
 * Error Log is a custom error handler
 *
 * PHP version 5
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
 * @since    Pulled out of errorlog.php 2016-03-15
 */


/**
 * Text safe to relay to IRC (#15): control characters (CR/LF could inject IRC commands as the
 * bot) become spaces, secret query parameters are masked, length is capped.
 *
 * @param string $text Error text; may contain outside input (remote responses, player text, URLs)
 * @param int    $max  Maximum length
 *
 * @return string
 */
function emphyreErrorRelayText($text, $max = 400)
{
    $text = preg_replace('/[\x00-\x1f\x7f]+/', ' ', (string)$text);
    $names = '(?:[A-Za-z0-9]+[_-])*(?:apicode|api_?key|key|token|password|passwd|pass|pwd|secret|player_site_id|session|sess_?id|sid|phpsessid)';
    // name=value and name%3Dvalue (query strings, form data, cookies), any prefix: auth_token=, client_secret=
    $text = preg_replace('/\b('.$names.')(=|%3D)[^&\s,;"\']*/i', '$1$2***', $text);
    // JSON / PHP-array style: "token":"value", 'key' => 'value'
    $text = preg_replace('/(["\']'.$names.'["\']\s*(?::|=>)\s*)(["\'])(?:\\\\.|(?!\2).)*\2/i', '$1$2***$2', $text);
    // user:password@ in URLs
    $text = preg_replace('#(\w+://[^/\s:@]+):[^/\s@]*@#', '$1:***@', $text);
    if (strlen($text) > $max) {
        $text = substr($text, 0, $max - 3).'...';
    }

    return trim($text);
}//end emphyreErrorRelayText()


/**
 * One CSV field: quotes doubled, newlines flattened, so a message can't break the log format.
 *
 * @param string $value The value
 *
 * @return string       The quoted field
 */
function emphyreErrorCsvField($value)
{
    return '"'.str_replace('"', '""', preg_replace('/[\r\n]+/', ' ', (string)$value)).'"';
}//end emphyreErrorCsvField()


/**
 * Custom Error Handler, installed by errorlog.php when the app sets $errorlogging.
 *
 * Globals the app sets (all optional):
 *  - $sitebasedir          base directory: logs go to $sitebasedir/logs/*.csv, and file names are
 *                          shown relative to it (no logging to files when unset)
 *  - $errorlogging         >= 2: show the full error to the visitor on fatal errors (also with
 *                          $debug and a logged-in user, as before)
 *  - $output_errors_to_irc true: forward errors to the bot cluster (needs CommClient)
 *  - $debug                echo each error line
 *  - $user                 current user object (its getId() is logged)
 *
 * @param int    $errno    the php error number
 * @param string $errmsg   the php error message
 * @param string $filename the filename of the error
 * @param int    $linenum  the line number of the error
 * @param array  $vars     the error context (PHP < 8 only; unused)
 *
 * @return bool            true (handled); fatal levels end the script
 */
function userErrorHandler($errno, $errmsg, $filename, $linenum, $vars = null)
{
    global $sitebasedir, $errorlogging, $output_errors_to_irc, $debug, $user;

    if (!(error_reporting() & $errno)) {
        // disabled with the @ operator, or not reported at this level: handled, carry on (returning
        // false would hand it to PHP, which still ends the script for @E_USER_ERROR on 7.2)
        return true;
    }

    $time   = gmdate("M d Y H:i:s");
    $userid = (is_object($user) && method_exists($user, 'getId') ? (int)$user->getId() : 0);
    $ip     = (isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : 'cli');
    $self   = (isset($_SERVER['PHP_SELF']) ? $_SERVER['PHP_SELF'] : '');
    $base   = ($sitebasedir ? rtrim($sitebasedir, '/').'/' : null);

    static $errortype = [
                         E_ERROR             => "Error",
                         E_WARNING           => "Warning",
                         E_PARSE             => "Parsing Error",
                         E_NOTICE            => "Notice",
                         E_CORE_ERROR        => "Core Error",
                         E_CORE_WARNING      => "Core Warning",
                         E_COMPILE_ERROR     => "Compile Error",
                         E_COMPILE_WARNING   => "Compile Warning",
                         E_USER_ERROR        => "User Error",
                         E_USER_WARNING      => "User Warning",
                         E_USER_NOTICE       => "User Notice",
                         E_STRICT            => "PHP Strict",
                         E_RECOVERABLE_ERROR => "Recoverable Error",
                         E_DEPRECATED        => "Deprecated",
                         E_USER_DEPRECATED   => "User Deprecated",
                        ];
    $errlevel = (isset($errortype[$errno]) ? $errortype[$errno] : "Error $errno");

    if ($base && strpos($filename, $base) === 0) {
        $filename = substr($filename, strlen($base));
    }

    $errmsg = preg_replace("/^(.*)\[<(.*)>\](.*)$/", "\\1\\3", $errmsg);

    $backoutput = "";
    $backtrace  = debug_backtrace();
    // ignore $backtrace[0] as that is this function, the errorlogger; only show 4 levels deep
    for ($i = 1; $i < 5 && $i < count($backtrace); $i++) {
        $errfile = (isset($backtrace[$i]['file']) ? $backtrace[$i]['file'] : '');
        if ($base && strpos($errfile, $base) === 0) {
            $errfile = substr($errfile, strlen($base));
        }

        $line     = (isset($backtrace[$i]['line']) ? $backtrace[$i]['line'] : '');
        $function = (isset($backtrace[$i]['function']) ? $backtrace[$i]['function'] : '');
        $args     = (isset($backtrace[$i]['args']) ? count($backtrace[$i]['args']) : '');

        $backoutput .= "$errfile:$line:$function($args)";

        // show if there are more levels that were cut off
        if (($i + 1) < count($backtrace)) {
            $backoutput .= "<-";
        }
    }

    $fields = [$time, $self, $userid, $ip, "$filename: $linenum", "($errlevel)", $errmsg, $backoutput];
    $str    = implode(',', array_map('emphyreErrorCsvField', $fields))."\r\n";

    if ($debug) {
        echo htmlspecialchars($str, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    $logged = false;
    if ($base) {
        if (in_array($errno, [E_USER_ERROR], true)) {
            $file = $base.'logs/usererrors.csv';
        } elseif (in_array($errno, [E_ERROR, E_WARNING, E_PARSE, E_NOTICE, E_CORE_ERROR, E_CORE_WARNING, E_COMPILE_ERROR, E_COMPILE_WARNING], true)) {
            $file = $base.'logs/errors.csv';
        } else {
            $file = $base.'logs/userwarnings.csv';
        }

        $logged = (file_put_contents($file, $str, FILE_APPEND | LOCK_EX) !== false);
    }

    if ($output_errors_to_irc && class_exists('CommClient')) {
        // THIS PART IS ONLY FOR IF YOU HAVE THE BOT CLUSTER WORKING, TO SEND ERRORS TO IRC!
        if (strncmp($errmsg, '{NODETAIL_IRC}', 14) == 0) {
            $str2 = substr($errmsg, 14);
        } else {
            $str2 = "($errlevel) $errmsg,$filename:$linenum,$self,$userid,$ip";
        }

        $comm = new CommClient();
        $comm->send('forward', ['type' => 'website_error', 'data' => emphyreErrorRelayText($str2)."\r\n"]);
    }

    if (!$logged) {
        // no log directory configured (or not writable): PHP's own error_log still gets it
        error_log(rtrim($str));
    }

    // Terminate script if fatal error
    $nonfatal = [E_WARNING, E_NOTICE, E_USER_WARNING, E_USER_NOTICE, E_STRICT, E_DEPRECATED, E_USER_DEPRECATED];
    if (!in_array($errno, $nonfatal, true)) {
        if ($errorlogging >= 2 || ($userid && $debug)) {
            die("A fatal error has occured. Script execution has been aborted:<br/>\n".htmlspecialchars($str, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'));
        }

        die("A fatal error has occured. Script execution has been aborted<br/>");
    }

    return true;
}//end userErrorHandler()
