<?php
/*
 * CLI commands for using encryption
 * Run the script with -gen flag to create an encryption key.
 *  
 */
include_once "gigyaCMS.php";

if ($argv[1] == "-e") {
    $encStr = GigyaCMS::enc($argv[2], $argv[3]);
    echo $encStr . PHP_EOL;
} elseif ($argv[1] == "-d") {
    $dec = GigyaCMS::decrypt($argv[2], $argv[3]);
    echo $dec . PHP_EOL;
} elseif ($argv[1] == "-gen") {
    $str = isset($argv[2]) ? $argv[2] : null;
    $key = GigyaCMS::genKeyFromString($str);
    echo $key . PHP_EOL;
}