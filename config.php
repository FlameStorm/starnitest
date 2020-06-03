<?php
$config = [];

define("DEBUG_NONE", 0);
define("DEBUG_BASIC", 1);
define("DEBUG_MORE", 2);
define("DEBUG_FULL", 3);


$config["client"] = [];
$config["client"]["debug"] = DEBUG_MORE;
$config["client"]["timeLimit"] = 7*24*60*60;

//$config["client"]["domain"] = "starnitest";
//$config["client"]["localServerOrigin"] = "http://" . $config["client"]["domain"];
$config["client"]["srcServerUrl"] = "wss://mainnet.infura.io/ws/v3/59c1f36ddf844f79ad4750fe8041b77d";

$config["db"] = [
    "server" => "localhost",
    "user" => "starnitest",
    "password" => "BY81efX7uPkLnlbh",
    "dbname" => "starnitest",
    "charset" => "UTF8",
];

$config["server"] = [];
$config["server"]["debug"] = DEBUG_BASIC;
$config["server"]["port"] = 2020;
$config["server"]["wsUrl"] = "ws://127.0.0.1:" . $config["server"]["port"];
$config["server"]["broadcastInterval"] = 10; //sec

return $config;
