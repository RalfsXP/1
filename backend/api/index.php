<?php

// Vercel entry point: hand every request to Laravel's front controller.
//
// Vercel runs this file as /api/index.php, so Symfony would treat "/api" as the
// app's base path and strip it from /api/* routes. Pretend we are being served
// from public/index.php at the web root, like on a normal server.
$_SERVER['SCRIPT_FILENAME'] = __DIR__.'/../public/index.php';
$_SERVER['SCRIPT_NAME'] = '/index.php';
$_SERVER['PHP_SELF'] = '/index.php';

require __DIR__.'/../public/index.php';
