<?php
//copyright 2015-2016 C.D.Price. Licensed under Apache License, Version 2.0
//See license text at http://www.apache.org/licenses/LICENSE-2.0
if (($_SESSION["_SITE_CONF"]["RUNLEVEL"] < 1) || (!$_PERMITS->can_pass(PERMITS::_SUPERUSER)))
	throw_the_bum_out(NULL,"Evicted(".__LINE__."): no permit");

require_once ("tables_list.php");
tables_list();
$REFRESH_PATH = $_SESSION["_SITE_CONF"]["_STASH"]."/refresh/";

//Main State Gate: (the while (1==1) allows a loop back through the switch using a 'break 1')
while (1==1) { switch ($_STATE->status) {
case STATE::INIT:
	$_STATE->msgGreet = "Check the tables to refresh";
	$_STATE->status = STATE::UPDATE;
	break 2;
case STATE::UPDATE:
	$_STATE->msgStatus = "Tables refreshed:";
	entry_audit();
	$_STATE->msgGreet = "Check more to refesh:";
	break 2;
default:
	throw_the_bum_out(NULL,"Evicted(".__LINE__."): invalid state=".$_STATE->status);
} } //while & switch
//End Main State Gate

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
		$_STATE->msgStatus = "Cannot find file ".$file;
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

	if (isset($_POST["btnRestart"])) {
		$sql = "SELECT max(".$table->idname.") AS maxauto FROM ".$table->name.";";
		$stmt = $db->query($sql);
		$row = $stmt->fetchObject();
		$restart = $row->maxauto;
		$stmt->closeCursor();
		do {
			if (($data = fgetcsv($handle)) === FALSE) {
				$_STATE->msgStatus = "Restart beyond input range; restart at ".$restart;
				return false;
			}
		} while ($data[0] < $restart);

	} else {
		$sql = "DELETE FROM ".$table->name." WHERE ".$table->idname." > 0;";
		$db->exec($sql);
		$db->reset_auto($table->name, $table->idname, 1);
	}

	if ($_POST["txtCount"] == "") {
		$count = -1; //subtracting from here never gets to 0
	} else {
		$count = $_POST["txtCount"]; //records to load
	}

	$sql = "INSERT INTO ".$table->name." (".$fields.") VALUES (".$values.")";
	$stmt = $db->prepare($sql);
	while (($data = fgetcsv($handle)) !== FALSE) {
		if ($count == 0) break; //finished loading desired number
		--$count;
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

	if ($table->idname != "") {
		$sql = "SELECT max(".$table->idname.") AS maxauto FROM ".$table->name.";";
		$stmt = $db->query($sql);
		$row = $stmt->fetchObject();
		$stmt->closeCursor();

		$db->reset_auto($table->name, $table->idname, $row->maxauto+1);
	}

	return true;
}

function entry_audit() {
	global $_STATE;

	if (!isset($_POST["chkTable"])) {
		$_STATE->msgStatus = "No tables were refreshed";
		return;
	}

	if (($_POST["txtCount"] != "") && (!is_numeric($_POST["txtCount"]))) {
		$_STATE->msgStatus = "Invalid 'Stop after' count";
		return;
	}

	try {
		//Use an unprintable char as the delimiter:
		$db = new db_connect("\r".$_POST["txtName"]."\r".$_POST["txtPswd"]);
	} catch (PDOException $e) {
	    $_STATE->msgStatus = "Connection failed: ".$e->getMessage();
		return;
	}
	foreach ($_POST["chkTable"] as $ID => $value) {
		if (!array_key_exists($ID, $_STATE->records)) {
			throw_the_bum_out(NULL,"Evicted(".__LINE__."): invalid table name ".$_POST["chkTable"]);
		}
		if ($value == "on") {
			$_STATE->msgStatus .= "<br>".$ID;
			if (!refresh($db, $_STATE->records[$ID])) {
				$_STATE->msgStatus .= ": attempted refresh failed";
			}
		}
	}
	$db = NULL;
	return;
}

EX_pageStart(); //standard HTML page start stuff - insert scripts here

EX_pageHead(); //standard page headings - after any scripts

//forms and display depend on process state; note, however, that the state was probably changed after entering
//the Main State Gate so this switch will see the next state in the process:
switch ($_STATE->status) {
case STATE::UPDATE:
?>

<form method="post" name="frmAction" id="frmAction_ID" action="<?php echo $_SESSION["IAm"]; ?>">
<table align='center'>
<?php
	foreach($_STATE->records as $ID => $name) {
		if ($name->type != "t") continue; //tables only
		echo "  <tr>\n";
	  	echo "    <td><input type=\"checkbox\" name=\"chkTable[".strval($ID)."]\"></td>\n";
		echo "    <td style='text-align:left'>".$ID."</td>\n";
		echo "  </tr>\n";
	} ?>
</table>
<p>
Username: <input name="txtName" id="txtName_ID" type="text" class="formInput" maxlength="32" size="32">
  Password: <input name="txtPswd" type="password" class="formInput" maxlength="32" size="32">
</p>
  <button type="submit" name="btnRefresh">Refresh</button>
  <button type="submit" name="btnRestart">Restart</button>
Stop after <input name="txtCount" type="text" class="formInput" maxlength="5" size="5" value=""> records
</form>
<?php //end STATE::UPDATE status ----END STATUS PROCESSING----
} ?>

<?php
EX_pageEnd(); //standard end of page stuff
?>

