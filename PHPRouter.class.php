<?php
/**
 *
 * PHPRouter is the routing object for the EmPHyre project
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
 * @since    October 2009
 */

namespace EmPHyre;

class PHPRouter
{
    public $paths;
    //inherit everything other than the function
    private static $can_inherit = [0,1,4,5,6,7];

    public function __construct($filetime = false)
    {
        // trigger_error("{NODETAIL_IRC}Rebuilding Router Object for " . $_SERVER['SERVER_NAME']);
        // only for if you want Notices printed when the router is rebuilt
        $this->time = ($filetime ? $filetime : time());
        // so we know if it's fresh
        // $this->paths = array("GET" => new UriPart(), "POST" =>  new UriPart());
        $this->paths = ["GET"  => [], "POST" => [], "PUT" => [], "DELETE" => []];
        $this->clearDefaults();
    }//end __construct()


    public function clearDefaults()
    {
        $this->area             = array();
        $this->dir              = null;
        $this->auth             = null;
        $this->skin             = null;
        $this->get_inputs       = array();
        $this->post_inputs      = array();
        $this->common           = array();
        $this->path_extension   = false;
        $this->extractable_json = false;
    }//end clearDefaults()


    public function dirSet($dir = null)
    {
        $this->dir = rtrim($dir, '/');
    }//end dirSet()


    public function defaultAuth($auth = null)
    {
        $this->auth = $auth;
    }//end defaultAuth()


    public function defaultSkin($skin = null)
    {
        $this->skin = $skin;
    }//end defaultSkin()


    public function defaultGETInputs($inputs = array())
    {
        $this->get_inputs = $inputs;
    }//end defaultGETInputs()


    public function defaultPOSTInputs($inputs = array())
    {
        $this->post_inputs = $inputs;
    }//end defaultPOSTInputs()


    public function areaSet($area = null)
    {
        $this->area = array();
        if ($area) {
            $this->areaPush($area);
        }
    }//end areaSet()


    public function areaPush($area)
    {
        // this function is to allow me to avoid putting in /{server=>string}/
        // for like 200 entries in the registry
        $this->area = array_merge($this->area, explode('/', trim($area, '/')));
    }//end areaPush()


    public function areaPop()
    {
        array_pop($this->area);
    }//end areaPop()

    public function commonInputs($inputs = array())
    {
        $this->common = $inputs;
    }

    public function pathExtension($extension = false)
    {
        $this->path_extension = $extension;
    }

    public function extractableJson($extractable = false)
    {
        $this->extractable_json = $extractable;
    }


    // Object version --- these are TOO SLOW!  //Leave in until git & svn tree merge...
    /*
        function add($type, $url, $f, $u, $i = null, $a = null, $s=null){
        if(!$this->prepends)
            $this->prepends = array('');
        foreach($this->prepends as $pre){
            $uri_parts = explode('/', ltrim($pre . $url,'/'));
            $node = new PathNode($f, $u, $i, $a, $s); //maybe array
            $this->buildBranch($uri_parts, $this->paths[$type], $node);
        }
        }

        function buildBranch($uri_parts, &$r, $node){
        $current = array_shift($uri_parts);
        if(!$current){ //ie we've moved to the end of the url
            if(!$r->o){
                $r->o = $node;
                return false;
            }
            else
                trigger_error("Different node already set for this path!") &&
                 die("Different node already set for this path!");
        }

        if($vinfo = $this->isVariable($current)){
            if(!isset($r->v))
                $r->v = new VUriPart($vinfo[1], $vinfo[2]);
            elseif($r->v->n != $vinfo[1])
                //this must be 1 because isVariable returns 0 for matches 1 for name and 2 for type in {name=>type}
                trigger_error("Different variable already set for this path!") &&
                 die("Different variable already set for this path!");

            return $this->buildBranch($uri_parts, $r->v, $node);
        }
        else{
            if(!isset($r->s[$current]))
                $r->s[$current] = new UriPart();

            return $this->buildBranch($uri_parts, $r->s[$current], $node);
        }
    }*/

