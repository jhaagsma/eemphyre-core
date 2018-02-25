<?php
/**
 *
 * TypeValidator handles type validation for the routing object
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
 * @since    Feb 3, 2018
 */

namespace EmPHyre;

class TypeValidator
{

    /**
     * This shortens type names to single letters
     * @param  string $inputs The type to cast inputs to
     * @return char           The short form of the type
     */
    public static function compressInputs($inputs)
    {
        //just compress the first layer basics for now
        if (!$inputs) {
            return $inputs;
        }

        foreach ($inputs as $name => $type) {
            if (!isset($type)) {
                continue;
            }

            $inputs[$name] = self::compressInput($type);
        }

        return $inputs;
    }//end compressInputs()

    /**
     * shorten the name in order to compress better
     *
     * @param  string $type The type
     *
     * @return string       Single-letter representation of the type
     */
    public static function compressInput($type)
    {

        switch ($type) {
            case 'array':
                return 'a';
            case 'bool':
            case 'boolean':
                return 'b';
            case 'double':
                return 'd';
            case 'float':
                return 'f';
            case 'int':
            case 'integer':
                return 'i';
            case 'string':
                return 's';
            case 'u_int':
                return 'u';
            default:
                return $type;
        }
    }//end compressInput()

    /**
     * This is a list of aliases of pre-defined types, for ease of defining in
     * the registries
     *
     * @param  string $type The alias of the type we want
     *
     * @return mixed        The actual type definition
     */
    public static function typeAlias($type)
    {
        if (is_array($type)) {
            if ($type[0] == 'a') {
                $type[0] = 'array';
            }
        }

        // type aliases
        switch ($type) {
            case 'a':
                $type = 'array';
                break;
            case 'a1Dbu':
            case 'arr1D_bool_uint':
                // var ......TYPE...DEFAULT..VALUES..INDEX
                $type = ['a', false, 'b', 'u',];
                break;
            case 'a1Dss':
            case 'arr1D_str_str':
                $type = ['a', null, 's', 's',];
                break;
            case 'a1Dsu':
                $type = ['a', false, 's', 'u'];
                break;
            case 'a2Dbu':
            case 'arr2D_bool_uint':
                $type = ['a', [], ['a', false, 'b', 'u',], 'u',];
                break;
            case 'a2Dbs':
                $type = ['a', [], ['a', false, 'b', 's',], 's',];
                break;
            case 'a2Dss':
            case 'arr2D_str_str':
                $type = ['a', [], ['a', null, 's', 's',], 's',];
                break;
            case 'b':
                $type = 'bool';
                break;
            case 'd':
                $type = 'double';
                break;
            case 'f':
                $type = 'float';
                break;
            case 'i':
                $type = 'int';
                break;
            case 's':
            case 'str':
                $type = 'string';
                break;
            case 'u':
                $type = 'u_int';
                break;
            default:
                // do nothing;
        }//end switch

        /*
            Added true multi-dim functionality
            Types Must be specified in the following manner (variable_name => type)
                //variable name with type
                'testuint'=>'u_int'

                //variable name with type and default
                'testuint'=>array('u_int',1337)

                //one dimensional array with default for base layer and type
                'testarray2'=>array('array',null,'int'),
                'testarray2a'=>array('array',13,'int'),

                //one dimensional array with default for base layer and type and type for array keys
                'testarray2'=>array('array',null,'int','u_int'),
                'testarray2a'=>array('array',13,'int','int'),

                //one dimensional array with default for base layer and type with default for array elements
                'testarray2b'=>array('array',null,array('int',136)),

                //two dimensoinal array with default bool for top layer, and key validation for both layers
                'countries'=>array('array', false, array('array', false, 'bool', 'u_int'), 'u_int')

                //two dimensional array with default for base layer
                 //and second layer and type for array elements WITHOUT default for array elements
                'testarray3'=>array('array',null,array('array',3,'int')),
                //two dimensional array with default for base layer
                 //and second layer and type for array elements with default for array elements
                'testarray3b'=>array('array',null,array('array',3,array('int',136))),

                //these can be extended to further dimensions as following (a four dimensional array in this case)
                'testarray4'=>array('array',null,array('array',null,array('array',null,array('array',3,'int'))))
        */
        return $type;
    }//end typeAlias()

