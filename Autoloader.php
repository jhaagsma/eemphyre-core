<?php
/**
 *
 * This is the Autoloader for the EmPHyre project
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
 * @license  All files are licensed under the MIT License.
 * @link     https://github.com/jhaagsma/emPHyre
 * @since    February 3, 2018
 */

namespace EmPHyre;

define('EMPHYRE_DIR', dirname(__FILE__) . '/');

//for now;
//eventually we should make this an external git package for EmPHyre
function eemphyre_autoloader($class)
{
    $emPHyre = 'EmPHyre\\';
    $len = strlen($emPHyre);
    if (strncmp($emPHyre, $class, $len) === 0) {
        //trigger_error($class);
        $relative_class = substr($class, $len);
        include_once EMPHYRE_DIR.$relative_class.'.class.php';
        return;
    }
}

spl_autoload_register('\EmPHyre\eemphyre_autoloader');
