<?php
//copyright 2015-2017,2019 C.D.Price. Licensed under Apache License, Version 2.0
//See license text at http://www.apache.org/licenses/LICENSE-2.0
if (!$_PERMITS->can_pass("project_logs")) throw_the_bum_out(NULL,"Evicted(".__LINE__."): no permit");

require_once "lib/field_edit.php";

//The Main State Gate cases:
define('LIST_PROJECTS',		STATE::INIT);
define ('SELECT_PROJECT',		LIST_PROJECTS + 1);
define ('SELECTED_PROJECT',		LIST_PROJECTS + 2);
define('SHOW_SPECS',		STATE::INIT + 10);
define ('SELECT_SPECS',			SHOW_SPECS + 1);
define ('DOWNLOAD_LOG',		STATE::INIT + 20);

$version = "v2.1"; //downloaded with the file for client verification

//Main State Gate: (the while (1==1) allows a loop back through the switch using a 'break 1')
while (1==1) { switch ($_STATE->status) {
case LIST_PROJECTS:
	$_STATE->project_id = 0;
	$_STATE->close_date = COM_NOW();
	require_once "lib/project_select.php";
	$projects = new PROJECT_SELECT($_PERMITS->restrict("project_logs"));
	$_STATE->project_select = serialize(clone($projects));
	if ($projects->selected) {
		$_STATE->init = SELECT_SPECS;
		$_STATE->status = SELECTED_PROJECT;
		break 1; //re-switch to SELECTED_PROJECT
	}
	$_STATE->msgGreet = "Select the ".ucfirst($projects->get_label("project"));
	Page_out();
	$_STATE->status = SELECT_PROJECT;
	break 2; //return to executive

case SELECT_PROJECT: //select the project
	require_once "lib/project_select.php"; //catches $_GET list refresh
	$projects = unserialize($_STATE->project_select);
	$projects->set_state();
	$_STATE->project_select = serialize(clone($projects));
case SELECTED_PROJECT:
	$_STATE->project_name = $projects->selected_name();

case SHOW_SPECS:
	$_STATE->set_a_gate(SHOW_SPECS); //for a 'goback' - sets status
	require_once "lib/date_select.php";
	$dates = new DATE_SELECT("wmp","p"); //within week(w), month(m), period(p), default to period
	$_STATE->date_select = serialize(clone($dates));
	require_once "lib/calendar.php";
	$calendar = new CALENDAR(2, "FT"); //2 pages
	$_STATE->calendar = serialize(clone($calendar));
	$_STATE->msgGreet = $_STATE->project_name."<br>Select the date range";
	$_STATE->backup = LIST_PROJECTS; //set goback
	Page_out();
	$_STATE->status = SELECT_SPECS;
	$_STATE->goback_to(LIST_PROJECTS);
	break 2; //return to executive

case SELECT_SPECS: //set the from and to dates
	require_once "lib/calendar.php"; //catches $_GET refresh
	require_once "lib/date_select.php";
	$calendar = unserialize($_STATE->calendar);
	$dates = unserialize($_STATE->date_select);
	if (!$dates->POST()) {
		$_STATE->msgGreet = $_STATE->project_name."<br>Select the date range";
		Page_out();
		break 2;
	}
	set_state($dates);
	require_once "lib/props_send.php"; //routines for sending property values
	$props_send = new PROPS_SEND(array("a12","a14","a21"));
	$_STATE->props_send = serialize($props_send);
	$_STATE->msgGreet = $_STATE->project_name."<br>Download the log";
	$_STATE->goback_to(SHOW_SPECS);
	$_STATE->status = DOWNLOAD_LOG;
	Page_out();
	break 2; //return to executive

case DOWNLOAD_LOG:
	$props_send = unserialize($_STATE->props_send);
	put_log();
	$_STATE->msgStatus .= "Done!";
	Page_out();
	$_STATE->status = STATE::DONE;
	break 2; //return to executive

default:
	throw_the_bum_out(NULL,"Evicted(".$_STATE->ID."/".__LINE__."): Invalid state=".$_STATE->status);
} } //while & switch
//End Main State Gate & return to executive

