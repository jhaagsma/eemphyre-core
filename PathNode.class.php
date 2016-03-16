<?php
/**
 * PathNode is the node for the routing object
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
 * @since    Pulled out of PHPRouter.class.php 2016-03-15
 */

namespace EmPHyre;

class PathNode
{
    //shortening names to speed up APC storage and retrieval //debug test
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
    public function __construct($r)
    {
        $this->file = $r['0'];
        $this->function = $r['1'];
        $this->inputs = def($r['2'], null);
        $this->auth = def($r['3'], null);
        $this->skin = def($r['4'], null);
    }
}
