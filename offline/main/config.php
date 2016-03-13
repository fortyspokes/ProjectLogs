<?php
//copyright 2015-2016 C.D.Price. Licensed under Apache License, Version 2.0
//See license text at http://www.apache.org/licenses/LICENSE-2.0

if (!$_PERMITS->can_pass(PERMITS::_SUPERUSER))  throw_the_bum_out(NULL,"Evicted(".__LINE__."): no permit");

$HTML = "";
$reload = false;
//Main State Gate: (the while (1==1) allows a loop back through the switch using a 'break 1')
while (1==1) { switch ($_STATE->status) {
case STATE::INIT:
	$_STATE->msgGreet = "Site Config:";
	$_STATE->status = STATE::UPDATE;
	break 2;
case STATE::UPDATE:
	$_STATE->msgGreet = "Site Config:";
	$HTML = update($reload);
	break 2;
default:
	throw_the_bum_out(NULL,"Evicted(".__LINE__."): invalid state=".$_STATE->status);
} } //while & switch
function THE_END() {
//	global $_DB, $_STATE;
//	sav_state($_STATE);
}

function update(&$reload) {

	$HTML =  "<br>Changes:<br>\n";
	foreach($_POST['txtConfig'] as $ID => $value) {
		if (($ID == "DBADMIN") || ($ID == "DBEDITOR") || ($ID == "DBREADER")) continue;

		if (is_array($_SESSION['_SITE_CONF'][$ID])) {
			if (implode(":",$_SESSION['_SITE_CONF'][$ID]) != $value) {
				$HTML .= "<br>".$ID.": ".$_SESSION['_SITE_CONF'][$ID]."=>".$value."\n";
				$_SESSION['_SITE_CONF'][$ID] = explode(":",$value);
			}

		} else {
			if ($_SESSION['_SITE_CONF'][$ID] != $value) {
				if ($ID =="THEME") $reload = true;
				$HTML .= "<br>".$ID.": ".$_SESSION['_SITE_CONF'][$ID]."=>".$value."\n";
				$_SESSION['_SITE_CONF'][$ID] = $value;
			}
		}
	}
	return $HTML."<br><br>End changes\n";
}

$redirect = $_SESSION["_SITE_CONF"]["_REDIRECT"];

EX_pageStart();
?>
<script language="JavaScript">
if (top == self) {
  top.location = "https://<?php echo($_SERVER["HTTP_HOST"]); ?>";
}
<?php
if ($reload) {
	echo "LoaderS.push('top.reload_head(); top.reload_menu();');\n";
} ?>
</script>

<?php
EX_pageHead();
echo $HTML;
?>

<form method="post" name="frmAction" id="frmAction_ID" action="<?php echo $_SESSION["IAm"]; ?>">
<table align='center'>
<?php
foreach ($_SESSION['_SITE_CONF'] as $ID => $value) {
	echo "  <tr>\n";
	echo "    <td>".$ID."</td>\n";
  	echo "    <td><input type='text' name=\"txtConfig[".strval($ID)."]\" maxlength='256' size='128' value='";
	if (is_array($value)) {
		echo (implode(":",$value));
	} else {
		if (($ID == "DBADMIN") || ($ID == "DBEDITOR") || ($ID == "DBREADER")) {
			$delim = substr($value,0,1);
			$where = strpos($value,$delim,1);
			$value = substr($value,1,$where - 1);
		}
		echo $value;
	}
	echo "'></td>";
	echo "  </tr>\n";
}
?>
</table>
  <button type="submit">Update</button>
</form>
</p>
  <div id="msgStatus_ID"><?php echo $_STATE->msgStatus ?></div>
</p>
</p>
<button type="button" onclick="window.location.assign('<?php
	echo $redirect; ?>/main/main.php')">Return to menu</button>
<?php
if (($_SESSION["_SITE_CONF"]["RUNLEVEL"] == 1) || ($_SESSION["person_id"] == 0)) {
function show_array($list) {

	foreach ($list as $ID => $value) {
		echo "  <tr>\n";
		echo "    <td>".$ID."</td>\n";
		if (is_array($value)) {
			echo "    <td> array:</td>";
			echo "  </tr>\n";
			echo "  <tr><td></td><td><table>\n";
			show_array($value);
			echo "  </table></td></tr>\n";
		} else {
			if (($ID == "DBADMIN") || ($ID == "DBEDITOR") || ($ID == "DBREADER")) $value = "*";
			echo "    <td>".$value."</td>";
			echo "  </tr>\n";
		}
	}

}
echo "<p>";
echo "<table align='center'>";
echo "<tr><td>Session Values:</td></tr>";
show_array($_SESSION);
echo "<tr><td></td><td>     --------------------</td></tr>";
echo "<tr><td>State:</td></tr>";
	echo ("<tr><td>ID</td><td>".$_STATE->ID."</td></tr>\n");
	echo ("<tr><td>heading</td><td>".$_STATE->heading."</td></tr>\n");
	echo ("<tr><td>status</td><td>".$_STATE->status."</td></tr>\n");
	echo ("<tr><td>records</td><td>".implode("||",$_STATE->records)."</td></tr>\n");
	echo ("<tr><td>record_id</td><td>".$_STATE->record_id."</td></tr>\n");
	echo ("<tr><td>fields</td><td>".implode("||",$_STATE->fields)."</td></tr>\n");
	echo ("<tr><td>msgGreet</td><td>".$_STATE->msgGreet."</td></tr>\n");
	echo ("<tr><td>msgStatus</td><td>".$_STATE->msgStatus."</td></tr>\n");
	echo ("<tr><td>noSleep</td><td>".implode("||",$_STATE->noSleep)."</td></tr>\n");
echo "</table>";
echo "<br>";
}?>
</body>
</html>
<?php THE_END(); ?>