function set_state(&$dates) {
	global $_DB, $_STATE;

	$_STATE->from_date = clone($dates->from);
	$_STATE->to_date = clone($dates->to);

	$from = new DATE_FIELD($_STATE->from_date);
	$to = new DATE_FIELD($_STATE->to_date);
	switch ($dates->checked) {
	case "w":
		$_STATE->heading .= "<br>for the week of ".$from->format()." to ".$to->format();
		break;
	case "m":
		$_STATE->heading .= "<br>for the month of ".$from->format("M-Y");
		break;
	case "p":
		$_STATE->heading .= "<br>for dates from ".$from->format()." to ".$to->format();
	}

	$sql = "SELECT name FROM ".$_DB->prefix."a00_organization
			WHERE organization_id=".$_SESSION["organization_id"].";";
	$_STATE->orgname = $_DB->query($sql)->fetchObject()->name;

	$sql = "SELECT project_id, name, description, budget, budget_exp, budget_by, mileage
			FROM ".$_DB->prefix."a10_project
			WHERE project_id=".$_STATE->project_id.";";
	$row = $_DB->query($sql)->fetchObject();
	$_STATE->project_ids = array($row->project_id);
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

	require_once "lib/props_send.php"; //routines for sending property values
	$props_send = unserialize($_STATE->props_send);

	$from = $_STATE->from_date->format('Y-m-d');
	$to = $_STATE->to_date->format('Y-m-d');
	$filename = "logs_".$_STATE->orgname."_".$_STATE->projname."_".$from."_to_".$to.".csv"; //for file_put...
	require_once "lib/file_put.php";

	$out = FP_open($filename);

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

	get_log($props_send,$out);

	$props_send->send_all($out);

	$_STATE->msgStatus = "Logs successfully downloaded";
	FP_close($out); //does not return
}

function get_log(&$props_send, &$file=null) {
	global $_DB, $_STATE;

	$fields = "";
	$type = 3; //offset to these fields
	$person = 14;
	$rate = 15;
	$outline = array();
	$HTML = "  <tr>";
	foreach ($_STATE->headings as $key=>$name) {
		$fields .= $name.",";
		if ($name == "type") $type = $key;
		elseif ($name == "person_id") $person = $key;
		elseif ($name == "rate") $rate = $key;
		$outline[] = $name;
		$HTML .= "<th>".$name."</th>";
	}
	$HTML .= "</tr>\n";
	if (is_null($file)) { //to online page
		echo $HTML; //header row
	} else { //downloading
		fputcsv($file, $outline); //header row
	}

	$props_send->init($outline); //set up to get property values

	$sql_logs = "(
			SELECT ".substr($fields,0,-1)." FROM ".$_DB->prefix."v10_timereport
			WHERE (project_id = ".$_STATE->project_id.")
			AND (logdate BETWEEN :from10 AND :to10)
			) UNION (
			SELECT ".substr($fields,0,-1)." FROM ".$_DB->prefix."v11_expensereport
			WHERE (project_id = ".$_STATE->project_id.")
			AND (logdate BETWEEN :from11 AND :to11)
			)
			ORDER BY person_id, logdate;";
	$stmt_logs = $_DB->prepare($sql_logs);

	foreach ($_STATE->project_ids as $project_id) {
		if (!is_null($file)) { //downloading
			$outline = array();
			$outline[] = "<project>";
			$outline[] = $_STATE->projname;
			$outline[] = $_STATE->projdesc;
			$outline[] = $project_id;
			fputcsv($file, $outline); //project row
		}

		$stmt_logs->bindValue(':from10', $_STATE->from_date->format('Y-m-d'), db_connect::PARAM_DATE);
		$stmt_logs->bindValue(':to10', $_STATE->to_date->format('Y-m-d'), db_connect::PARAM_DATE);
		$stmt_logs->bindValue(':from11', $_STATE->from_date->format('Y-m-d'), db_connect::PARAM_DATE);
		$stmt_logs->bindValue(':to11', $_STATE->to_date->format('Y-m-d'), db_connect::PARAM_DATE);
		$stmt_logs->execute();
		$person_id = 0;
		while ($row_logs = $stmt_logs->fetch(PDO::FETCH_NUM)) {
			if ($row_logs[$person] != $person_id) {
				$person_id = $row_logs[$person];
				$sql = "SELECT lastname, firstname FROM ".$_DB->prefix."c00_person
						WHERE person_id=".$person_id.";";
				$info = $_DB->query($sql)->fetchObject();
				if (is_null($file)) { //sending to online page
					echo "<tr><td>person</td><td>".$info->lastname."</td><td>".$info->firstname."</td></tr>\n";
				} else { //downloading
					$outline = array();
					$outline[] = "<person>";
					$outline[] = $info->lastname;
					$outline[] = $info->firstname;
					$outline[] = $person_id;
					fputcsv($file, $outline);
				}
			}

			$props_send->add_ids($row_logs); //add property value ids to logs record

			if ($row_logs[$type] == "mi") $row_logs[$rate] = $_STATE->mileage;
			if (is_null($file)) { //sending to online page
					echo "<tr>";
					foreach ($row_logs as $value) {
						if (is_null($value)) $value = 0;
						echo "<td>".$value."</td>";
					}
					echo "</tr>\n";
			} else { //downloading
					fputcsv($file, $row_logs);
			}
		}
		$stmt_logs->closeCursor();
	} //end projects

	if (is_null($file)) return; //to online page

	$outline = array();
	$outline[] = "<person>";
	$outline[] = "<end>";
	fputcsv($file, $outline); //end person row

	$outline = array();
	$outline[] = "<project>";
	$outline[] = "<end>";
	fputcsv($file, $outline); //end project row
}

