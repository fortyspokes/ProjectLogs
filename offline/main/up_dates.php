<?php
//copyright 2015-2017 C.D.Price. Licensed under Apache License, Version 2.0
//See license text at http://www.apache.org/licenses/LICENSE-2.0
/*For testing purposes.  When saved test data is loaded to a test DB, the dates will probably be too old to be used.  This page allows those dates to be changed to be more recent.
*/
if (($_SESSION["_SITE_CONF"]["RUNLEVEL"] < 1) || (!$_PERMITS->can_pass(PERMITS::_SUPERUSER)))
	throw_the_bum_out(NULL,"Evicted(".__LINE__."): no permit");

require_once "lib/field_edit.php";
require_once "lib/tables_list.php";

//Main State Gate: (the while (1==1) allows a loop back through the switch using a 'break 1')
while (1==1) { switch ($_STATE->status) {
case STATE::INIT:
	$_STATE->datelist = set_fields();
	$_STATE->upweeks = 0;
	$_STATE->now = COM_NOW();
	$_STATE->msgGreet = "Enter the up date criteria";
	$_STATE->status = STATE::SELECT; //prepare a 'goback'
//	break 1; //do a re-switch
case STATE::SELECT:
	set_max();
	$_STATE->status = STATE::UPDATE;
	break 2;
case STATE::UPDATE:
	if (!audit()) {
		break 2; //go try again
	}
	up_dates();
	$_STATE->status = STATE::SELECT;
	break 1; //set it up again
default:
	throw_the_bum_out(NULL,"Evicted(".__LINE__."): invalid state=".$_STATE->status);
} } //while & switch
//End Main State Gate

function set_fields() {
	global $_DB, $_STATE;

	$tables = array();
	$list = DB_tables($_DB->prefix);
	foreach($list as $ID=>$table) {
		if ($table->type != "t") {
			continue;
		}
		$atable = array();
		$atable["name"] = $table->name;
		$atable["key"] = $table->idname;
		$fields = array();
		foreach($table->fields as $name=>$field) {
			if ($field->editor != "date") {
				continue;
			}
			$afield = array();
			$afield["selected"] = false;
			$afield["max"] = '';
			$afield["from"] = 0;
			$afield["thru"] = -1;
			$afield["count"] = 0;
			$fields[$name] = $afield;
		}
		$atable["fields"] = $fields;
		$tables[$ID] = $atable;
	}
	return $tables;
}

function set_max() {
	global $_DB, $_STATE;

	foreach($_STATE->datelist as $ID=>&$table) {
		foreach($table["fields"] as $name=>&$field) {
			$sql = "SELECT MAX(".$name.") as max_date FROM ".$table["name"].";";
			$stmt = $_DB->query($sql);
			$row = $stmt->fetchObject();
			if (is_null($row->max_date)) {
				$field["max"] = '';
			} else {
				$maxdate = new DateTime($row->max_date);
				$field["max"] = $maxdate->format("Y-m-d");
			}
			$stmt->closeCursor();
		}
	}
}

function audit() {
	global $_STATE;

	foreach($_STATE->datelist as $ID=>&$table) {
		foreach($table["fields"] as $name=>&$field) {
			$field["selected"] = false; //reset 'em
		}
	}
	if (!isset($_POST["chkDate"])) {
		$_STATE->msgStatus = "No fields selected";
		return false;
	}
	foreach ($_POST["chkDate"] as $chkDate) { //first, save entered stuff for re-display
		$who = explode(":", $chkDate); //tableID:fieldname
		$field = &$_STATE->datelist[$who[0]]["fields"][$who[1]];
		$field["selected"] = true;

		$from = $field["from"]; //initialize
		$thru = $field["thru"];
		if (isset($_POST["txtfromID:".$chkDate])) $from = $_POST["txtfromID:".$chkDate];
		if (isset($_POST["txtthruID:".$chkDate])) $thru = $_POST["txtthruID:".$chkDate];
		$field["from"] = $from;
		$field["thru"] = $thru;
	}
	foreach ($_POST["chkDate"] as $chkDate) { //now, audit
		$who = explode(":", $chkDate); //tableID:fieldname
		$field = &$_STATE->datelist[$who[0]]["fields"][$who[1]];
		$from = $field["from"];
		$thru = $field["thru"];
		if (!is_numeric($from) || !is_numeric($thru)) {
			$_STATE->msgStatus = "IDs must be numbers";
			return false;
		}
		$from *= 1; //make it an integer type
		$thru *= 1;
		if (!is_int($from) || !is_int($thru)) {
			$_STATE->msgStatus = "IDs must be integers";
			return false;
		}
	}

	$_STATE->upweeks = $_POST["txtCount"];
	if (!is_numeric($_STATE->upweeks)) {
		$_STATE->msgStatus = "Weeks count must be a number";
		return false;
	}
	$_STATE->upweeks *= 1; //make it an integer type
	if (!is_int($_STATE->upweeks)) {
		$_STATE->msgStatus = "Weeks count must be an integer";
		return false;
	}

	return true;
}

