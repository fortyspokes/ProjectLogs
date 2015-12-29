<?php
//copyright 2015 C.D.Price. Licensed under Apache License, Version 2.0
//See license text at http://www.apache.org/licenses/LICENSE-2.0
//This module can be symlinked to index.php in the site start directories

if (!isset($_SERVER["HTTPS"])){
    header("Location: https://".$_SERVER["HTTP_HOST"].$_SERVER["REQUEST_URI"]);
}

session_start(); //always restart the session, even if (particularly if) doing a browser reload
if (isset($_SESSION["_EVICTED_"])) { //prepend::throw_the_bum_out() set this
	$evicted = $_SESSION["_EVICTED_"]; //save it while session is restarted
}
$_SESSION = array();
session_destroy();
session_write_close();

if (isset($_GET['info'])) {
	phpinfo();
	exit;
}

$_TEMP_PERMIT = "_LEGAL_"; //a temp permission for the "are you logged in" gate (in prepend)
require_once "prepend.php";
if (isset($evicted)) $_SESSION["_EVICTED"] = $evicted; //note _EVICTED != _EVICTED_
$redirect = $_SESSION["_SITE_CONF"]["_REDIRECT"];
?>
<html>
<head>
<title><?php echo $_SESSION['_SITE_CONF']['PAGETITLE'] ?></title>
<meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1">
<link rel="shortcut icon" href="<?php echo $redirect; ?>/images/logo.ico" >
<script language="JavaScript">
if (top != self) {
	top.location = "https://<?php echo($_SERVER["HTTP_HOST"].$_SESSION["_SITE_CONF"]["_OFFSET"].'/'); ?>";
//	top.location = "https://<?php echo($_SERVER["HTTP_HOST"].$_SERVER["REQUEST_URI"]); ?>";
}

function reload_head() {
	top.frames["headframe"].location = "<?php echo $redirect; ?>/main/head.php";
}

function reload_menu() {
	menuframe.location = "<?php echo $redirect; ?>/main/menu.php";
}

function reset_menu() {
	menuframe.restore_attrs();
}

function load_task(task) {
	process = task.split(":");
	if (process[0].charAt(0) == "!") {
		url = process[0].substr(1);
	} else {
		url = "<?php echo $redirect; ?>/main/executive.php?init=" + process[0];
	}
	self.frames["mainframe"].location = url + "&head=" + encodeURI(process[1]);
}

function OnOff(owner, element) {
	var style = self.frames[owner].document.getElementById(element).style;
	style.visibility = (style.visibility=='visible')?'hidden':'visible';
}
</script>
</head>
<body>
<table border="0" cellspacing="0" cellpadding="0" width="100%" height="100%">
<tr><td colspan="2">
<iframe name="headframe" scrolling="no" height="110" width="100%" frameborder="no" border="0" framespacing="0"></iframe>
</td></tr>
<tr height="100%"><td height="100%" width="150">
<iframe name="menuframe" height="100%" width="150" frameborder="no" marginwidth="5"
  src="<?php echo $redirect; ?>/main/menu.php"></iframe>
</td><td width="100%">
<iframe name="mainframe" height="100%" width="100%" frameborder="no" marginwidth="5"
  src="<?php echo $redirect; ?>/main/login.php?init=LI"></iframe>
</td></tr>
</table>

</body>
</html>
