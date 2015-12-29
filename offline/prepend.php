<?php
//copyright 2015 C.D.Price. Licensed under Apache License, Version 2.0
//See license text at http://www.apache.org/licenses/LICENSE-2.0
//must have a copy (or link) in every directory with a loadable page

function SiteConfig() {
	if (isset($_GET['user'])) {
		$_SESSION["user"] = $_GET["user"]; //preload user login
	}
	/* $path = explode("/",str_replace('\\','/',$_SERVER['SCRIPT_FILENAME'])); //Windows version */
	$root = explode("/",dirname($_SERVER['SCRIPT_FILENAME'])); //file system path
	while (TRUE) { //get the first "site_conf.php" we see going up the directory path
		$dir = implode("/",$root);
/*		if (($dir == "") || (substr($dir,-1) == ":")) { //Windows version */
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
	$offset = "";
	$redirect = "";
	if (isset($config["_REDIRECT"])) { //note: parms in _MORE & _INCLUDE files must be relative to this new path
		$redirect = explode("/",$config["_REDIRECT"]);
		while ($redirect[0] == "..") {
			$offset = "/".array_pop($root).$offset;
			array_shift($redirect);
		}
		$root = array_merge($root,$redirect);
		$redirect = implode("/",$redirect);
		if ($redirect != "") $redirect = "/".$redirect;
	}
	$config["_REDIRECT"] = $redirect;
	if (!isset($config["_OFFSET"]))
		$config["_OFFSET"] = $offset;
	$_SESSION["OUR_ROOT"] = implode("/",$root);
	if (isset($config["_MORE"])) {
		foreach ($config["_MORE"] as $more) {
			$more = $_SESSION["OUR_ROOT"]."/".$more;
			if (file_exists($more)) {
				$config = array_merge($config,parse_ini_file($more,FALSE));
			}		
		}
		unset($config["_MORE"]);
	}
	if (isset($config["_EXTENSIONS"])) {
		$extension = explode("/",$config["_EXTENSIONS"]);
		$root = explode("/",$_SESSION["OUR_ROOT"]);
		while ($extension[0] == "..") {
			array_pop($root);
			array_shift($extension);
		}
		$extension = array_merge($root,$extension);
		$config["_EXTENSIONS"] = implode("/",$extension);
	}
	if (isset($config["_INCLUDE"])) {
		foreach($config["_INCLUDE"] as &$value) {
			if (substr($value,0,1) != "/") { //add OUR_ROOT to "naked" includes
				$include = explode("/",$value);
				$root = explode("/",$_SESSION["OUR_ROOT"]);
				while ($include[0] == "..") {
					array_pop($root);
					array_shift($include);
				}
				$include = array_merge($root,$include);
				$value = implode("/",$include);
			}
		}
	}
	$_SESSION["_SITE_CONF"] = $config;
	$_SESSION["UserPermits"] = array();
//	$_SESSION["STATE"] = array("_MAIN"=>"dummy");
	$_SESSION["STATE"] = array();
}
function throw_the_bum_out($publicmsg=NULL, $privatemsg=NULL, $script=false) {
	ob_clean(); //remove any previous headers
	if (!is_null($privatemsg)) error_log($privatemsg.": ".$_SERVER['SCRIPT_NAME']);
	if (!is_null($publicmsg)) {
		$_SESSION["_EVICTED_"] = $publicmsg;
	}
	$protocol = "http"; if (isset($_SERVER["HTTPS"])) $protocol .= "s";
	if ($script) {
		echo "top.location=\"".$protocol."://".$_SERVER["HTTP_HOST"].
			$_SESSION["_SITE_CONF"]["_OFFSET"]."\";document.close();\n";
	} else {
		echo "<html><head><script>top.location=\"".$protocol."://".	$_SERVER["HTTP_HOST"].
			$_SESSION["_SITE_CONF"]["_OFFSET"]."\";document.close();</script></head></html>";
	}
	exit();
}

function errorButler($errno, $errstr, $errfile, $errline) {

	if ($_SESSION["_SITE_CONF"]["RUNLEVEL"] == 1) return false;

	error_log($errstr. " in ".$errfile." on line ".$errline);
	throw_the_bum_out("An error has occurred<br> please notify the system administrator",NULL);
	return true;
}

ob_start(); //if we 'throw_the_bum_out', need to prevent prior headers from being sent, so buffer up the output

if (session_id() == "") session_start();
if (!isset($_SESSION["_SITE_CONF"])) {
	SiteConfig();
}

error_reporting(-1);
if ($_SESSION["_SITE_CONF"]["RUNLEVEL"] == 1) {
	ini_set("display_errors","1");
	ini_set("display_startup_errors","1");
	ini_set("error_log",$_SESSION["OUR_ROOT"]."/script.log");
} else {
	ini_set("display_errors","0");
	ini_set("display_startup_errors","0");
	set_error_handler("errorButler");
}

/*ini_set('include_path', implode(";",$_SESSION["_SITE_CONF"]["_INCLUDE"]).";".ini_get('include_path')); Windows version */
ini_set('include_path', implode(":",$_SESSION["_SITE_CONF"]["_INCLUDE"]).":".ini_get('include_path'));

require_once "permits.php";
$_PERMITS = new PERMITS();
//Successful login sets a "_LEGAL_" permit so that subsequent modules can get through this gate;
//Publicly viewable pages, eg. login.php, will declare a $_TEMP_PERMIT = "_LEGAL_"
if (!$_PERMITS->can_pass("_LEGAL_")) { //must be logged in; prevents specifying module in URL to bypass login
	throw_the_bum_out(NULL,"Evicted(".__LINE__."): no permit; not _LEGAL_");
}
?>

