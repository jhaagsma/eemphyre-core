<?php
/**
 * Path is the path information holder for the routing object
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

class Path
{
    public $url;
    public $skin;
    public $page_title;
    public $meta_desc;
    public $language;
    public $variables;

    /**
     * The construct
     *
     * @param string $url The url we're at
     */
    public function __construct($url)
    {
        $this->url        = $url;
        $this->skin       = null;
        $this->page_title = null;
        $this->meta_desc  = null;
        $this->language   = null;
        $this->variables  = [];
    }//end __construct()

    /**
     * Get a variable from the path
     *
     * @param string $variable The variable to get
     *
     * @return variable
     */
    public function getVar($variable)
    {
        return $this->variables[$variable];
    }//end getVar()
}//end class
