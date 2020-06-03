<?php

use App\MyClient;

require_once ("vendor/autoload.php");


$config = include("config.php");

$myClient = new MyClient($config);

