<?php
// Test bootstrap: load the framework the same way apps do (Autoloader.php defines EMPHYRE_DIR).
require dirname(__DIR__) . '/Autoloader.php';
\EmPHyre\Autoloader::register();

// CLI has no APCu: in-memory stand-ins so classes using \EmPHyre\Cache can be tested.
if (!function_exists('apcu_fetch')) {
    $GLOBALS['__apcu'] = [];
    function apcu_fetch($key, &$success = null)
    {
        $success = array_key_exists($key, $GLOBALS['__apcu']);
        return $success ? $GLOBALS['__apcu'][$key] : false;
    }
    function apcu_store($key, $var, $ttl = 0)
    {
        $GLOBALS['__apcu'][$key] = $var;
        return true;
    }
    function apcu_delete($key)
    {
        $had = array_key_exists($key, $GLOBALS['__apcu']);
        unset($GLOBALS['__apcu'][$key]);
        return $had;
    }
}
require __DIR__ . '/Fakes/FakeDb.php';