    /**
     * Add a RESTful API url adder
     *
     * @param  string  $url       The URL being accessed by various methods
     * @param  string  $file      The file name ofthe function to be called
     * @param  array   $functions An array of get=>fn1, post=>fn2, put=>fn3
     * @param  array   $inputs    The inputs expected
     * @param  bool|fn $auth      False|The auth function to be called
     * @param  bool|fn $skin      False|The skin function to be called
     *
     * @return null
     */
    public function rest($url, $file, $functions = [], $inputs = [], $auth = false, $skin = false)
    {
        $methods = ['GET', 'POST', 'PUT', 'DELETE'];
        // add a shorthand version
        foreach ($functions as $method => $function) {
            $m = array_search(strtoupper($method), $methods);
            $this->add($methods[$m], $url, $file, $function, $inputs, $auth, $skin);
        }
    }//end rest()

    public function get($url, $file, $function, $inputs = array(), $auth = false, $skin = false)
    {
        // add a shorthand version
        $this->add('GET', $url, $file, $function, $inputs, $auth, $skin);
    }//end get()

    public function post($url, $file, $function, $inputs = array(), $auth = false, $skin = false)
    {
        // add a shorthand version
        $this->add('POST', $url, $file, $function, $inputs, $auth, $skin);
    }//end post()

    public function add($type, $url, $file, $function, $inputs = array(), $auth = false, $skin = false)
    {
        // Testing out array version
        $uri_parts = array_merge($this->area, explode('/', ltrim($url, '/')));
        $url       = implode('/', $uri_parts);
        $dir       = ($this->dir && $file[0] != '.' ? $this->dir : false);
        $file      = ($this->dir && $file[0] != '.' ? ltrim($file, '/') : $file);
        //$node      = [0 => $file, 1 => $function,];

        if ($this->common) {
            $inputs = $inputs + $this->common;
        }

        $inputs = TypeValidator::compressInputs($inputs);

        $node = [];

        if ($dir) {
            $node[0] = $dir;
        }

        $node[1] = $file;
        $node[2] = $function;

        // maybe array
        if ($skin === false) {
            // in other words, if they supplied no auth
            // if they supplied null, it should still set it as null, even if the default is not
            $skin = $this->skin;
        }

        if ($auth === false) {
            $auth = $this->auth;
        }

        // this will overwrite defaults with $inputs
        if ($type == 'GET') {
            $inputs = $inputs == null ? $this->get_inputs : array_merge($this->get_inputs, $inputs);
        } elseif ($type == 'POST') {
            $inputs = $inputs == null ? $this->post_inputs : array_merge($this->post_inputs, $inputs);
        }

        if ($inputs !== array()) {
            ksort($inputs);
            $node[3] = $inputs;
        }

        if ($auth !== false) {
            $node[4] = $auth;
        }

        if ($skin !== false) {
            //for some reason this optimization just made it slower...
            //perhaps it would be more effective with a larger website
            /*if($skin === null)
                $key = 0;
            elseif(!$key = array_search($skin, $this->skins)){
                $this->skins[] = $skin;
                $key = array_search($skin,$this->skins);
            }
            $node[5] = $key;*/

            $node[5] = $skin;
        }

        if ($this->path_extension !== false) {
            $node[6] = $this->path_extension;
        }

        if ($this->extractable_json !== false) {
            $node[7] = $this->extractable_json;
        }

        $this->buildBranch($uri_parts, $this->paths[$type], $node, $url);
    }//end add()


