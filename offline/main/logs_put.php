<?php
//copyright 2015 C.D.Price. Licensed under Apache License, Version 2.0
//See license text at http://www.apache.org/licenses/LICENSE-2.0
if (!$_PERMITS->can_pass("project_logs")) throw_the_bum_out(NULL,"Evicted(".__LINE__."): no permit");

require_once "field_edit.php";

//Define the cases for the Main State Gate that are unique to this module:
define ('SELECT_PROJECT', STATE::SELECT + 1);
define ('SELECTED_PROJECT', STATE::SELECTED + 1);
define ('SELECT_SPECS', STATE::SELECT + 2);
define ('DOWNLOAD_LOG', STATE::CHANGE);

$version = "v2.0"; //downloaded with the file for client verification

//Main State Gate: (the while (1==1) allows a loop back through the switch using a 'break 1')
while (1==1) { switch ($_STATE->status) {
case STATE::INIT:
	$_STATE->project_id = 0;
	$_STATE->close_date = COM_NOW();
	require_once "project_select.php";
	$projects = new PROJECT_SELECT($_PERMITS->restrict("project_logs"));
	$_STATE->project_select = serialize(clone($projects));
	if ($projects->selected) {
		$_STATE->status = SELECTED_PROJECT;
		break 1; //re-switch to SELECTED_PROJECT
	}
	$_STATE->msgGreet = "Select the project";
	$_STATE->status = SELECT_PROJECT;
	break 2;
case SELECT_PROJECT: //select the project
	require_once "project_select.php"; //catches $_GET list refresh
	$projects = unserialize($_STATE->project_select);
	$projects->set_state();
	$_STATE->project_select = serialize(clone($projects));
	$_STATE->status = SELECTED_PROJECT; //for possible goback
	$_STATE->replace();
//	break 1; //re_switch
case SELECTED_PROJECT:
	require_once "project_select.php"; //in case of goback
	$projects = unserialize($_STATE->project_select);
	$_STATE->project_name = $projects->selected_name();
	require_once "date_select.php";
	$dates = new DATE_SELECT("wmp","p"); //within week(w), month(m), period(p), default to period
	$_STATE->date_select = serialize(clone($dates));
	require_once "calendar.php";
	$calendar = new CALENDAR(2, "FT"); //2 pages
	$_STATE->calendar = serialize(clone($calendar));
	$_STATE->msgGreet = $_STATE->project_name."<br>Select the date range";
	$_STATE->status = SELECT_SPECS;
	break 2;
case SELECT_SPECS: //set the from and to dates
	require_once "calendar.php"; //catches $_GET refresh
	require_once "date_select.php";
	$dates = unserialize($_STATE->date_select);
	if (!$dates->POST()) {
		$calendar = unserialize($_STATE->calendar);
		$_STATE->msgGreet = $_STATE->project_name."<br>Select the date range";
		break 2;
	}
	set_state($dates);
	$_STATE->msgGreet = $_STATE->project_name."<br>Download the log";
	$_STATE->status = DOWNLOAD_LOG;
	break 2;
case DOWNLOAD_LOG:
	put_log();
	$_STATE->msgGreet .= "Done!";
	$_STATE->status = STATE::DONE;
	break 2;
default:
	throw_the_bum_out(NULL,"Evicted(".__LINE__."): Invalid state=".$_STATE->status);
} } //while & switch
//End Main State Gate

