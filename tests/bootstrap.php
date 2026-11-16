<?php
// Test bootstrap: load the framework the same way apps do (Autoloader.php defines EMPHYRE_DIR).
require dirname(__DIR__) . '/Autoloader.php';
\EmPHyre\Autoloader::register();
