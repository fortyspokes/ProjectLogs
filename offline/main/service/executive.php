<?php
//copyright 2015-2016,2019 C.D.Price. Licensed under Apache License, Version 2.0
//See license text at http://www.apache.org/licenses/LICENSE-2.0
require_once "prepend.php";
require_once "lib/state.php";
require_once "lib/common.php";
require_once "lib/db_".$_SESSION['_SITE_CONF']['DBMANAGER'].".php";
$_DB = new db_connect($_SESSION['_SITE_CONF']['DBEDITOR']);
$EX_servercall = false;

$_STATE = STATE_pull(); //'pull' the working state
//A non-blank $_GET["init"] tells us to replace the state object with new status & ID;
//many processes rely on that STATE::INIT being set so they know to create initial setup:
if (isset($_GET["init"])) {
	if (isset($_GET["head"])) {
		$_STATE->heading = $_GET["head"];
	}
	$_STATE->status = STATE::INIT;
	$_STATE->ID = $_GET["init"]; //the staff module
	$_STATE->replace();
	$_SESSION["IAm"] = $_SESSION["BUTLER"]."?IAm=".$_GET["IAm"]; //for form action

} else {
	if (isset($_GET["goback"])) {
		if ($_GET["goback"] != "") $_STATE->backup = -($_GET["goback"]);
		if ($_STATE->backup < 0) {
			$_STATE = $_STATE->goback(-$_STATE->backup); //goback x levels
		} else {
			$_STATE = $_STATE->loopback($_STATE->backup); //loopback to given status
		}
	} else if (isset($_GET["servercall"]) || isset($_POST["servercall"])) {
		$EX_servercall = true;
		ob_clean(); //server_call wants a clean buffer
	}
}

require_once "lib/staff.php";
if (!isset($EX_staff[$_STATE->ID])) {
	throw_the_bum_out(NULL,"Evicted(".__LINE__."): invalid process ID");
} else {
	$EX_staffer = $EX_staff[$_STATE->ID];
	eval($EX_staffer[PRE_EXEC]);
	require_once("main/".$EX_staffer[PAGE]);
}

//called processes should not exit(); or, if they do, they must do their own STATE->push(), etc
$_STATE->push();
$_DB = NULL;

function EX_pageStart($scripts=array()) {
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
  top.frames['headframe'].document.getElementById('msgHead_ID').innerHTML = '<?php echo $_STATE->heading; ?>';
}
</script>
<?php
	foreach ($scripts as $script) {
		echo "<script type='text/javascript' src='".$_SESSION["BUTLER"],"?IAm=SG&file=".$script."&ver=".$_VERSION."'></script>\n";
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
?>
<div id="msgStatus_ID" class="status"><?php echo $_STATE->msgStatus ?></div>
<p>
<button type="button" onclick="top.reload_main();">&lt&lt Return to menu</button>
<?php
	$state = STATE_pull(); //the state before changes
	if ($state->status > $state->init) { ?>
<button type="button" onclick="window.location.assign('<?php echo $_SESSION["IAm"] ?>&goback')">
	&lt Goback</button>
<?php
	}
	if ($_SESSION["_SITE_CONF"]["RUNLEVEL"] == 1) {
		require_once "lib/debug.php";
		echo debug_area();
		echo "</p>\n";
		echo debug_session();
	} ?>
</body>
</html>
<?php
} //end function EX_pageEnd()
?>
