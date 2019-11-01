<?php
//copyright 2015-2017,2019 C.D.Price. Licensed under Apache License, Version 2.0
//See license text at http://www.apache.org/licenses/LICENSE-2.0
/*For testing purposes.  When saved test data is loaded to a test DB, the dates will probably be too old to be used.  This page allows those dates to be changed to be more recent.
*/
if (($_SESSION["_SITE_CONF"]["RUNLEVEL"] < 1) || (!$_PERMITS->can_pass(PERMITS::_SUPERUSER)))
	throw_the_bum_out(NULL,"Evicted(".__LINE__."): no permit");

require_once "lib/field_edit.php";
require_once "lib/tables_list.php";
require_once "lib/async_resp.php";

//Main State Gate: (the while (1==1) allows a loop back through the switch using a 'break 1')
while (1==1) { switch ($_STATE->status) {
case STATE::INIT:
	$_STATE->datelist = set_fields();
	$_STATE->now = COM_NOW();
	$_STATE->upweeks = 0;
	set_max();
	$_STATE->status = STATE::SELECT; //for Page_out()
case STATE::SELECT:
	$_STATE->msgGreet = "Enter the up date criteria";
	Page_out();
	$_STATE->backup = STATE::INIT; //prepare a 'goback'
	$_STATE->status = STATE::SELECTED;
	break 2;
case STATE::SELECTED:
	if (!audit()) {
		$_STATE->status = STATE::SELECT;
		break 1; //go try again
	}
	$_STATE->msgGreet = "These fields will be updated";
	Page_out();
	$_STATE->status = STATE::UPDATE;
	break 2;
case STATE::UPDATE: //the button calls AR_client_open() who sends us here
	AR_open();
	up_dates();
	$_STATE->status = STATE::DONE;
	$_STATE->replace();
	AR_close(true); //re-loads the page
	exit; //using AsyncResp means skipping the buffer ouput, etc. in Executive
//	break 2;
case STATE::DONE:
	$_STATE->msgStatus = $_STATE->savStatus;
	Page_out();
	$_STATE->status = STATE::INIT;
	break 2;
default:
	throw_the_bum_out(NULL,"Evicted(".__LINE__."): invalid state=".$_STATE->status);
} } //while & switch
//End Main State Gate & return to the executive

function set_fields() {
	global $_DB, $_STATE;

	$tables = array();
	$list = DB_tables($_DB->prefix);
	foreach($list as $ID=>$table) {
		if ($table->type != "t") { //t=table; v=view
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

	$_STATE->savStatus = "Done!";
}

function up_one_date($ID, $table, $name, &$field) {
	global $_DB, $_STATE;

	$count = abs($_STATE->upweeks * 7);
	$interval = new DateInterval("P".$count."D");
	$max = new DateTime($field["max"]);
	if ($_STATE->upweeks > 0) {
		$max->add($interval);
	} else {
		$max->sub($interval);
	}
	$field["max"] = $max->format("Y-m-d");

	$sql = "UPDATE ".$table["name"]." SET ".$name."=:newdate
			WHERE ".$table["key"]."=:table_id;";
	$stmt1 = $_DB->prepare($sql);

	$sql = "SELECT ".$table["key"]." AS id, ".$name." AS olddate FROM ".$table["name"]."
			WHERE ".$name." IS NOT NULL
			ORDER BY ".$name.";";
	$stmt2 = $_DB->query($sql);
	$TField = $ID.":".$name;
	$count = 0;
	while ($row = $stmt2->fetchObject()) {
		++$count;
		if (($count % 1000) == 0) {
			AR_send("document.getElementById('UpCount_".$TField."').innerHTML='".$count."';");
		}
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
	AR_send("document.getElementById('UpCount_".$TField."').innerHTML='".$count."';");

}

function Page_out() {
	global $_STATE, $_DB;

	EX_pageStart(); //standard HTML page start stuff - insert scripts here

	if ($_STATE->status == STATE::SELECTED) {
		echo "<script language='JavaScript'>\n";
		foreach (AR_client() as $line) echo $line."\n";
		echo "</script>\n";
	}

	EX_pageHead(); //standard page headings - after any scripts

	echo "<form method='post' name='frmAction' id='frmAction_ID' action=".$_SESSION["IAm"].">\n";
	echo "<table align='center'>\n";
	switch ($_STATE->status) {
	case STATE::SELECT:
?>
  <tr>
    <td style='text-align:center' colspan='7'>Enter the number of weeks to up dates:
      <input type='text' class='number' name='txtCount' maxlength='9' size='9' value='0'>
    </td>
  </tr>
  <tr><td style='text-align:left' colspan='7'>Select the dates to upgrade:</td><tr>
  <tr style='text-align:left'>
    <td></td>
    <td style='text-align:right'>Table:</td>
    <td>Field</td>
    <td style='text-align:center'>Latest date - 'how far back'</td>
    <td># to be up dated</td>
  </tr>
<?php
		the_list(true);
?>
  <tr>
    <td style='text-align:center' colspan='7'><button type="submit">Select</button></td>
  </tr>
<?php
		break; //end case STATE::INIT status processing
	case STATE::SELECTED:
?>
  <tr>
    <td style='text-align:center' colspan='7'>Number of weeks to up dates: <?php echo $_STATE->upweeks; ?></td>
  </tr>
  <tr style='text-align:left'>
    <td></td>
    <td style='text-align:right'>Table:</td>
    <td>Field</td>
    <td style='text-align:center'>Latest date - 'how far back'</td>
    <td># up dated</td>
  </tr>
<?php
		the_list(false);
?>
  <tr>
    <td style='text-align:center' colspan='7'><button type="button" onclick="AR_client_open();">Do it!</button></td>
  </tr>
<?php
		break; //end case STATE::SELECTED status processing
	case STATE::DONE:
?>
  <tr>
    <td style='text-align:center' colspan='7'>Number of weeks up dated: <?php echo $_STATE->upweeks; ?></td>
  </tr>
  <tr style='text-align:left'>
    <td></td>
    <td style='text-align:right'>Table:</td>
    <td>Field</td>
    <td style='text-align:center'>New latest date - 'how far back'</td>
    <td># up dated</td>
  </tr>
<?php
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
	foreach ($_STATE->datelist as $ID=>$table) {
		foreach ($table["fields"] as $name=>$field) {
			if ((!$listall) && (!$field["selected"])) continue;
			$TField = $ID.":".$name;
			echo "  <tr>\n";
		  	echo "    <td><input type='checkbox' name='chkDate[]' value='".$TField."'".$disable."></td>\n";
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
			echo "    <td ID='UpCount_".$TField."' style='text-align:center'>".$field["count"]."</td>\n";
			echo "  </tr>\n";
		}
	}
} //end function the_list()
?>
