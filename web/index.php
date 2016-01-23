<?php

$debug = true;

if ($debug)
{
	ini_set('display_errors', 1);
}

include_once dirname(__FILE__) . '/../vendor/autoload.php';

$config =  dirname(__FILE__) . '/../app/resources/config.php';
if (file_exists(dirname(__FILE__) . '/../app/resources/config.local.php'))
{
	$config =  dirname(__FILE__) . '/../app/resources/config.local.php';
}


use Redsys\Response;
use Redsys\Api\RedsysAPI;
$response = new Response(new RedsysAPI(), require $config);
$response->createFakeResponse('sincrona');