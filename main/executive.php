<?php
//copyright 2015, 2016 C.D.Price. Licensed under Apache License, Version 2.0
//See license text at http://www.apache.org/licenses/LICENSE-2.0
require_once "prepend.php";
require_once "state.php";
require_once "common.php";
require_once "db_".$_SESSION['_SITE_CONF']['DBMANAGER'].".php";
$_DB = new db_connect($_SESSION['_SITE_CONF']['DBEDITOR']);
$EX_servercall = false;
$EX_SCRIPTS = $_SESSION["_SITE_CONF"]["_REDIRECT"]."/scripts".$_SESSION["_SITE_CONF"]["SCR"];

//A non-blank $_GET["init"] tells us to create a new state object with status=STATE::INIT;
//many processes rely on that STATE::INIT being set so they know to create initial setup:
if (isset($_GET["init"])) {
	$_STATE = new STATE($_GET["init"]); //create a new state object with status=STATE::INIT
	if (isset($_GET["head"])) {
		$_STATE->heading = $_GET["head"];
		$_STATE->replace();
	}

} else {
	$_STATE = STATE_pull(); //'pull' the working state
	if (isset($_GET["goback"])) {
		if ($_STATE->backup < 0) {
			$_STATE = $_STATE->goback(-$_STATE->backup);
		} else {
			$_STATE = $_STATE->loopback($_STATE->backup);
		}
	} else if (isset($_GET["servercall"]) || isset($_POST["servercall"])) {
		$EX_servercall = true;
		ob_clean(); //server_call wants a clean buffer
	}
}

require_once "staff.php";
if (!isset($EX_staff[$_STATE->ID])) {
	throw_the_bum_out(NULL,"Evicted(".__LINE__."): invalid process ID");
} else {
	$EX_staffer = $EX_staff[$_STATE->ID];
	eval($EX_staffer[PRE_EXEC]);
	require_once($EX_staffer[PAGE]);
}

//called processes should not exit(); or, if they do, they must do their own STATE->push(), etc
$_STATE->push();
$_DB = NULL;

function EX_pageStart($scripts=array()) {
//The standardized HTML stuff at the top of the page:
	global $_STATE, $EX_SCRIPTS, $EX_servercall;
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
<link rel="stylesheet" href="<?php echo
	$_SESSION["_SITE_CONF"]["_REDIRECT"]."/css".$_SESSION["_SITE_CONF"]["CSS"]."/".
	$_SESSION["_SITE_CONF"]["THEME"]; ?>/main.css" type="text/css">
<script language="JavaScript">
var IAm = "<?php echo $_SERVER["SCRIPT_NAME"]; ?>";
var LoaderS = new Array();
window.onload = function() {
  for (var i = 0; i < LoaderS.length; i++) {
    eval (LoaderS[i]);
  }
  top.frames['headframe'].document.getElementById('msgHead_ID').innerHTML = '<?php echo $_STATE->heading; ?>';
}
</script>
<?php
	foreach ($scripts as $script) {
		echo "<script type='text/javascript' src='".$EX_SCRIPTS."/".$script."'></script>\n";
	}
} //end function EX_pageStart

function EX_pageHead() {
//The standardized stuff after scripts and at top of HTML body:
	global $_STATE;
?>
</head>

<body>
<div id="msgGreet_ID" class="greet"><?php echo $_STATE->msgGreet; ?></div>
<?php
} //end function EX_pageHead

function EX_pageEnd() {
//The standardized stuff at the end of the page:
//A neg $goback = # of levels to pop; pos is the actual status to return to.
	global $_STATE;
	$redirect = $_SESSION["_SITE_CONF"]["_REDIRECT"];
?>
<div id="msgStatus_ID" class="status"><?php echo $_STATE->msgStatus ?></div>
<p>
<button type="button" onclick="window.location.assign('<?php echo $redirect; ?>/main/main.php')">&lt&lt Return to menu</button>
<?php
	$state = STATE_pull(); //the state before changes
	if ($state->status > $state->init) { ?>
<button type="button" onclick="window.location.assign('<?php echo $redirect; ?>/main/executive.php?goback')">
	&lt Goback</button>
<?php
	}
	if ($_SESSION["_SITE_CONF"]["RUNLEVEL"] == 1) { ?>
<br><textarea id='msgDebug' cols='100' rows='1' ondblclick='this.rows=this.rows+10;'></textarea>
</p>
<table cellpadding=2>
<?php
		echo "<tr><td align='right' valign='top'>Session: </td><td align='left'>";
		foreach ($_SESSION as $key=>$value) echo $key.";"; echo "</td></tr>";
		echo "<tr><td align='right' valign='top'>Permits: </td>";
		echo "<td align='left'>";
		echo serialize($_SESSION["UserPermits"]);
		echo "</td></tr>";
		echo "<tr><td colspan='2' align='left'>_STATE:</td></tr>";
		$value = serialize(clone($_STATE));
		echo ("<tr valign='top'><td></td><td align='left'>".$value."</td></tr>\n");
		foreach ($_SESSION["STATE"] as $name=>$thread) {
			echo "<tr><td colspan='2' align='left'>_SESSION[STATE][".$name."]:</td></tr>";
			foreach ($thread as $key=>$value) {
				echo ("<tr valign='top'><td align='right'>".$key."</td><td align='left'>".$value."</td></tr>\n");
			}
		}
?>
</table>
<?php
	} ?>
</body>
</html>
<?php
} //end function EX_pageEnd()
?>

