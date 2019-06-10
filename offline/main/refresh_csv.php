<?php
//copyright 2015-2016,2019 C.D.Price. Licensed under Apache License, Version 2.0
//See license text at http://www.apache.org/licenses/LICENSE-2.0
if (($_SESSION["_SITE_CONF"]["RUNLEVEL"] < 1) || (!$_PERMITS->can_pass(PERMITS::_SUPERUSER)))
	throw_the_bum_out(NULL,"Evicted(".__LINE__."): no permit");

require_once ("lib/tables_list.php");
require_once "lib/async_resp.php";

$REFRESH_PATH = $_SESSION["_SITE_CONF"]["_STASH"]."/refresh/";

//Main State Gate: (the while (1==1) allows a loop back through the switch using a 'break 1')
while (1==1) { switch ($_STATE->status) {
case STATE::INIT:
	$_STATE->tablelist = set_list();
	$_STATE->status = STATE::SELECT; //for Page_out()
case STATE::SELECT:
	$_STATE->msgGreet = "Check the tables to refresh";
	Page_out();
	$_STATE->backup = STATE::INIT; //prepare a 'goback'
	$_STATE->status = STATE::SELECTED;
	break 2;
case STATE::SELECTED:
	if (!audit()) {
		$_STATE->status = STATE::SELECT;
		break 1; //go try again
	}
	$_STATE->msgGreet = "These tables will be refreshed";
	Page_out();
	$_STATE->status = STATE::UPDATE;
	break 2;
case STATE::UPDATE: //the button calls AR_client_open() who sends us here
	$_STATE->savStatus = "Refresh status:<br>";
	AR_open();
	do_it();
	$_STATE->status = STATE::DONE;
	$_STATE->replace();
	AR_close(true); //re-loads the page
	exit; //using AsyncResp means skipping the buffer ouput, etc. in Executive
case STATE::DONE:
	$_STATE->msgStatus = $_STATE->savStatus;
	Page_out();
	$_STATE->status = STATE::INIT;
	break 2;
default:
	throw_the_bum_out(NULL,"Evicted(".__LINE__."): invalid state=".$_STATE->status);
} } //while & switch
//End Main State Gate & return to the executive

function set_list() {
	global $_STATE, $_DB;

	$tables = DB_tables($_DB->prefix);
	$list = array();
	foreach($tables as $ID => $name) {
		if ($name->type != "t") continue; //tables only
		$list[$ID] = array("count"=>0, "selected"=>false);
	}
	return $list;
}

function money_check($value) {

	if (substr($value,0,1) == "$" ) $value = substr($value,1,0); //remove $sign
	$value = "0".$value; //deal with an empty value
	$value = str_replace(",", "", $value); //remove commas
	return $value;
}

function date_check($value) {

	if ($value == "") return NULL;
	$value = str_replace("/", "-", $value);
	$date = explode("-",$value);
	//create form yyyy-mm-dd instead of mm-dd-yyyy:
	if (strlen($date[0]) <= 2) $value = $date[2]."-".$date[0]."-".$date[1];
	if ($value == "0000-00-00") return NULL;
	return $value;
}

function string_check($value) {

	return COM_string_decode($value); //remove dis-allowed chars
}

function refresh(&$db, &$table) {
	global $_STATE, $REFRESH_PATH;

	$file = $REFRESH_PATH.$table->name.".csv";
	if (!file_exists($file)) {
		$_STATE->savStatus .= "Cannot find file ".$file;
		return false;
	}
	$handle = fopen($file, "r");

	if (($headers = fgetcsv($handle)) === false) { //csv headers
		$sql = "DELETE FROM ".$table->name." WHERE ".$table->idname." > 0;";
		$db->exec($sql);
		$db->reset_auto($table->name, $table->idname, 1);
		return true; //empty
	}
	$names = array(); //field names for SQL insert
	$fields = ""; //SQL FIELDS list
	$values = ""; //SQL VALUES list
	foreach ($headers as $name) {
		//if not defined in tables_list, skip it (may have been deleted since last save)
		if (!isset($table->fields[$name])) {
			$names[] = "-";
			continue;
		}
		$names[] = $name;
		$fields .= ",".$name;
		$values .= ",:".$name;
	}
	$fields = substr($fields,1); //remove leading ","
	$values = substr($values,1);

	$sql = "DELETE FROM ".$table->name." WHERE ".$table->idname." > 0;";
	$db->exec($sql);
	$db->reset_auto($table->name, $table->idname, 1);

	$count = 0;
	$sql = "INSERT INTO ".$table->name." (".$fields.") VALUES (".$values.")";
	$stmt = $db->prepare($sql);
	while (($data = fgetcsv($handle)) !== FALSE) {
		++$count;
		if (($count % 1000) == 0) {
			AR_send("document.getElementById('UpCount_".$table->ID."').innerHTML='".$count."';");
		}
		$ndx = 0;
		foreach ($names as $name) {
			if ($name == "-") { //if not defined in tables_list, skip it
				++$ndx;
				continue;
			}
			$field = $table->fields[$name];
			$value = $data[$ndx];
			if ($field->editor != "") {
				$editor = $field->editor."_check";
				$value = $editor($value);
			}
			$stmt->bindValue(":".$name, $value,$field->type);
			++$ndx;
		}
		$stmt->execute();
	}
	fclose($handle);
	$table->count = $count;
	AR_send("document.getElementById('UpCount_".$table->ID."').innerHTML='".$count."';");

	if ($table->idname != "") {
		$sql = "SELECT max(".$table->idname.") AS maxauto FROM ".$table->name.";";
		$stmt = $db->query($sql);
		$row = $stmt->fetchObject();
		$stmt->closeCursor();

		$db->reset_auto($table->name, $table->idname, $row->maxauto+1);
	}

	return true;
} //refresh()

