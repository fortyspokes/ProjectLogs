<?php
//copyright 2015 C.D.Price. Licensed under Apache License, Version 2.0
//See license text at http://www.apache.org/licenses/LICENSE-2.0
if (!$_PERMITS->can_pass("reports")) throw_the_bum_out(NULL,"Evicted(".__LINE__."): no permit");

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
	$_STATE->close_date = false; //not used but lib/project_select.php expects it
	require_once "project_select.php";
	$projects = new PROJECT_SELECT($_PERMITS->restrict("reports"));
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
	$dates = new DATE_SELECT("bp"); //show all before(b) and within period(p)
	$_STATE->date_select = serialize(clone($dates));
	require_once "calendar.php";
	$calendar = new CALENDAR(2, "FT"); //2 pages
	$_STATE->calendar = serialize(clone($calendar));
	$_STATE->msgGreet = $_STATE->project_name."<br>Select the data window";
	$_STATE->status = SELECT_SPECS;
	break 2;
case SELECT_SPECS: //set the from and to dates
	require_once "calendar.php"; //catches $_GET refresh
	require_once "date_select.php";
	$dates = unserialize($_STATE->date_select);
	if (!$dates->POST(DATE_SELECT::TO)) { //check only to date for recent
		$calendar = unserialize($_STATE->calendar);
		$_STATE->msgGreet = "Select the data window";
		break 2;
	}
	set_state($dates);
	$_STATE->heading .= "<br>as of ".$_STATE->to_date->format('Y-m-d');
	$_STATE->msgGreet = $_STATE->project_name."<br>Download the report";
	$_STATE->status = DOWNLOAD_LOG;
	break 2;
case DOWNLOAD_LOG:
	put_log();
	$_STATE->msgGreet = "Done!";
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
	case "b":
		$_STATE->heading .= "<br>for all prior to ".$_STATE->to_date->format("Y-m-d");
		break;
	case "p":
		$_STATE->heading .= "<br>for dates from ".$_STATE->from_date->format('Y-m-d').
							" to ".$_STATE->to_date->format('Y-m-d');
	}

	$sql = "SELECT name FROM ".$_DB->prefix."a00_organization
			WHERE organization_id=".$_SESSION["organization_id"].";";
	$_STATE->orgname = $_DB->query($sql)->fetchObject()->name;

	$sql = "SELECT name, budget, budget_exp, budget_by, mileage
			FROM ".$_DB->prefix."a10_project
			WHERE project_id=".$_STATE->project_id.";";
	$row = $_DB->query($sql)->fetchObject();
	$_STATE->projname = $row->name;
	$_STATE->budget = $row->budget;
	$_STATE->budget_exp = $row->budget_exp;
	$_STATE->budget_by = $row->budget_by;
	$_STATE->mileage = $row->mileage;

	$_STATE->listLog = false;
	if (isset($_POST["chkList"])) $_STATE->listLog = true;

	$_STATE->headings = array();
	$sql = "SELECT column_name FROM information_schema.columns
			WHERE table_name ='".$_DB->prefix."v12_taskreport';";
	$stmt = $_DB->query($sql);
	while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
		if (substr($row["column_name"],-3) == "_id") continue; //don't send id fields
		$_STATE->headings[] = $row["column_name"];
	}
	$_STATE->headings[] = "labor";
	$_STATE->headings[] = "expense";

	return true;
}

function put_log() { //put the log to the download file
	global $_STATE;
	global $version;

	$from = $_STATE->from_date->format('Y-m-d');
	$to = $_STATE->to_date->format('Y-m-d');
	$filename = "taskreport_".$_STATE->orgname."_".$_STATE->projname."_".$to.".csv"; //for file_put...
	require_once "file_put.php"; //start the file put
	$out = fopen('php://output', 'w');

	$outline = array();
	$outline[] = "taskreport";
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

	get_log($out);

	fclose($out);
	FP_end(); //finish off the file put
}