function Page_out() {
	global $_DB, $_STATE;

	$scripts = array("call_server.js");
	if ($_STATE->status == SHOW_SPECS) {
		$scripts[] = "calendar.js";
	}
	EX_pageStart($scripts); //standard HTML page start stuff - insert SCRIPTS here
?>
<?php
	if ($_STATE->status == DOWNLOAD_LOG) {
?>
<script language="JavaScript">
function download(me) {
  me.style.visibility = "hidden";
  document.getElementById("msgStatus_ID").innerHTML = "Done!";
  me.form.submit();
}
</script>
<?php
	}
	EX_pageHead(); //standard page headings - after any scripts

	switch ($_STATE->status) {

	case LIST_PROJECTS:
		global $projects;
		echo $projects->set_list();
		break; //end LIST_PROJECTS status ----END STATE: EXITING FROM PROCESS----

	case SHOW_SPECS:
	case SELECT_SPECS: //errors...
?>
<form method="post" name="frmAction" id="frmAction_ID" action="<?php echo $_SESSION["IAm"]; ?>">
<br>
<table cellpadding="3" border="0" align="center">
  <tr><td>&nbsp</td><td colspan="2">- - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - </td></tr>
<?php
		global $dates;
		echo $dates->HTML();
?>
  <tr><td>&nbsp</td><td colspan="2">- - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - </td></tr>

<tr><td colspan="3">
<?php
		global $calendar;
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
<?php
		break; //end SHOW/SELECT_SPECS status ----END STATUS PROCESSING----

	case DOWNLOAD_LOG:
?>
<form method="post" name="frmAction" id="frmAction_ID" action="<?php echo $_SESSION["IAm"]; ?>">
<?php
		if ($_STATE->listLog) {
			echo "<br>\n";
			echo "<table align='center'' cellpadding='4' border='2'>\n";
			global $props_send;
			get_log($props_send);
			echo "</table>\n";
			echo "<br>\n";
		}
?>
<button name="btnPut" id="btnPut_ID" type="button" value="download" onclick="download(this)">Download</button><br>
(check your browser preferences for where the downloaded file will go)
</form>
<?php
		break; //end DOWNLOAD_LOG status ----END STATUS PROCESSING----

	default:
		throw_the_bum_out(NULL,"Evicted(".$_STATE->ID."/".__LINE__."): invalid state=".$_STATE->status);

	} //end select ($_STATE->status) ----END STATE: EXITING FROM PROCESS----

	EX_pageEnd(); //standard end of page stuff

} //end Page_out()
?>
