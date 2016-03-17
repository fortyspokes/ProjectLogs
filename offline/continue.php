<?php
//copyright 2015-2016 C.D.Price. Licensed under Apache License, Version 2.0
//See license text at http://www.apache.org/licenses/LICENSE-2.0

function You_Are_Here() { //returns the full directory path of the system includes
	return dirname(realpath(__FILE__)); //realpath() removes .., ., etc.
}

function throw_the_bum_out($publicmsg=NULL, $privatemsg=NULL, $script=false) {
	ob_clean(); //remove any previous headers
	if (!is_null($privatemsg)) error_log($privatemsg.": ".$_SERVER['SCRIPT_NAME']);
	if (!is_null($publicmsg)) {
		$_SESSION["_EVICTED_"] = $publicmsg;
	}
	$protocol = "http"; if (isset($_SERVER["HTTPS"])) $protocol .= "s";
	if ($script) {
		echo "top.location=\"".
			$protocol."://".$_SESSION["HOST"]."?user=".$_SESSION["user"].
			"\";document.close();\n";
	} else {
		echo "<html><head><script>top.location=\"".
			$protocol."://".$_SESSION["HOST"]."?user=".$_SESSION["user"].
			"\";document.close();</script></head></html>";
	}
	exit();
}

function errorButler($errno, $errstr, $errfile, $errline) {

	if ($_SESSION["_SITE_CONF"]["RUNLEVEL"] == 1) return false;

	error_log($errstr. " in ".$errfile." on line ".$errline);
	throw_the_bum_out("An error has occurred<br> please notify the system administrator",NULL);
	return true;
}

ob_start(); //clear out any prior headers
ob_clean();

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

if (!isset($mod)) $mod = $_GET["IAm"];

require_once "lib/staff.php";
//Use staff.php ID from $_GET["IAm"] to get module name, except if prefixed by !, then following is the name
if (substr($mod, 0, 1) == "!") {
	$mod = substr($mod, 1);
} elseif (strlen($mod) == KEYLENGTH) {
	$mod = "main/".$EX_staff[$mod][PAGE];
} else {
	$ID = substr($mod, -KEYLENGTH);
	$mod = substr($mod,0,strlen($mod)-KEYLENGTH).$EX_staff[$ID][PAGE];
}

//If a directory path is prefixed, the / is replaced by * in the URL (asterisk will NEVER be used in a name)
$mod = str_replace("*","/",$mod);

require_once $mod;
?>