function get_log($file=null) {
	global $_STATE;

	$fields = "";
	foreach ($_STATE->headings as $name) $fields .= $name.",";

	$stmt = set_stmt(substr($fields,0,-1),"");
	while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
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

}

function set_stmt($fields, $limit) {
	global $_DB, $_STATE;

	$sql = "SELECT ".$fields." FROM ".$_DB->prefix."v12_taskreport

			LEFT OUTER JOIN (
			SELECT subtask_id AS labor_id, SUM(hours*rate) AS labor
			FROM ".$_DB->prefix."b00_timelog AS b00
			JOIN ".$_DB->prefix."a14_subtask a14 ON a14.subtask_id = b00.subtask_idref
			JOIN ".$_DB->prefix."a12_task a12 ON a12.task_id = a14.task_idref
			JOIN ".$_DB->prefix."c00_person c00 ON c00.person_id = b00.person_idref
			JOIN ".$_DB->prefix."c02_rate c02
			ON c02.person_idref = b00.person_idref AND c02.project_idref = a12.project_idref
			WHERE logdate >= '".$_STATE->from_date->format('Y-m-d')."'
			AND logdate <= '".$_STATE->to_date->format('Y-m-d')."'
			AND logdate >= c02.effective_asof
			AND (c02.expire_after IS NULL OR logdate <= c02.expire_after)
			GROUP BY subtask_id
			) AS labor_cost ON labor_cost.labor_id = subtask_id

			LEFT OUTER JOIN (
			SELECT subtask_id AS expense_id,
			SUM(CASE WHEN b20.type='mi' THEN amount*".$_STATE->mileage." ELSE amount END) AS expense 				FROM ".$_DB->prefix."b20_expenselog AS b20
			JOIN ".$_DB->prefix."a14_subtask a14 ON a14.subtask_id = b20.subtask_idref
			JOIN ".$_DB->prefix."a12_task a12 ON a12.task_id = a14.task_idref
			JOIN ".$_DB->prefix."c00_person c00 ON c00.person_id = b20.person_idref
			WHERE logdate >= '".$_STATE->from_date->format('Y-m-d')."'
			AND logdate <= '".$_STATE->to_date->format('Y-m-d')."'
			GROUP BY subtask_id
			) AS expense_cost ON expense_cost.expense_id = subtask_id

			WHERE (project_id = ".$_STATE->project_id.")
			AND ((task_inactive_asof IS NULL) OR (task_inactive_asof > :to))
			ORDER BY task_id, subtask_id ".$limit.";";
	$stmt = $_DB->prepare($sql);
	$stmt->bindValue(':to', $_STATE->to_date->format('Y-m-d'), db_connect::PARAM_DATE);
	$stmt->execute();
	return $stmt;
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

//forms and display depend on process state; note, however, that the state was probably changed after
//entering the Main State Gate so this switch will see the next state in the process:
switch ($_STATE->status) {
case SELECT_PROJECT:

	echo $projects->set_list();

	break; //end SELECT_PROJECT status ----END STATE: EXITING FROM PROCESS----
case SELECT_SPECS:
?>
<form method="post" name="frmAction" id="frmAction_ID" action="<?php echo $_SERVER['SCRIPT_NAME']; ?>">
<table cellpadding="3" border="0" align="center">
  <tr><td colspan="3">- - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - </td></tr>
<?php
	echo $dates->HTML();
 ?>
  <tr><td colspan="3">- - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - </td></tr>

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
<?php //end SELECT_SPECS status ----END STATUS PROCESSING----
	break;
case DOWNLOAD_LOG: ?>

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
<button name="btnPut" id="btnPut_ID" type="button" value="download" onclick="download(this)">Download</button>
</form>
<br>
(check your browser preferences for where the downloaded file will go)
<?php //end default status ----END STATUS PROCESSING----
}
EX_pageEnd(); //standard end of page stuff
?>
