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

defined('ROUTER_PREFIX') or define("ROUTER_PREFIX", 'R:');
defined('ROUTER_NAME') or define("ROUTER_NAME", ROUTER_PREFIX . $_SERVER['HTTP_HOST'] . ':');

class RouteCacher
{
    public static function getRouter($registries, $optimization = 1)
    {
        //0 for no optimization,
        //1 for json cut into two APC bits,
        //2 for serialize, not cut up;
        //
        //so far 1 is fastest

        $filetime = filemtime(dirname(__FILE__) . '/PHPRouter.class.php'); //the actual router object file
        foreach ($registries as $r) {
            //see if any registries have been updated
            $filetime = max($filetime, filemtime($r));
        }

        $router = Cache::serialFetch(ROUTER_NAME.$optimization);
        if (!$router || $router->time < $filetime || $router->requires_reconstruction()) { //registries file time //requires_reconstruction actually does the reconstruction!
            $router = new PHPRouter($filetime);

            foreach ($registries as $r) {
                include_once($r);
            }

            if ($optimization == 1) {
                $router->optimize();
            } elseif ($optimization == 2) {
                $router->optimize2();
            }

            Cache::serialStore(ROUTER_NAME.$optimization, $router, 86400*2);
            $router->requires_reconstruction(); //MUST BE AFTER STORE SO WE DON'T DUPLICATE DATA IN THE CACHE!!!
        }
        return $router;
    }//end getRouter()
}//end class
