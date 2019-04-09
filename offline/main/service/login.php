<?php
//copyright 2015-2016,2019 C.D.Price. Licensed under Apache License, Version 2.0
//See license text at http://www.apache.org/licenses/LICENSE-2.0
//require_once ("../noparent.php");

$_TEMP_PERMIT = "_LEGAL_"; //a temp permission for the "are you logged in" gate (in prepend)
require_once "prepend.php";
require_once "lib/common.php";
require_once ("lib/db_".$_SESSION['_SITE_CONF']['DBMANAGER'].".php");
$_DB = new db_connect($_SESSION['_SITE_CONF']['DBEDITOR']);

require_once ("lib/state.php");
if (isset($_GET["init"])) {
	$_STATE = new STATE($_GET["init"]); //create a new state object with status=STATE::INIT
	if (isset($_GET["head"])) {
		$_STATE->heading = $_GET["head"];
	}
} else {
	$_STATE = STATE_pull(); //'pull' the working state
}

if (isset($_SESSION["user"])) {
	$_STATE->fields["txtName"] = $_SESSION["user"];
} else {
	$_STATE->fields["txtName"] = "";
}

$reload = FALSE;
$_STATE->msgGreet = "Please login:";
$_STATE->msgStatus = "";
switch ($_STATE->status) {
case STATE::INIT:
	$reload = TRUE; //make sure other frames load after me
	$_STATE->status = STATE::ENTRY;
	break;
case STATE::ENTRY:
	require_once "lib/logging.php";
	if (entry_audit()) {
		$_STATE->msgGreet = "";
		$_STATE->msgStatus = "";
		$reload = TRUE; //reload other frames to get current login info
		$_STATE->ID = "MA"; //re-load main.php instead of this mod
		$_STATE->status = STATE::DONE;
	} else {
		error_log("Logerr: by ".$_STATE->fields["txtName"]);
	}
	break;
default:
	$_STATE->status = STATE::ERROR;
}

$_STATE->replace(); //with new status, etc.
$_DB = NULL;
?>
<html>
<head>
<title>SR2S Timesheets Login</title>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
<link rel="stylesheet" href="<?php echo $_SESSION["BUTLER"]; ?>?IAm=CG&file=main&ver=<?php echo $_VERSION; ?>" type="text/css">
<script language="JavaScript">
<!--
if (top == self) {
	top.location = "https://<?php echo($_SERVER["HTTP_HOST"]); ?>";
}

window.onload = function () {
<?php
if ($reload) {
	echo "  top.reload_head();\n";
	echo "  top.reload_menu();\n";
}
if ($_STATE->status == STATE::DONE) {
	echo "  top.reload_main();\n";
} else {
	if ($_STATE->fields['txtName'] == "") {
		echo "  document.getElementById('txtName_ID').focus();\n";
	} else {
		echo "  document.getElementById('txtPswd_ID').focus();\n";
	}
}
?>
}
//-->
</script>
</head>

<body><h1><?php echo $_STATE->msgGreet; ?></h1>
<?php
if ($_STATE->status == STATE::ENTRY) {
?>
<form method="post" name="frmLogin" action="<?php echo $_SESSION["BUTLER"]."?IAm=".$_STATE->ID; ?>">
<p>
Username: <input name="txtName" id="txtName_ID" type="text" class="formInput" <?php
	echo "value=\"".COM_output_edit($_STATE->fields['txtName'])."\"";
?> maxlength="32" size="32">
</p>
<p>
Password: <input name="txtPswd" id="txtPswd_ID" type="password" class="formInput" maxlength="32" size="32">
</p>
<p>
<input type="submit" value="Login">   <?php echo $_STATE->msgStatus; ?>
</p>
</form>
<?php
}
?>
</p>
<?php
	if ($_SESSION["_SITE_CONF"]["RUNLEVEL"] == 1) {
		require_once "lib/debug.php";
		echo debug_session();
	} ?>
</body>
</html>
