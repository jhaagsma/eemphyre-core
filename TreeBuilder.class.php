<?php
/**
 *
 * TreeBuilder builds the tree for the routing object
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
 * @since    Feb 11, 2018
 */

namespace EmPHyre;

class TreeBuilder
{
    public static function makeNode($dir, $file, $function, $inputs, $auth, $skin, $ext, $json)
    {
        $node = [];

        if ($dir) {
            $node[0] = $dir;
        }

        $node[1] = $file;
        $node[2] = $function;


        ksort($inputs);

        if ($inputs) {
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

        if ($ext !== false) {
            $node[6] = $ext;
        }

        if ($json !== false) {
            $node[7] = $json;
        }

        return $node;
    }
}//end class
