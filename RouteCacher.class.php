<?php
/**
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
 *
 * @package  EmPHyre
 *
 * @author   Julian Haagsma <jhaagsma@gmail.com>
 *
 * @license  All files are licensed under the MIT License.
 *
 * @link     https://github.com/jhaagsma/emPHyre
 *
 * @since    February 3, 2018
 */

namespace EmPHyre;

defined('ROUTER_PREFIX') or define("ROUTER_PREFIX", 'R:');
defined('ROUTER_NAME') or define("ROUTER_NAME", ROUTER_PREFIX . getenv('HTTP_HOST') . ':');

/**
 * This cache builds, stores, and fetches the Router object
 *
 * @category Router
 * @package  Emphyre
 * @author   Julian Haagsma <jhaagsma@gmail.com>
 * @license  All files are licensed under the MIT License.
 * @link     https://github.com/jhaagsma/emPHyre
 */
class RouteCacher
{
    private static $registries = [];

    /**
     * Grab the router from the registry, or build it
     *
     * @param array   $add_registries The list of registries to append
     * @param integer $optimization   Which optimization to use
     *
     * @return Router               The routing object
     */
    public static function getRouter($add_registries = [], $optimization = 0)
    {
        //0 for no optimization, //this is almost exactly the same speed as 2
        //1 for json cut into two APC bits, //this TAKES TWICE AS LONG as 0 or 2
        //2 for serialize, not cut up; //this is almost exactly the same speed as 0

        //$optimization = (time() % 2) * 2;

        //so far 1 is SLOWEST BY FAR
        self::$registries = array_merge(self::$registries, $add_registries);
        $filetime         = filemtime(dirname(__FILE__) . '/Router.class.php'); //the actual router object file
        $thistime         = filemtime(dirname(__FILE__) .  '/RouteCacher.class.php'); //the actual router object file
        $filetime         = max($filetime, $thistime);
        foreach (self::$registries as $r) {
            //see if any registries have been updated
            $filetime = max($filetime, filemtime($r));
        }
        //global $profiler, $time_start;
        //$profiler['done_r'] = codetime($time_start, true);

        $router = Cache::serialFetch(ROUTER_NAME.$optimization);
        if (!$router || $router->time < $filetime || $optimization && self::requiresReconstruction($router)) {
            //registries file time
            //requires_reconstruction actually pieces it back together!!

            // if (!$router) {
            //     trigger_error("NOT ROUTER");
            // } elseif ($router->time < $filetime) {
            //     trigger_error("OLD ROUTER");
            // } elseif ($recon) {
            //     trigger_error("ROUTER RECONSTRUCTION REQUIRED");
            // }

            $router = new Router($filetime);

            foreach (self::$registries as $r) {
                include_once $r;
            }

            unset($router->area);
            unset($router->dir);
            unset($router->skin);
            unset($router->auth);
            unset($router->path_extension);
            unset($router->extractable_json);
            unset($router->auth);
            unset($router->get_inputs);
            unset($router->post_inputs);
            unset($router->common);

            if ($optimization == 1) {
                self::optimize1($router);
            } elseif ($optimization == 2) {
                self::optimize2($router);
            }

            Cache::serialStore(ROUTER_NAME.$optimization, $router, 86400 * 2);
            self::requiresReconstruction($router); //MUST BE AFTER STORE SO WE DON'T DUPLICATE DATA IN THE CACHE!!!
            $router->reconstructed = true;
        }

        return $router;
    }//end getRouter()

    /**
     * The first optimization type
     * This caches each branch as a json
     * then unsets the branches
     *
     * @param Router $router The PHP Routing object
     *
     * @return Router         The PHP Routing object
     */
    public static function optimize1(&$router)
    {
        $router->optimize = 1;
        foreach (array_keys($router->paths) as $type) {
            Cache::jsonStore(ROUTER_NAME . $type, $router->paths[$type], 86400 * 3);
            //trigger_error("STORE: " . ROUTER_NAME . $type);
        }

        unset($router->paths);
    }//end optimize1()

    /**
     * Reconstruct the router of optimization type 1
     *
     * @param Router $router The routing object
     *
     * @return boolean           If it wasc changed
     */
    public static function partialReconstruct(&$router)
    {
        $type   = $router->getType();
        $branch = Cache::jsonFetch(ROUTER_NAME . $type);

        //trigger_error("FETCH: ". ROUTER_NAME . $type);

        if (!$branch) {
            Cache::delete(ROUTER_NAME . $router->optimize);
            //error handling now :)
            //trigger_error(ROUTER_NAME .': Branch for ' . $type . ' not set;
            //deleting cached router for ' . $_SERVER['SERVER_NAME']);

            return false;
        }

        //echo "dBug3";
        //new dBug($router);

        $router->paths = [$type => $branch];

        //echo "dBug4";
        //new dBug($router);

        return true;
    }//end partialReconstruct()

    /**
     * Optimize of type 2
     * This serializes the branches before caching
     *
     * @param Router $router The routing object
     *
     * @return null
     */
    public static function optimize2(&$router)
    {
        //this is now much faster! serialize was key
        $router->optimize = 2;
        $router->s_paths  = serialize($router->paths);
        //trigger_error("Serialize Paths");
        unset($router->paths);
    }//end optimize2()

    /**
     * This reconstructs the serialized type
     *
     * @param Router $router The router
     *
     * @return null
     */
    public static function reconstruct2(&$router)
    {
        $router->paths = unserialize($router->s_paths);
        //trigger_error("Unserialize Paths");
        unset($router->s_paths);
    }//end reconstruct2()

    /**
     * This determines if reconstruction is required
     * and rebuilds if it does
     *
     * @param Router $router The routing object
     *
     * @return bool Whether or not it needs to be rebuilt
     */
    public static function requiresReconstruction(&$router)
    {
        if ($router->optimize == 1) { //ie, if optimize is run
            return !self::partialReconstruct($router);
        } elseif ($router->optimize == 2) { //ie, if optimize is run
            self::reconstruct2($router);
        }

        return false;
    }//end requiresReconstruction()

    /**
     * Add a registry to the list
     *
     * @param filepath $registry The path to the registry
     *
     * @return null
     */
    public static function addRegistry(string $registry)
    {
        self::$registries[] = $registry;
    }//end addRegistry()
}//end class
