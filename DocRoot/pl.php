<?php
//copyright 2016 C.D.Price. Licensed under Apache License, Version 2.0
//See license text at http://www.apache.org/licenses/LICENSE-2.0

if (session_id() == "") session_start();
if (!isset($_SESSION["_SITE_CONF"])) {echo "Hello,world!";exit;}
ini_set('include_path', implode(":",$_SESSION["_SITE_CONF"]["_INCLUDE"]).":".ini_get('include_path'));
//For testing purposes, comment out this include:
//require "main/service/show_parms.php";
require_once "version.php";
require_once "continue.php";
?>