function do_it() {
	global $_STATE, $_DB;

	$connect = explode("\\",$_GET["AR_msg"]);
	try {
		//Must login as admin; use an unprintable char as the delimiter:
		$db = new db_connect("\\".$connect[0]."\\".$connect[1]);
	} catch (PDOException $e) {
	    $_STATE->savStatus .= "Connection failed: ".$e->getMessage();
		return;
	}

	$tables = DB_tables($_DB->prefix);
	foreach ($_STATE->tablelist as $ID=>&$attrs) {
		if (!$attrs["selected"]) continue;
		$_STATE->savStatus .= $ID.": ";
		$table = $tables[$ID];
		$table->ID = $ID;
		$table->count = 0;
		if (refresh($db, $table)) {
			$_STATE->savStatus .= "done<br>";
		} else {
			$_STATE->savStatus .= "refresh failed<br>";
		}
		$attrs["count"] = $table->count;
	}

	$_STATE->savStatus .= "Done!";
	$db = NULL;
	return;
} //do_it()

function audit() {
	global $_STATE;

	foreach($_STATE->tablelist as $ID=>&$table) {
		$table["selected"] = false; //reset 'em
	}
	if (!isset($_POST["chkTable"])) {
		$_STATE->msgStatus = "No tables were refreshed";
		return false;
	}
	foreach ($_POST["chkTable"] as $ID => $value) { //first, save entered stuff for re-display
		$_STATE->tablelist[$ID]["selected"] = true;
	}

	return true;
} //audit()

function Page_out() {
	global $_STATE, $_DB;

	EX_pageStart(); //standard HTML page start stuff - insert scripts here

	switch ($_STATE->status) {
	case  STATE::SELECT:
?>
<script language="JavaScript">
var all = true;
function select_all() {
	var boxes = document.getElementsByTagName("INPUT");
	for (var i=0; i<boxes.length; ++i) {
		if(boxes[i].value == 'table') {
			boxes[i].checked = all;
		}
	}
	all = !all;
}
</script>
<?php
		break; //end case STATE::INIT
	case STATE::SELECTED:
		echo "<script language='JavaScript'>\n";
		foreach (AR_client() as $line) echo $line."\n";
?>
function do_it() {
	var user = document.getElementById("txtName_ID").value;
	var pswd = document.getElementById("txtPswd_ID").value;
	if ((user == "") || (pswd == "")) {
		alert("Please enter the DB user ID and password");
		return;
	}
	AR_client_open(user+"\\"+pswd);
}
<?php
		echo "</script>\n";
		break; //end case STATE::SELECTED
	} //end switch

	EX_pageHead(); //standard page headings - after any scripts

	echo "<form method='post' name='frmAction' id='frmAction_ID' action=".$_SESSION["IAm"].">\n";
	echo "<table align='center'>\n";
	switch ($_STATE->status) {
	case STATE::SELECT:
?>

  <tr>
    <td style='text-align:center' colspan='3'>
      <button type='button' onclick='return select_all()'>Select all on/off</button>
    </td>
  </tr>
<?php
		the_list(true);
?>
  <tr>
    <td style='text-align:center' colspan='3'><button type="submit">Select</button></td>
  </tr>
<?php
		break; //end case STATE::SELECT status processing

	case STATE::SELECTED:
		the_list(false);
?>
  <tr>
    <td style='text-align:center' colspan='3'>
<p>
Username: <input name="txtName" id="txtName_ID" type="text" class="formInput" maxlength="32" size="32">
  Password: <input name="txtPswd" id="txtPswd_ID" type="password" class="formInput" maxlength="32" size="32">
</p>
    </td>
  </tr>
  <tr>
    <td style='text-align:center' colspan='3'><button type="button" onclick="do_it();">Do it!</button></td>
  </tr>
<?php
		break; //end case STATE::SELECTED status processing

	case STATE::DONE:
		the_list(false);
		break; //end case STATE::DONE status processing

	default:
		echo "Invalid state: ".$_STATE->status;
	} //end switch ($_STATE->status)

	echo "</table>\n";
	echo "</form>\n";
	EX_pageEnd(); //standard end of page stuff
} //end function Page_out()

function the_list($listall=false) {
	global $_STATE;
	if ($listall) { $disable=""; } else { $disable=" checked disabled"; }
	foreach ($_STATE->tablelist as $ID=>$table) {
		if ((!$listall) && (!$table["selected"])) continue;
		echo "  <tr>\n";
	  	echo "    <td><input type='checkbox' name='chkTable[".$ID."]' value='table'".$disable."></td>\n";
		echo "    <td style='text-align:left'>".$ID.":</td>\n";
		echo "    <td ID='UpCount_".$ID."' style='text-align:center'>".$table["count"]."</td>\n";
		echo "  </tr>\n";
	}
} //end function the_list()
?>