function up_dates() {
	global $_STATE;

	foreach($_STATE->datelist as $ID=>&$table) {
		foreach($table["fields"] as $name=>&$field) {
			if ($field["selected"]) {
				up_one_date($ID, $table, $name, $field);
			}
		}
	}

	$_STATE->msgStatus = "Done!";
}

function up_one_date($ID, $table, $name, &$field) {
	global $_DB, $_STATE;

	$count = abs($_STATE->upweeks * 7);
	$interval = new DateInterval("P".$count."D");

	$sql = "UPDATE ".$table["name"]." SET ".$name."=:newdate
			WHERE ".$table["key"]."=:table_id;";
	$stmt1 = $_DB->prepare($sql);

	$sql = "SELECT ".$table["key"]." AS id, ".$name." AS olddate FROM ".$table["name"]."
			WHERE ".$name." IS NOT NULL
			AND ".$table["key"]." >= ".$field["from"];
	if ($field["thru"] > 0) {
		$sql .= " AND ".$table["key"]." <= ".$field["thru"];
	}
	$sql .= " ORDER BY ".$name.";";
	$stmt2 = $_DB->query($sql);
	$count = 0;
	while ($row = $stmt2->fetchObject()) {
		++$count;
		$temp = new DateTime($row->olddate);
		if ($_STATE->upweeks > 0) {
			$temp->add($interval);
		} else {
			$temp->sub($interval);
		}
		$new_date = $temp->format("Y-m-d");
		$stmt1->bindValue(':newdate',$new_date,db_connect::PARAM_DATE);
		$stmt1->bindValue(':table_id',$row->id,PDO::PARAM_INT);
		$stmt1->execute();
	}
	$stmt2->closeCursor();
	$field["count"] += $count;

}

EX_pageStart(); //standard HTML page start stuff - insert scripts here
EX_pageHead(); //standard page headings - after any scripts
?>

<form method="post" name="frmAction" id="frmAction_ID" action="<?php echo $_SESSION["IAm"]; ?>">
<table align='center'>
  <tr>
    <td style='text-align:right' colspan='3' class="label">Enter the number of weeks to up dates:</td>
    <td style='text-align:left' colspan='4'><input type='text' class='number' name='txtCount' maxlength='9' size='9' value='<?php echo $_STATE->upweeks; ?>'></td>
  </tr>
  <tr><td style='text-align:left' colspan='7'>Select the dates to upgrade:</td><tr>
  <tr style='text-align:left'>
    <td></td>
    <td style='text-align:right'>Table:</td>
    <td>Field</td>
    <td style='text-align:center'>Latest date - 'how far back'</td>
    <td>from ID</td>
    <td>thru ID</td>
    <td># up dated</td>
  </tr>
<?php
	foreach ($_STATE->datelist as $ID=>$table) {
		foreach ($table["fields"] as $name=>$field) {
			$TField = $ID.":".$name;
			echo "  <tr>\n";
		  	echo "    <td><input type='checkbox' name='chkDate[]' value='".$TField."'";
			if ($field["selected"]) echo " checked";
			echo "></td>\n";
			echo "    <td style='text-align:right'>".$ID.":</td>\n";
			echo "    <td style='text-align:left'>".$name."</td>\n";
			if ($field["max"] == "") {
				echo "    <td>all null</td>\n";
			} else {
				$max = new DateTime($field["max"]);
				$dateDiff = $max->diff($_STATE->now);
				$days = $dateDiff->format('%R%a');
				$weeks = intdiv($days, 7);
				$extra = $days % 7;
				echo "    <td>".$max->format("l  Y-m-d")." - ".$weeks." weeks, ".$extra." days</td>\n";
			}
			echo "    <td><input type='text' class='number' name='txtfromID:".$TField."' maxlength='9' size='9' value='".$field["from"]."'></td>\n";
			echo "    <td><input type='text' class='number' name='txtthruID:".$TField."' maxlength='9' size='9' value='".$field["thru"]."'></td>\n";
			echo "    <td>".$field["count"]."</td>\n";
			echo "  </tr>\n";
		}
	}
?>
</table>
  <button type="submit">Upgrade</button>
</form>

<?php
EX_pageEnd(); //standard end of page stuff
?>
