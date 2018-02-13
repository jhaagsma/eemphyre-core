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

define('EMPHYRE_DIR', dirname(__FILE__) . '/');

//I thought maybe this was adding the extra 0.3ms,
//but it seems to come from something else; maybe even just using autoloader
//We're borrowing ideas/code heavily from the PSR-4 docs
class Autoloader
{
    protected static $prefixes = array();
    protected static $extensions = array();

    // public function __construct()
    // {
    //     //trigger_error("Registering?");
    //     spl_autoload_register(array($this, 'loadClass'));
    // }

    public static function register()
    {
        spl_autoload_register('self::loadClass');
    }

    /**
     * Adds a base directory for a namespace prefix.
     *
     * @param string $prefix The namespace prefix.
     * @param string $base_dir A base directory for class files in the
     * namespace.
     * @param bool $prepend If true, prepend the base directory to the stack
     * instead of appending it; this causes it to be searched first rather
     * than last.
     * @return void
     */
    public static function addNamespace($prefix, $base_dir, $prepend = false, $ext = '.php')
    {
        // normalize namespace prefix
        $prefix = trim($prefix, '\\') . '\\';

        // normalize the base directory with a trailing separator
        $base_dir = rtrim($base_dir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

        //trigger_error("Registering $prefix to $base_dir");

        // initialize the namespace prefix array
        if (isset(self::$prefixes[$prefix]) === false) {
            self::$prefixes[$prefix] = array();
            self::$extensions[$prefix] = array();
        }

        // retain the base directory for the namespace prefix
        if ($prepend) {
            array_unshift(self::$prefixes[$prefix], $base_dir);
            array_unshift(self::$extensions[$prefix], $ext);
        } else {
            array_push(self::$prefixes[$prefix], $base_dir);
            array_push(self::$extensions[$prefix], $ext);
        }

        //trigger_error("Prefixes ".str_replace("\n", null, var_export(self::$prefixes, true)));
        //trigger_error("Extensions ".str_replace("\n", null, var_export(self::$extensions, true)));
    }

    /**
     * Loads the class file for a given class name.
     *
     * @param string $class The fully-qualified class name.
     * @return mixed The mapped file name on success, or boolean false on
     * failure.
     */
    public static function loadClass($class)
    {
        // the current namespace prefix
        $prefix = $class;
        //trigger_error("Trying to load $class");

        // work backwards through the namespace names of the fully-qualified
        // class name to find a mapped file name
        while (false !== $pos = strrpos($prefix, '\\')) {
            // retain the trailing namespace separator in the prefix
            $prefix = substr($class, 0, $pos + 1);

            // the rest is the relative class name
            $relative_class = substr($class, $pos + 1);

            //trigger_error("Trying to load $prefix-$relative_class");

            // try to load a mapped file for the prefix and relative class
            $mapped_file = self::loadMappedFile($prefix, $relative_class);

            //trigger_error("Mapping: $mapped_file");

            if ($mapped_file) {
                //trigger_error("Trying to load $prefix $relative_class from $mapped_file");
                return $mapped_file;
            }

            // remove the trailing namespace separator for the next iteration
            // of strrpos()
            $prefix = rtrim($prefix, '\\');
        }

        // never found a mapped file
        return false;
    }

    /**
     * Load the mapped file for a namespace prefix and relative class.
     *
     * @param string $prefix The namespace prefix.
     * @param string $relative_class The relative class name.
     * @return mixed Boolean false if no mapped file can be loaded, or the
     * name of the mapped file that was loaded.
     */
    protected static function loadMappedFile($prefix, $relative_class)
    {
        // are there any base directories for this namespace prefix?
        if (isset(self::$prefixes[$prefix]) === false) {
            return false;
        }

        //trigger_error("Extension for $prefix: $ext");
        //trigger_error("Ext: ".str_replace("\n", null, var_export($ext, true)));


        // look through base directories for this namespace prefix
        foreach (self::$prefixes[$prefix] as $index => $base_dir) {
            // replace the namespace prefix with the base directory,
            // replace namespace separators with directory separators
            // in the relative class name, append with .php

            $ext = self::$extensions[$prefix][$index];
            $file = $base_dir
                  . str_replace('\\', '/', $relative_class)
                  . $ext;

            //trigger_error("Trying to load $file");

            // if the mapped file exists, require it
            if (self::requireFile($file)) {
                // yes, we're done
                return $file;
            }
        }

        // never found it
        return false;
    }

    /**
     * If a file exists, require it from the file system.
     *
     * @param string $file The file to require.
     * @return bool True if the file exists, false if not.
     */
    protected static function requireFile($file)
    {
        if (file_exists($file)) {
            require $file;
            return true;
        }
        return false;
    }
}
