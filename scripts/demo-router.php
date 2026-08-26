<?php

$publicPath = '/var/www/html/public';
chdir($publicPath);

$uri = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '');

if ($uri !== '/' && $uri !== '' && file_exists($publicPath.$uri) && ! is_dir($publicPath.$uri)) {
    return false;
}

require $publicPath.'/index.php';
