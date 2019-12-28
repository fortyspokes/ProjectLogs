<?php
//copyright 2016,2019 C.D.Price. Licensed under Apache License, Version 2.0
//See license text at http://www.apache.org/licenses/LICENSE-2.0

if (session_id() == "") { //index.php will start the session first time
	session_start();
}
if (!isset($_SESSION["REQUEST_TIME"])) { //PHP session timeout
	$_SESSION["_STATUS"] = 1;
	echo "<html><head><script>\n";
	echo "top.location=\"https://".$_SERVER["HTTP_HOST"]."\"\n";
	echo "</script></head></html>\n";
	exit;
}
ini_set('include_path', implode(":",$_SESSION["_SITE_CONF"]["_INCLUDE"]).":".ini_get('include_path'));
if (($_SERVER["REQUEST_TIME"] - $_SESSION["REQUEST_TIME"]) < 1800) { //30 mins
	$_SESSION["REQUEST_TIME"] = $_SERVER["REQUEST_TIME"]; //re-start timer
	//For testing purposes, comment out this include:
	//require "main/service/show_parms.php";
	require_once "version.php";
	require_once "continue.php"; //put code out of DocRoot
} else {
	$_SESSION["_STATUS"] = 1;
	require_once "lib/reload.php";
	reload_top(); //does not return
}
?>
