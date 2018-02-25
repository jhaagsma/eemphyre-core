<?php
/**
 * PathNode is the node for the routing object
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
 * @since    Pulled out of PHPRouter.class.php 2016-03-15
 */

namespace EmPHyre;

class PathNode //shortening names to speed up APC storage and retrieval //debug test
{
    public $file; //$file;
    public $function; //$function;
    public $inputs; //$inputs;
    public $auth; //$auth;
    public $skin; //$skin_name;

    /*function __construct($file, $function, $inputs = null, $auth = null, $skin = null){
        $this->file = $file;
        $this->function = $function;
        $this->inputs = $inputs;
        $this->auth = $auth;
        $this->skin = $skin;
    }*/

    /**
     * Make a new PathNode
     *
     * @param array $r The node information
     *
     * @return null
     */
    public function __construct($r)
    {
        $this->file             = (isset($r[0]) ? $r[0] . '/' . $r[1] : $r[1]);
        $this->function         = $r[2];
        $this->inputs           = $r[3] ?? null;
        $this->auth             = $r[4] ?? null;
        $this->skin             = $r[5] ?? null;
        $this->path_extension   = $r[6] ?? false;
        $this->extractable_json = $r[7] ?? false;
    }//end __construct()
}//end class
