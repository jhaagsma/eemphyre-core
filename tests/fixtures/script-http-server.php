<?php
// Router for `php -S` in ScriptTest: a local HTTP server, so Script's curl calls are tested
// without the network. /status/NNN answers with that status, /slow sleeps, anything else
// echoes the method and the data it received.
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

if ($path === '/slow') {
    sleep(2);
}

if (preg_match('#^/status/([0-9]{3})$#', $path, $m)) {
    http_response_code((int)$m[1]);
    echo 'status '.$m[1];
    return true;
}

echo $_SERVER['REQUEST_METHOD'].' '.json_encode($_SERVER['REQUEST_METHOD'] === 'POST' ? $_POST : $_GET);
return true;
