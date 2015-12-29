<?php
//copyright 2015 C.D.Price. Licensed under Apache License, Version 2.0
//See license text at http://www.apache.org/licenses/LICENSE-2.0
require_once ("../noparent.php");

$_TEMP_PERMIT = "_LEGAL_"; //a temp permission for the "are you logged in" gate (in prepend)
require_once "prepend.php";
require_once "common.php";
require_once ("db_".$_SESSION['_SITE_CONF']['DBMANAGER'].".php");

$menu = array();
$title = "";
if (isset($_SESSION["person_id"])) { //logged in

	$_DB = new db_connect($_SESSION['_SITE_CONF']['DBEDITOR']);
	$title = "Menu";
	require_once "menu_list.php";
	$_DB = NULL;
}
?>
<html>
<head>
<title>SR2S Timesheets Menu</title>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
<link rel="stylesheet" href="<?php echo
	$_SESSION["_SITE_CONF"]["_REDIRECT"]."/css".$_SESSION["_SITE_CONF"]["CSS"]."/".
	$_SESSION["_SITE_CONF"]["THEME"]; ?>/menu.css" type="text/css">
<script language="JavaScript">
<!--
if (top == self) {
	top.location = "https://<?php echo($_SERVER["HTTP_HOST"].$_SESSION["_SITE_CONF"]["_OFFSET"].'/'); ?>";
}

var color_sav;
var style_sav;
var weight_sav;
var Tasks = new Array();
window.onload = function () {
//getElementsByName doesn't work in IE!!
//  Tasks = document.getElementsByName("Task");
  TDs = document.getElementsByTagName('td');
  for (ndx=0; ndx < TDs.length; ndx++) {
    if (TDs.item(ndx).getAttribute("name") == "Task") {
      TDs[ndx].LOADED = false;
      Tasks.push(TDs[ndx]);
    }
  }
  if (ndx == 0) return;
  color_sav = Tasks[0].style.color;
  style_sav = Tasks[0].style.fontStyle;
  weight_sav = Tasks[0].style.fontWeight;
}

function restore_attrs() {
  for (ndx=0; ndx < Tasks.length; ndx++) {
    Tasks[ndx].style.color = color_sav;
    Tasks[ndx].style.fontStyle = style_sav;
    Tasks[ndx].style.fontWeight = weight_sav;
    Tasks[ndx].LOADED = false;
  }
}

function load_task(me,task,item) {
  top.load_task(task + ":" + item);
  cell = me.parentNode.parentNode;
  if (cell.LOADED) return;
  restore_attrs();
  cell.LOADED = true;
  cell.style.color = "red";
  cell.style.fontStyle = "italic";
  cell.style.fontWeight = "bold";
}
//-->
</script>
</head>

<body>
<?php
if (count($menu) > 0) {
	echo "<table cellspacing='5'>\n";
	echo "<br>".$title."<br>\n";
	echo "<ul>\n";
	foreach ($menu as $item => $ID) {
		echo "<tr><td name='Task'><li>";
		echo "<a onclick=\"javascript: load_task(this,'".$ID."','".$item."');\">".$item."</a>";
		echo "</td></tr>\n";
	}
	echo "</ul>\n";
	echo "</table>\n";
}
?>
</body>
</html>