function set_state(&$dates) {
	global $_DB, $_STATE;

	$_STATE->from_date = clone($dates->from);
	$_STATE->to_date = clone($dates->to);

	switch ($dates->checked) {
	case "w":
		$_STATE->heading .= "<br>for the week of ".$_STATE->from_date->format('Y-m-d').
							" to ".$_STATE->to_date->format('Y-m-d');
		break;
	case "m":
		$_STATE->heading .= "<br>for the month of ".$_STATE->from_date->format("M-Y");
		break;
	case "p":
		$_STATE->heading .= "<br>for dates from ".$_STATE->from_date->format('Y-m-d').
							" to ".$_STATE->to_date->format('Y-m-d');
	}

	$sql = "SELECT name FROM ".$_DB->prefix."a00_organization
			WHERE organization_id=".$_SESSION["organization_id"].";";
	$_STATE->orgname = $_DB->query($sql)->fetchObject()->name;

	$sql = "SELECT name, description, budget, budget_exp, budget_by, mileage
			FROM ".$_DB->prefix."a10_project
			WHERE project_id=".$_STATE->project_id.";";
	$row = $_DB->query($sql)->fetchObject();
	$_STATE->projname = $row->name;
	$_STATE->projdesc = $row->description;
	$_STATE->budget = $row->budget;
	$_STATE->budget_exp = $row->budget_exp;
	$_STATE->budget_by = $row->budget_by;
	$_STATE->mileage = $row->mileage;

	$_STATE->listLog = false;
	if (isset($_POST["chkList"])) $_STATE->listLog = true;

	$_STATE->headings = array();
	$sql = "SELECT column_name FROM information_schema.columns
			WHERE table_name ='".$_DB->prefix."v10_timereport';";
	$stmt = $_DB->query($sql);
	while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
		//don't send project_id (keep activity_id, person_id):
		if (($row["column_name"] == "project_id")
		 || ($row["column_name"] == "lastname")
		 || ($row["column_name"] == "firstname")
			) continue;
		$_STATE->headings[] = $row["column_name"];
	}

	return true;
}

function put_log() {
	global $_DB, $_STATE;
	global $version;

	$from = $_STATE->from_date->format('Y-m-d');
	$to = $_STATE->to_date->format('Y-m-d');
	$filename = "logs_".$_STATE->orgname."_".$_STATE->projname."_".$from."_to_".$to.".csv"; //for file_put...
	require_once "file_put.php";
	$out = fopen('php://output', 'w');

	$outline = array();
	$outline[] = "logs";
	$outline[] = $version;
	$outline[] = $from;
	$outline[] = $to;
	$outline[] = $_STATE->orgname;
	$outline[] = $_STATE->projname;
	$outline[] = $_STATE->budget;
	$outline[] = $_STATE->budget_exp;
	$outline[] = $_STATE->budget_by;
	$outline[] = $_STATE->mileage;
	fputcsv($out, $outline); //ID row

	$outline = array();
	foreach ($_STATE->headings as $name) {
		$outline[] = $name;
	}
	fputcsv($out, $outline); //header row

	$outline = array();
	$outline[] = "project";
	$outline[] = $_STATE->projname;
	$outline[] = $_STATE->projdesc;
	fputcsv($out, $outline); //project row

	get_log($out);

	$outline = array();
	$outline[] = "project";
	$outline[] = "<end>";
	fputcsv($out, $outline); //project row

	fclose($out);
	FP_end();
}

function get_log($file=null) {
	global $_DB, $_STATE;

	$fields = "";
	$type = 3;
	$person = 14;
	$rate = 15;
	foreach ($_STATE->headings as $key=>$name) {
		$fields .= $name.",";
		if ($name == "type") $type = $key;
		if ($name == "person_id") $person = $key;
		if ($name == "rate") $rate = $key;
	}

	$sql = "(
			SELECT ".substr($fields,0,-1)." FROM ".$_DB->prefix."v10_timereport
			WHERE (project_id = ".$_STATE->project_id.")
			AND (logdate BETWEEN :from10 AND :to10)
			) UNION (
			SELECT ".substr($fields,0,-1)." FROM ".$_DB->prefix."v11_expensereport
			WHERE (project_id = ".$_STATE->project_id.")
			AND (logdate BETWEEN :from11 AND :to11)
			)
			ORDER BY person_id, logdate;";
		$stmt = $_DB->prepare($sql);
		$stmt->bindValue(':from10', $_STATE->from_date->format('Y-m-d'), db_connect::PARAM_DATE);
		$stmt->bindValue(':to10', $_STATE->to_date->format('Y-m-d'), db_connect::PARAM_DATE);
		$stmt->bindValue(':from11', $_STATE->from_date->format('Y-m-d'), db_connect::PARAM_DATE);
		$stmt->bindValue(':to11', $_STATE->to_date->format('Y-m-d'), db_connect::PARAM_DATE);
		$stmt->execute();
		$person_id = 0;
		while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
			if ($row[$person] != $person_id) {
				$person_id = $row[$person];
				$sql = "SELECT lastname, firstname FROM ".$_DB->prefix."c00_person
						WHERE person_id=".$person_id.";";
				$info = $_DB->query($sql)->fetchObject();
				if (is_null($file)) { //sending to online page
					echo "<tr><td>person</td><td>".$info->lastname."</td><td>".$info->firstname."</td></tr>\n";
				} else { //downloading
					$outline = array();
					$outline[] = "person";
					$outline[] = $info->lastname;
					$outline[] = $info->firstname;
					fputcsv($file, $outline);
				}
			}
			if ($row[$type] == "mi") $row[$rate] = $_STATE->mileage;
			if (is_null($file)) { //sending to online page
				echo "<tr>";
				foreach ($row as $value) {
					if (is_null($value)) $value = 0;
					echo "<td>".$value."</td>";
				}
				echo "</tr>\n";
			} else { //downloading
				fputcsv($file, $row);
			}
		}
		$stmt->closeCursor();
		if (!is_null($file)) { //sending to online page
			$outline = array();
			$outline[] = "person";
			$outline[] = "<end>";
			fputcsv($file, $outline);
		}

}

