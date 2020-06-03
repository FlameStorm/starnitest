<?php

use App\MyServer;

require_once ("vendor/autoload.php");


$config = include("config.php");

$myServer = new MyServer($config);

