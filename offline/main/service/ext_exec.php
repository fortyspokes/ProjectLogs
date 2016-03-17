<?php
//copyright 2015-2016 C.D.Price. Licensed under Apache License, Version 2.0
//See license text at http://www.apache.org/licenses/LICENSE-2.0
require_once "prepend.php";
require_once "lib/state.php";
require_once "lib/common.php";
require_once "lib/db_".$_SESSION['_SITE_CONF']['DBMANAGER'].".php";
$_DB = new db_connect($_SESSION['_SITE_CONF']['DBEDITOR']);
$EX_servercall = false;

if (isset($_GET["init"])) {
	$_STATE = STATE_pull()->scion_pull(-1); //-1 => get 'youngest' generation
	$_STATE = $_STATE->scion_start("_EXTENSION"); //add this thread

	$_SESSION["_EXTENSION"] = array();
	$_SESSION["_EXTENSION"]["name"] = $_STATE->extension;
	$inifile = $_SESSION["_SITE_CONF"]["_EXTENSIONS"].$_STATE->extension."_conf.php";
	$_SESSION["_EXTENSION"] = array_merge($_SESSION["_EXTENSION"],parse_ini_file($inifile));

	$_STATE->heading = $_SESSION["_EXTENSION"]["title"];

} else if (isset($_GET["quit"])) {
	unset($_SESSION["_EXTENSION"]);
	$_STATE= STATE_pull("_EXTENSION");
	$_STATE->cut(); //remove the thread
	ob_clean();
	echo "@remove_ext(true);\n";
	exit;

} else {
	$_STATE = STATE_pull("_EXTENSION"); //'pull' the working state
	if (isset($_GET["goback"])) {
		$_STATE = $_STATE->goback(2);
	} else if (isset($_GET["servercall"]) || isset($_POST["servercall"])) {
		$EX_servercall = true;
		ob_clean(); //server_call wants a clean buffer
	}
}
$EX_status = $_STATE->status; //save for later

require_once $_SESSION["_SITE_CONF"]["_EXTENSIONS"].$_SESSION["_EXTENSION"]["page"]; //load the working extension module

//called processes should not exit(); or, if they do, they must do their own STATE_save(), etc
$_STATE->push();
$_DB = NULL;

function EX_pageStart() {
//The standardized HTML stuff at the top of the page:
	global $_STATE, $EX_servercall, $_VERSION;
	if ($EX_servercall) {
		exit(); //server_call wants a clean buffer
	}
//the !DOCTYPE below is necessary to get position:fixed working in IE!!
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html>
<head>
<title>SR2S Timesheets <?php echo $_STATE->heading; ?></title>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
<link rel="stylesheet" href="<?php echo $_SESSION["BUTLER"]; ?>?IAm=CG&file=main&ver=<?php echo $_VERSION; ?>" type="text/css">
<script language="JavaScript">
var IAm = "<?php echo $_SESSION["IAm"]; ?>";
var LoaderS = new Array();
window.onload = function() {
  for (var i = 0; i < LoaderS.length; i++) {
    eval (LoaderS[i]);
  }
}
</script>
<?php
} //end function EX_pageStart

function EX_pageHead() {
//The standardized stuff after scripts and at top of HTML body:
	global $_STATE;
?>
</head>

<body>
<div class="pagehead"><?php echo $_STATE->heading; ?></div><br>
<div id="msgGreet_ID" class="greet"><?php echo $_STATE->msgGreet; ?></div>
<?php
} //end function EX_pageHead

function EX_pageEnd() {
//The standardized stuff at the end of the page:
	global $_STATE;
	global $EX_status;
?>
<div id="msgStatus_ID"><?php echo $_STATE->msgStatus ?></div>
<p>
<button type="button" onclick="return window.parent.remove_ext(false);">&lt&lt Return</button>
<?php
	if ($EX_status != STATE::INIT) { ?>
<button type="button" onclick="window.location.assign('<?php echo $_SESSION["IAm"] ?>&goback')">
	&lt Goback</button>
<?php
	}
	if ($_SESSION["_SITE_CONF"]["RUNLEVEL"] == 1) { ?>
<br><textarea id='msgDebug' cols='100' rows='1' ondblclick='this.rows=this.rows+10;'></textarea>
</p>
<table cellpadding=2>
<?php
		echo "<tr><td colspan='2' align='left'>_STATE:</td></tr>";
		$value = serialize(clone($_STATE));
		echo ("<tr valign='top'><td></td><td align='left'>".$value."</td></tr>\n");
		echo "<tr><td colspan='2' align='left'>_SESSION[STATE][_EXTENSION]:</td></tr>";
		foreach ($_SESSION["STATE"]["_EXTENSION"] as $key=>$value) {
			echo ("<tr valign='top'><td align='right'>".$key."</td><td align='left'>".$value."</td></tr>\n");
		} ?>
</table>
<?php
	} ?>
</body>
</html>
<?php
} //end functin EX_pageEnd
?>