    private function buildBranch($uri_parts, &$r, $node, $url, $inherit = false)
    {
        // comments on 'r': mapping
            // node aka o => 0
            // variable aka v =>1
            // static aka s =>2
            // name aka n => 3
            // type aka t => 4
        $current = array_shift($uri_parts);
        if (!$current) { // ie we've moved to the end of the url
            if (!isset($r[0])) {
                $node = $this->newNode($inherit, $node);
                $r[0] = $node;
                return false;
            } else {
                trigger_error("Ignoring Branch!: Different node already set for this path: $url");
                return;
            }
        }

        $inherit = $this->newInherit($inherit, (isset($r[0]) ? $r[0] : false));
        if ($vinfo = $this->isVariable($current)) {
            if (!isset($r[1])) {
                $r[1] = [3 => $vinfo[1], 4 => $vinfo[2]];
            } elseif ($r[1][3] != $vinfo[1]) {
                // this must be 1 because isVariable returns 0 for matches 1 for name and 2 for type in {name=>type}
                trigger_error("Ignoring Branch!: Different variable already set for this path: $url");
                return;
            }

            return $this->buildBranch($uri_parts, $r[1], $node, $url, $inherit);
        } else {
            if (!isset($r[2])) {
                $r[2] = [];
            }

            if (!isset($r[2][$current])) {
                $r[2][$current] = [];
            }

            return $this->buildBranch($uri_parts, $r[2][$current], $node, $url, $inherit);
        }//end if
    }//end buildBranch()


    private function isVariable($string)
    {
        preg_match("/{([A-z0-9]*)=>([A-z0-9]*)}/", $string, $matches);
        return $matches;
        // this returns empty array if nothing was matched
    }//end isVariable()


    // OBJECT VERSIONS: these are TOO SLOW!
    /*
        function urlRoute($s = array(), $r, &$path){
        $current = array_shift($s);
        if(!is_object($r))
            return false;
        elseif(!$current)
            return $r->o;
        elseif(isset($r->s[$current]))
            return $this->urlRoute($s, $r->s[$current], $path);
        elseif($r->v){
            $path->v[$r->v->n] = $this->validate(array($current),0,$r->v->t);
            return $this->urlRoute($s,$r->v,$path);
        }
        return false;
    }*/

    public function newInherit($inherit, $node)
    {
        // $can_inherit = array(0,1,4,5);

        foreach (self::$can_inherit as $a) {
            if ($node && array_key_exists($a, $node)) {
                $inherit[$a] = $node[$a];
            }
        }

        return $inherit;
    }

    public function newNode($inherit, $node)
    {
        if (!$inherit) {
            return $node;
        }

        foreach (self::$can_inherit as $a) {
            if (array_key_exists($a, $inherit) && array_key_exists($a, $node) && $inherit[$a] == $node[$a]) {
                unset($node[$a]);
            }
        }

        if (!array_key_exists(4, $node) && !array_key_exists(5, $node) && !$node[3]) {
            unset($node[3]);
        }

        ksort($node);
        return $node;
    }

    public function inheritNode($inherit, $node)
    {
        foreach (self::$can_inherit as $a) {
            if (!array_key_exists($a, $node) && array_key_exists($a, $inherit)) {
                $node[$a] = $inherit[$a];
            }
        }
        $file = $node[1];
        if ($file[0] == '.') {
            unset($node[0]);
        }

        return $node;
    }

    /**
     * I'll document this when I have time to go through it later...
     *
     * @param array $s    Not sure
     * @param ????? $r    Not sure
     * @param Path  $path A Path Node
     * @param array $inherit
     */
    private function urlRoute($s, $r, $path, $inherit = false)
    {
        // comments on 'r': mapping
            // node aka o => 0
            // variable aka v =>1
            // static aka s =>2
            // name aka n => 3
            // type aka t => 4
        $inherit = $this->newInherit($inherit, (isset($r[0]) ? $r[0] : false));
        $current = array_shift($s);
        if (!$current && !isset($r[0])) {
            return false;
        } elseif (($current === null || $current === "")) {
            return new PathNode($this->inheritNode($inherit, $r[0]));
        } elseif (isset($r[2][$current])) {
            return $this->urlRoute($s, $r[2][$current], $path, $inherit);
        } elseif (isset($r[1])) {
            $path->variables[$r[1][3]] = TypeValidator::validate(array($current), 0, $r[1][4]);
            return $this->urlRoute($s, $r[1], $path, $inherit);
        }

        return false;
    }//end urlRoute()

    private function getType()
    {
        $type = $_SERVER['REQUEST_METHOD'];
        if ($type != 'GET' && $type != 'POST') { //for now until we do HEAD and PUT versions of things
            $type = 'GET';
        }

        return $type;
    }