//-------end function code; begin HTML------------

EX_pageStart(); //standard HTML page start stuff - insert SCRIPTS here
?>
<script language="JavaScript">
<?php	if ($_STATE->status == DOWNLOAD_LOG) { ?>
function download(me) {
  me.style.visibility = "hidden";
  document.getElementById("msgStatus_ID").innerHTML = "Done!";
  me.form.submit();
}
<?php	} ?>
</script>
<?php
echo "<script type='text/javascript' src='".$EX_SCRIPTS."/call_server.js'></script>\n";
if ($_STATE->status == SELECT_SPECS) {
	echo "<script type='text/javascript' src='".$EX_SCRIPTS."/calendar.js'></script>\n";
}
EX_pageHead(); //standard page headings - after any scripts
?>

<?php
//forms and display depend on process state; note, however, that the state was probably changed after entering
//the Main State Gate so this switch will see the next state in the process:
switch ($_STATE->status) {
case SELECT_PROJECT:

	echo $projects->set_list();

	break; //end SELECT_PROJECT status ----END STATE: EXITING FROM PROCESS----
case SELECT_SPECS:
?>
<form method="post" name="frmAction" id="frmAction_ID" action="<?php echo $_SERVER['SCRIPT_NAME']; ?>">
<br>
<table cellpadding="3" border="0" align="center">
  <tr><td>&nbsp</td><td colspan="2">- - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - </td></tr>
<?php
	echo $dates->HTML();
 ?>
  <tr><td>&nbsp</td><td colspan="2">- - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - </td></tr>

<tr><td colspan="3">
<?php
	echo $calendar->create("h"); //horiz
?>
</td></tr>

  <tr>
    <td style="text-align:right"><input type='checkbox' name='chkList'></td>
    <td colspan="2" style="text-align:left">List the report before download</td>
  </tr>
  <tr>
    <td>&nbsp</td>
    <td colspan="2" style="text-align:left">
      <button name="btnDates" type="button" value="dates" onclick="this.form.submit()">Continue</button>
    </td>
  </tr>
</table>
</form>
<?php //end SELECT_PROJECT status ----END STATUS PROCESSING----
	break;
case DOWNLOAD_LOG:
?>
<form method="post" name="frmAction" id="frmAction_ID" action="<?php echo $_SERVER['SCRIPT_NAME']; ?>">
<?php
	if ($_STATE->listLog) {
		echo "<br>\n";
		echo "<table align='center'' cellpadding='4' border='2'>\n";
		echo "  <tr>";
		foreach ($_STATE->headings as $name) {
			echo "<th>".$name."</th>";
		}
		echo "</tr>\n";
		get_log();
		echo "</table>\n";
		echo "<br>\n";
} ?>
<button name="btnPut" id="btnPut_ID" type="button" value="download" onclick="download(this)">Download</button><br>
(check your browser preferences for where the downloaded file will go)
</form>
<?php //end DOWNLOAD_LOG status ----END STATUS PROCESSING----
} ?>

<?php
EX_pageEnd(); //standard end of page stuff
?>

