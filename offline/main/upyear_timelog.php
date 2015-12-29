<?php
//copyright 2015 C.D.Price. Licensed under Apache License, Version 2.0
//See license text at http://www.apache.org/licenses/LICENSE-2.0
/*For testing purposes.  When saved test data is loaded to a test DB, the dates will probably be too old to be used.  This page allows those dates to be changed to be more recent.
*/
if (($_SESSION["_SITE_CONF"]["RUNLEVEL"] < 1) || (!$_PERMITS->can_pass(PERMITS::_SUPERUSER)))
	throw_the_bum_out(NULL,"Evicted(".__LINE__."): no permit");

require_once "field_edit.php";
require_once "tables_list.php";

function old_date() {
	global $_DB, $_STATE;

	$sql = "SELECT MAX(logdate) as old_date FROM ".$_DB->prefix."b00_timelog;";
	$stmt = $_DB->query($sql);
	$row = $stmt->fetchObject();
	$old_date = new DateTime($row->old_date);
	$stmt->closeCursor();
	$days = array("Sun","Mon","Tue","Wed","Thu","Fri","Sat");
	$_STATE->old_date = $days[$old_date->format("w")]." ". $old_date->format("Y-m-d");
}

function up_date($chkDate, $interval, $diff) {
	global $_DB, $_STATE;

	$IDs = explode(":", $chkDate); //tableID:fieldname
	foreach ($_STATE->records as $ID=>$table) {
		if ($ID == $IDs[0]) {
			$IDs[0] = "";
			break;
		}
	}
	if ($IDs[0] != "") {
		echo "table ".$chkDate." not found<br>";
		exit;
	}
	foreach ($table->fields as $fieldname=>$field) {
		if ($fieldname == $IDs[1]) {
			$IDs[1] = "";
			break;
		}
	}
	if ($IDs[1] != "") {
		echo "field ".$chkDate." not found<br>";
		exit;
	}

	$sql = "UPDATE ".$table->name." SET ".$fieldname."=:newdate WHERE ".$table->idname."=:table_id;";
	$stmt1 = $_DB->prepare($sql);

	$sql = "SELECT ".$table->idname." AS id, ".$fieldname." AS olddate FROM ".$table->name."
			WHERE ".$fieldname." IS NOT NULL
			ORDER BY ".$fieldname.";";
	$stmt2 = $_DB->query($sql);
	$marker = 0;
	$redate = 0;
	while ($row = $stmt2->fetchObject()) {
		if ($row->olddate != $redate) {
			$redate = $row->olddate;
			$temp = new DateTime($redate);
			if ($diff > 0) {
				$temp->add($interval);
			} else {
				$temp->sub($interval);
			}
			$new_date = $temp->format("Y-m-d");
		}
		if ($marker < $row->id) {
			echo $row->id."=".$row->olddate."=>".$new_date."<br>";
			$marker = $row->id + 10;
		}
		$stmt1->bindValue(':newdate',$new_date,db_connect::PARAM_DATE);
		$stmt1->bindValue(':table_id',$row->id,PDO::PARAM_INT);
		$stmt1->execute();
	}
	$stmt2->closeCursor();
}

function upgrade(&$new_date) {
	global $_DB, $_STATE;

//	$diff = date_diff($_STATE->fields["Close Date"]->value, COM_NOW(), true);
//	if ($diff->m > 2) {


	$sql = "SELECT MAX(logdate) AS max_date FROM ".$_DB->prefix."b00_timelog;";
	$stmt = $_DB->query($sql);
	$row = $stmt->fetchObject();
	$max_date = new DateTime($row->max_date);
	$stmt->closeCursor();

	$diff = date_diff($max_date,$new_date->value, true);
	$interval = new DateInterval("P".$diff->days."D");
	$diff = 1;
	if ($max_date > $new_date->value) $diff = -1;

	tables_list();
	foreach ($_POST["chkDate"] as $chkDate) {
		up_date($chkDate, $interval, $diff);
	}

	echo "<br>Done!";
	exit;
}

//Main State Gate: (the while (1==1) allows a loop back through the switch using a 'break 1')
while (1==1) { switch ($_STATE->status) {
case STATE::INIT:
	tables_list();
	$_STATE->msgGreet = "Enter the new date";
	$_STATE->status = STATE::SELECT; //prepare a 'goback'
//	break 1; //do a re-switch
case STATE::SELECT:
	$_STATE->old_date = "";
	$new_date = new DATE_FIELD("txtNew","",TRUE,TRUE,TRUE,0,FALSE,"now");
	old_date();
	$_STATE->status = STATE::UPDATE;
	break 2;
case STATE::UPDATE:
	$_STATE->msgGreet = "";
	$new_date = new DATE_FIELD("txtNew","",TRUE,TRUE,TRUE,0,FALSE,"now");
	$msg = $new_date->audit();
	if ($msg === true) {
		upgrade($new_date);
	} else {
		$_STATE->msgStatus = $msg;
		$_STATE->status = STATE::INIT;
		break;
	}
	$_STATE->status = STATE::DONE;
	break 2;
default:
	throw_the_bum_out(NULL,"Evicted(".__LINE__."): invalid state=".$_STATE->status);
} } //while & switch

EX_pageStart(); //standard HTML page start stuff - insert scripts here
EX_pageHead(); //standard page headings - after any scripts
?>

<form method="post" name="frmAction" id="frmAction_ID" action="<?php echo $_SERVER['SCRIPT_NAME']; ?>">
<table align='center'>
  <tr>
    <td class="label">Old Date:</td>
    <td><?php echo $_STATE->old_date; ?></td>
  </tr>
  <tr>
    <td class="label"><?php echo $new_date->HTML_label("Enter the New Date (match weekdays?): "); ?></td>
    <td><?php echo $new_date->HTML_input(0); ?></td>
  </tr>
  <tr><td colspan='2'>Select the dates to upgrade:</td><tr>
<?php
	foreach ($_STATE->records as $ID=>$table) {
		foreach ($table->fields as $name=>$field) {
			if ($field->editor == "date") {
				$label = $ID.":".$name;
				echo "  <tr>\n";
			  	echo "    <td><input type='checkbox' name='chkDate[]' value='".$label."' checked></td>\n";
				echo "    <td style='text-align:left'>".$label."</td>\n";
				echo "  </tr>\n";
			}
		}
	} ?>
</table>
  <button type="submit">Upgrade</button>
</form>

<?php
EX_pageEnd(); //standard end of page stuff
?>

