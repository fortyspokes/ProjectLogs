<?php
//copyright 2015-2016,2019 C.D.Price. Licensed under Apache License, Version 2.0
//See license text at http://www.apache.org/licenses/LICENSE-2.0

if (!isset($_SERVER["HTTPS"])){
    header("Location: https://".$_SERVER["HTTP_HOST"].$_SERVER["REQUEST_URI"]);
}

session_start(); //always restart the session, even if (particularly if) doing a browser reload
if (isset($_SESSION["_STATUS"])) { //save for re-started session & head
	$status = $_SESSION["_STATUS"];
} else {
	$status = 0;
}
$_SESSION = array();
session_destroy();
session_write_close();

if (isset($_GET['info'])) {
	phpinfo();
	exit;
}
session_start();
SiteConfig();
$_SESSION['REQUEST_TIME'] = $_SERVER['REQUEST_TIME']; //track timeout
$_SESSION["_STATUS"] = $status; //0=initial login;1=timed out;2=logged in;3=logged out;4=evicted

$mod = "!showtime.php";
require "pl.php"; //can't be require_once because on timeout, pl.php recalls index.php

function SiteConfig() {
	if (isset($_GET['user'])) {
		$_SESSION["user"] = $_GET["user"]; //preload user login
	}
	$root = explode("/",dirname($_SERVER['SCRIPT_FILENAME'])); //file system path
	while (TRUE) { //get the first "site_conf.php" we see going up the directory path
		$dir = implode("/",$root);
		if ($dir == "") {
			echo "Startup error - notify the system administrator";
			$dir->what(); //force a loggable error
			exit;
		} else if (file_exists($dir.'/'."site_conf.php")) {
			$config = parse_ini_file($dir.'/'."site_conf.php",FALSE);
			break;
		}
		array_pop($root); //go up a level and check again
	}
	$_SESSION["OUR_ROOT"] = implode("/",$root);
	if (isset($config["_MORE"])) {
		foreach ($config["_MORE"] as $more) {
			$more = $_SESSION["OUR_ROOT"]."/".$more;
			if (file_exists($more)) {
				$config = array_merge_recursive($config,parse_ini_file($more,FALSE));
			}		
		}
		unset($config["_MORE"]);
	}
	//Append OUR_ROOT to directory parms (those with "[ROOT]" prepended):
	foreach ($config as $name=>$parm) {
		if (is_array($parm)) {
			foreach ($parm as $key=>$subparm) {
				$parm[$key] = addROOT($subparm);
			}
			$config[$name] = $parm;
		} else {
			$config[$name] = addROOT($parm);
		}
	}
	$_SESSION["_SITE_CONF"] = $config;
	$offset = "/".substr($_SERVER["REQUEST_URI"],1,strrpos($_SERVER["REQUEST_URI"],"/"));
	$_SESSION["HOST"] = $_SERVER["HTTP_HOST"].$offset;
	$_SESSION["BUTLER"] = $offset."pl.php";
	$_SESSION["UserPermits"] = array();
	$_SESSION["STATE"] = array();
}
function addROOT($parm) {
	if (substr($parm,0,6) != "[ROOT]") return $parm;
	$root = explode("/",$_SESSION["OUR_ROOT"]);
	$dir = explode("/",substr($parm,6));
	while ($dir[0] == "..") {
		array_pop($root);
		array_shift($dir);
	}
	$dir = array_merge($root,$dir);
	return implode("/",$dir);
}
?>
