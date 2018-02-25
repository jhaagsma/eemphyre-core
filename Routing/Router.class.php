<?php
/**
 *
 * Router is the routing object for the EmPHyre project
 *
 * PHP version 7.2
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

class Router
{
    public $paths;
    public $optimize = 0;
    //inherit everything other than the function
    // 0 => dir (string)
    // 1 => file (string)
    // 2 => function (string)
    // 3 => variables (array)
    // 4 => auth (string)
    // 5 => skin (string)
    // 6 => path extension (for json? string)
    // 7 => extractable json (for json; string)
    private static $can_inherit = [0,1,4,5,6,7];

    /**
     * Create the Router object
     *
     * @param bool|int $filetime The time the router object was first created;
     *                           This allows us to rebuild it, but still know
     *                           when it was rebuilt.
     */
    public function __construct($filetime = false)
    {
        // trigger_error("{NODETAIL_IRC}Rebuilding Router Object for " . $_SERVER['SERVER_NAME']);
        // only for if you want Notices printed when the router is rebuilt
        // so we know if it's fresh
        $this->time = ($filetime ? $filetime : time());

        // remove HEAD, OPTIONS because we probably don't need to suppor those
        $this->paths = ['GET'  => [], 'POST' => [], 'PUT' => [], 'DELETE' => []]; //'HEAD' => [], 'OPTIONS' => []
        //clear/set defaults
        $this->clearDefaults();
    }//end __construct()


    /**
     * Clear the default values
     *
     * @return null
     */
    public function clearDefaults()
    {
        $this->area             = [];
        $this->dir              = null;
        $this->auth             = null;
        $this->skin             = null;
        $this->get_inputs       = [];
        $this->post_inputs      = [];
        $this->common           = [];
        $this->path_extension   = false;
        $this->extractable_json = false;
    }//end clearDefaults()


    /**
     * This allows setting of a default directory when adding paths
     *
     * @param  string $dir A directory path
     *                     For example: "./public"
     *
     * @return null
     */
    public function dirSet($dir = null)
    {
        $this->dir = rtrim($dir, '/');
    }//end dirSet()

    /**
     * This allows setting of a default authentication type when adding paths
     *
     * @param  string $auth An authentication type
     *                      For example: "auth_user"
     *
     * @return null
     */
    public function defaultAuth($auth = null)
    {
        $this->auth = $auth;
    }//end defaultAuth()


    /**
     * This allows setting of a default skin type when adding paths
     *
     * @param  string $skin A a skin type
     *                      For example: "web_public"
     *
     * @return null
     */
    public function defaultSkin($skin = null)
    {
        $this->skin = $skin;
    }//end defaultSkin()


    public function defaultGETInputs($inputs = [])
    {
        $this->get_inputs = $inputs;
    }//end defaultGETInputs()


    public function defaultPOSTInputs($inputs = [])
    {
        $this->post_inputs = $inputs;
    }//end defaultPOSTInputs()


    public function areaSet($area = null)
    {
        $this->area = [];
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

    public function commonInputs($inputs = [])
    {
        $this->common = $inputs;
    }//end commonInputs()


    public function pathExtension($extension = false)
    {
        $this->path_extension = $extension;
    }//end pathExtension()


    public function extractableJson($extractable = false)
    {
        $this->extractable_json = $extractable;
    }//end extractableJson()



    // Object version --- these are TOO SLOW!
    // //Leave in until git & svn tree merge...
    // //Or apparently much later;
    // I still kindof like the idea, but we'd have to re-try it in php 7.2
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

    public function get($url, $file, $function, $inputs = [], $auth = false, $skin = false)
    {
        // add a shorthand version
        $this->add('GET', $url, $file, $function, $inputs, $auth, $skin);
    }//end get()

    public function post($url, $file, $function, $inputs = [], $auth = false, $skin = false)
    {
        // add a shorthand version
        $this->add('POST', $url, $file, $function, $inputs, $auth, $skin);
    }//end post()

    public function add($type, $url, $file, $function, $inputs = [], $auth = false, $skin = false)
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

        // this will overwrite defaults with $inputs
        if ($type == 'GET') {
            $inputs = $inputs == null ? $this->get_inputs : array_merge($this->get_inputs, $inputs);
        } elseif ($type == 'POST') {
            $inputs = $inputs == null ? $this->post_inputs : array_merge($this->post_inputs, $inputs);
        }

        $inputs = TypeValidator::compressInputs($inputs);

        // in other words, if they supplied no auth
        // if they supplied null, it should still set it as null, even if the default is not
        $node = TreeBuilder::makeNode(
            $dir,
            $file,
            $function,
            $inputs,
            $auth === false ? $this->auth : $auth,
            $skin === false ? $this->skin : $skin,
            $this->path_extension,
            $this->extractable_json
        );

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

        //Iterate through the parts of the uri
        $current = array_shift($uri_parts);
        if (!$current) { // ie we've moved to the end of the url
            if (!isset($r[0])) {
                $node = $this->newNode($inherit, $node);
                $r[0] = $node;

                ksort($r);

                return false;
            } else {
                trigger_error("Ignoring Branch!: Different node already set for this path: $url");
                return;
            }
        }

        //grab all the things that we could potentially inherit
        $inherit = $this->newInherit($inherit, (isset($r[0]) ? $r[0] : false));

        //check if the current URI bit is a {variable=>type}
        if (($vinfo = $this->isVariable($current)) == true) { //have to encapsulate in brackets
            if (!isset($r[1])) {
                //split up into variable and type, and compress the input
                $r[1] = [3 => $vinfo[1], 4 => TypeValidator::compressInput($vinfo[2])];
            } elseif ($r[1][3] != $vinfo[1]) {
                //check if a {variable=>type} has already been set for this path
                //must be vinfo[1] because isVariable returns 0 for matches 1 for name and 2 for type in {name=>type}
                trigger_error("Ignoring Branch!: Different variable already set for this path: $url");
                return;
            }
            ksort($r);
            //ksort($r[1]);
            //build a branch, in the variable type
            return $this->buildBranch($uri_parts, $r[1], $node, $url, $inherit);
        } else {
            if (!isset($r[2])) {
                //if the static node isn't set, set it
                $r[2] = [];
            }

            if (!isset($r[2][$current])) {
                $r[2][$current] = [];
            }

            ksort($r);
            //ksort($r[2]);
            //build a branch, in the static type
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

    /**
     * Inherit something if possible
     *
     * @param  array $inherit Current possible inheritances
     * @param  array $node    Current node values
     *
     * @return array          Possible inheritances
     */
    public function newInherit($inherit, $node)
    {
        // $can_inherit = array(0,1,4,5);
        if (!$node) {
            return $inherit;
        }

        foreach (self::$can_inherit as $a) {
            if (array_key_exists($a, $node)) {
                $inherit[$a] = $node[$a];
            }
        }

        return $inherit;
    }//end newInherit()


    private function newNode($inherit, $node)
    {
        if (!$inherit) {
            return $node;
        }

        foreach (self::$can_inherit as $a) {
            if (array_key_exists($a, $inherit) && array_key_exists($a, $node) && $inherit[$a] === $node[$a]) {
                unset($node[$a]);
            }
        }

        //i'm sure this was supposed to do something, but it was breaking things...
        //I believe it unset the variables if there was no auth or skin,
        //but i've changed how the variables are set,
        //so no variable key is set if there are no variables
        // if (!array_key_exists(4, $node) && !array_key_exists(5, $node) && !$node[3]) {
        //     unset($node[3]);
        // }

        ksort($node);
        return $node;
    }//end newNode()

    /**
     * Fill out things that can be inherited
     *
     * @param  array $inherit Things that can be inherited
     * @param  array $node    What is set for this node
     *
     * @return array          Merged values
     */
    private function inheritNode($inherit, $node)
    {
        foreach (self::$can_inherit as $a) {
            if (!array_key_exists($a, $node) && array_key_exists($a, $inherit)) {
                $node[$a] = $inherit[$a];
            }
        }

        //something about the . at the beginning of ./this/directory
        $file = $node[1];
        if ($file[0] == '.') {
            unset($node[0]);
        }

        return $node;
    }//end inheritNode()


    /**
     * I'll document this when I have time to go through it later...
     *
     * @param array $s       Not sure
     * @param ????? $r       Not sure
     * @param Path  $path    A Path Node
     * @param array $inherit Any things that it could inherit
     *
     * @return a PathNode, or a route, or false
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
            $path->variables[$r[1][3]] = TypeValidator::validate([$current], 0, $r[1][4], false);
            return $this->urlRoute($s, $r[1], $path, $inherit);
        }

        return false;
    }//end urlRoute()

    public function getType()
    {
        switch (getenv('REQUEST_METHOD')) {
            case 'GET':
                return 'GET';
            case 'POST':
                return 'POST';
            case 'PUT':
                return 'PUT';
            case 'DELETE':
                return 'DELETE';
            // case 'HEAD':
            //     return 'HEAD';
            // case 'OPTIONS':
            //     return 'OPTIONS';
            default:
                return 'GET';
        }
    }//end getType()


    private function getData()
    {
        switch ($this->getType()) {
            case 'GET':
                return $_GET;
            case 'POST':
                return $_POST;
            case 'PUT':
                return $this->getPUT();
            case 'DELETE':
                return [];
            // case 'HEAD':
            //     return [];
            // case 'OPTIONS':
            //     return [];
            default:
                return [];
        }
    }//end getData()


    private function getPUT()
    {
        parse_str(file_get_contents("php://input"), $PUT);
        return $PUT;
    }//end getPUT()


    private function extractJson($node)
    {
        $type = $this->getType();
        //$data = $this->getData();

        $extension = null;

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

            $extension = $_GET[$node->path_extension] ?? null;
            unset($_GET[$node->path_extension]);
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

            $extension = $_POST[$node->path_extension] ?? null;
            unset($_POST[$node->path_extension]);
        }

        unset($node->extractable_json);

        return $extension;
    }//end extractJson()


    /**
     * Do the actual routing!
     *
     * @param  string $url The url we are at
     *
     * @return object      Rediret or return a Route object
     */
    public function route($url = null)
    {
        $type = $this->getType();
        $uri  = getenv('REQUEST_URI');

        if (!isset($this->paths)) {
            trigger_error($type . ': ' . getenv('SERVER_NAME') . ' ' . $uri);
        }

        $url = ($url ? [$url] : explode('?', $uri, 2));

        $path = new Path($url = rtrim($url[0], '/'));
        $s    = explode('/', ltrim($path->url, '/'));

        $node = $this->urlRoute($s, $this->paths[$type], $path);

        $data = [];
        if (!$node) {
            return FourOhFour::notFound($data, $path);
        }

        //basically this lets people set everything up as a single json variable,
        //like api_payload, and we extract it as though it were in the POST or GET
        $extension = $node->extractable_json ? $this->extractJson($node) : null;

        if (is_array($node->inputs)) {
            $source = $this->getData();

            foreach ($node->inputs as $k => $v) {
                $data[$k] = TypeValidator::validate($source, $k, $v);
            }
        }

        //basically this lets us set a variable, like api_function, at say /ai/
        //and then automagically put us to /ai/cash or /ai/explore, based on input
        if ($extension) {
            unset($node->path_extension);
            unset($node->extractable_json);
            return $this->route($url . '/' . $extension);
        }

        $path->skin = $node->skin;
        return new Route($node->file, $node->function, $data, $path, $node->auth);
    }//end route()
}//end class