    private function extractJson($node)
    {
        $type = $this->getType();
        if ($type == 'GET') {
            if (!isset($_GET[$node->extractable_json])) {
                return;
            }

            $json = json_decode($_GET[$node->extractable_json]);
            if (!$json) {
                return;
            }

            foreach ($json as $key => $value) {
                $_GET[$key] = $value;
            }
        } elseif ($type == 'POST') {
            if (!isset($_POST[$node->extractable_json])) {
                return;
            }

            $json = json_decode($_POST[$node->extractable_json]);
            if (!$json) {
                return;
            }

            foreach ($json as $key => $value) {
                $_POST[$key] = $value;
            }
        }
    }

    public function route($url = null)
    {
        $type = $this->getType();
        $uri  = $_SERVER['REQUEST_URI'];

        $url = ($url ? array($url) : explode('?', $uri, 2));

        $path = new Path($url = rtrim($url[0], '/'));
        $s = explode('/', ltrim($path->url, '/'));

        $data = array();

        if (!isset($this->paths)) {
            trigger_error($_SERVER['REQUEST_METHOD'] . ': ' . $_SERVER['SERVER_NAME'] . ' ' . $_SERVER['REQUEST_URI']);
        }

        $node = $this->urlRoute($s, $this->paths[$type], $path);

        if (!$node) {
            return new Route(false, 'fourohfour', $data, $path, false);
        }

        //basically this lets people set everything up as a single json variable,
        //like api_payload, and we extract it as though it were in the POST or GET
        if ($node->extractable_json) {
            $this->extractJson($node);
        }


        if (is_array($node->inputs)) {
            $source = ($type == "GET" ? $_GET : $_POST);

            foreach ($node->inputs as $k => $v) {
                $data[$k] = TypeValidator::validate($source, $k, $v);
            }
        }

        //basically this lets us set a variable, like api_function, at say /ai/
        //and then automagically put us to /ai/cash or /ai/explore, based on input
        if ($node->path_extension && isset($source[$node->path_extension])) {
            return $this->route($url . '/' . $source[$node->path_extension]);
        }

        $path->skin = $node->skin;
        return new Route($node->file, $node->function, $data, $path, $node->auth);
    }//end route()

    public static function optimize()
    {
 //this is now much faster! serialize was key
        $this->optimize = 1;
        global $cache;
        foreach ($this->paths as $type => $tree) {
            $cache->json_store(ROUTER_PREFIX . $type, $this->paths[$type], 86400*3);
        }

        unset($this->paths);

        //$cache->store('r:' . $_SERVER['HTTP_HOST'] . ':sk',serialize($this->skins),86400*2);

        //unset($this->skins);
        unset($this->area);
        unset($this->dir);
        unset($this->skin);
        unset($this->auth);
    }

    public static function partialReconstruct($type)
    {
        global $cache;
        $branch = $cache->json_fetch(ROUTER_PREFIX . $type);

        if (!$branch) {
            $cache->delete(ROUTER_PREFIX . $this->optimize);
            trigger_error(ROUTER_PREFIX .': Branch for ' . $type . ' not set; deleting cached router for ' . $_SERVER['SERVER_NAME']); //error handling now :)
            $this->bad = true;
            return;
        }

        $this->paths = array($type=>$branch);
        //$this->skins = unserialize($cache->fetch('r:' . $_SERVER['HTTP_HOST'] . ':sk'));
    }

    public function optimize2()
    {
        $this->optimize = 2;
        $this->s_paths = serialize($this->paths);
        unset($this->paths);
    }

    public function reconstruct2()
    {
        $this->paths = unserialize($this->s_paths);
        unset($this->s_paths);
    }

    public function requiresReconstruction()
    {
        if ($this->optimize == 1) { //ie, if optimize is run
            $this->partialReconstruct($this->get_type());
        } elseif ($this->optimize == 2) { //ie, if optimize is run
            $this->reconstruct2();
        }

        return $this->bad;
    }
}//end class

function def(&$var, $def)
{
    return (isset($var) ? $var : $def);
}//end def()
