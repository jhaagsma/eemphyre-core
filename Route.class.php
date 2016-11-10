<?php
/**
 * Route is the route information holder for the routing object
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

class Route
{
    public $file;
    public $function;
    public $path;
    public $auth;
    public $data;

    public function __construct($file, $function, $data, $path = null, $auth = null)
    {
        $this->file = $file;
        $this->function = $function;
        $this->data = $data;
        $this->path = $path;
        $this->auth = $auth;
    }
}