    /**
     * This function validates incoming data to the types laid out in the registry
     *
     * @param Array  $source GET/POST/etc
     * @param string $key    The key of the incoming field
     * @param string $type   The type the incoming value is supposed to be
     * @param si     $si     Whether or not we allow 10k => 10000
     *
     * @return mixed          A value set to the type "$type"
     */
    public static function validate($source, $key, $type, $si = true)
    {
        //trigger_error("Source: ".str_replace("\n", "", var_export($source, true)).", Key: $key, Type: $type");

        $type = self::typeAlias($type);

        $default = $innertype = $keytype = null;
        if (is_array($type)) {
            $default = (isset($type[1]) ? $type[1] : null);
            if (($type[0] == 'a' || $type[0] == 'array') && isset($type[2])) {
                if (is_array($type[2])) {
                    $passarray = $type[2];
                } else {
                    $innertype = $type[2];
                }

                if (isset($type[3])) {
                    $keytype = $type[3];
                }
            }

            $type = self::typeAlias($type[0]);
        }

        switch ($type) {
            case "u_int":
                if (isset($source[$key]) && $source[$key] !== "") {
                    $ret = $source[$key];

                    if ($si == true && !is_numeric($ret)) {
                        $ret = self::doSiPrefixes($ret);
                        // make k's into 000's and m's into 000000
                    }

                    if (settype($ret, 'int')) {
                        if ($ret < 0) {
                            return 0;
                        }

                        return $ret;
                    }
                }

                settype($default, "int");
                return $default;

            case "int":
            case "integer":
            case "bool":
            case "boolean":
            case "float":
            case "double":
            case "string":
                if (isset($source[$key]) && $source[$key] !== "") {
                    $ret = $source[$key];

                    if (settype($ret, $type)) {
                        return $ret;
                    }
                }

                settype($default, $type);
                return $default;

            case "array":
                if (isset($source[$key])) {
                    $ret = $source[$key];

                    if (settype($ret, 'array')) {
                        // iff type is set as an array
                        // eg 'countries'=>array('array', 0, 'int') )
                        // with type array (which is how we got here) default,
                        // and internal type
                        foreach (array_keys($ret) as $k) {
                            if ($keytype) {
                                // validate the keys as well --
                                // have to store the data in $temp while we rewrite the key
                                $temp = $ret[$k];
                                unset($ret[$k]);

                                // this is kindof hack-ish,
                                // but I only just ran into wanting to validate the keys as well
                                $k       = self::validate([0 => $k], 0, $keytype);
                                $ret[$k] = $temp;
                            }

                            $ret[$k] = self::validate($ret, $k, (isset($passarray) ? $passarray : $innertype));
                            if ($ret[$k] === null) {
                                unset($ret[$k]);
                            }
                        }

                        return $ret;
                    }//end if
                }//end if
                return $default ?? [];

            case "file":
                return $_FILES[$key] ?? null;

            default:
                $noTypeErr = "\n<br />Are you passing an array without specifying an inner default?";
                trigger_error("Unknown validation type: '$type'\n".($type ? null : $noTypeErr));
                return 'string';
        }//end switch
    }//end validate()

    /**
     * Change mixed numString values like 10k to values like 10000
     *
     * @param  string $ret String to interpret
     *
     * @return float       The interpreted string
     */
    private static function doSiPrefixes($ret)
    {
        // make k's into 000's and m's into 000000
        $ret = str_replace(",", "", $ret);
        $k   = (substr_count($ret, "k") + substr_count($ret, "K"));
        $m   = (substr_count($ret, "m") + substr_count($ret, "M"));
        $ret = str_ireplace("k", "", $ret);
        $ret = str_ireplace("m", "", $ret);
        $ret = ((float)$ret * (pow(1000, $k)));
        $ret = ((float)$ret * (pow(1000000, $m)));
        return (float)$ret;
    }//end doSiPrefixes()
}//end class
