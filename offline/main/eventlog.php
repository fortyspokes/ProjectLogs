<?php
//copyright 2015 C.D.Price. Licensed under Apache License, Version 2.0
//See license text at http://www.apache.org/licenses/LICENSE-2.0

require_once "field_edit.php";

//Define the cases for the Main State Gate that are unique to this module:
define ('SELECT_PROJECT', STATE::SELECT + 1);
define ('SELECTED_PROJECT', STATE::SELECTED + 1);
define ('SELECT_SPECS', STATE::SELECT + 2);
define ('SELECTED_SPECS', STATE::SELECTED + 2);
define ('SHEET_DISP', STATE::SELECT + 3);

define ('ACCOUNT_DISP', STATE::SELECT);
define ('ACCOUNT_PICK', STATE::SELECTED);
define ('EVENT_DISP', STATE::SELECT + 2);
define ('EVENT_PICK', STATE::SELECTED + 2);
define ('DATE_DISP', STATE::SELECT + 3);
define ('DATE_PICK', STATE::SELECTED + 3);
define ('COMMENTS_DISP', STATE::SELECT + 4);
define ('COMMENTS_PICK', STATE::SELECTED + 4);
define ('SESSIONS_DISP', STATE::SELECT + 5);
define ('BUTTON_DISP', STATE::SELECT + 6);

define ('EVENT_HEAD', "Class");

//Define $_STATE->columns array: (a 'column' corresponds to one day within the date range)
define ('COL_COUNT', 0); //total columns (1 rel)
define ('COL_OPEN',1); //first open column (0 rel)
define ('COL_INACTIVE',2); //first 'inactive' column (0 rel)
define ('COL_AGENT',3); //name of 'inactive' agent: 'project','task','subtask'

$version = "v1.0"; //goes with the downloaded logs file for client verification

//Main State Gate: (the while (1==1) allows a loop back through the switch using a 'break 1')
while (1==1) { switch ($_STATE->status) {
case STATE::INIT:
	$_STATE->title_singular = EVENT_HEAD;
	$_STATE->project_id = 0;
	$_STATE->accounting_id = 0;
	$_STATE->accounting = "";
	$_STATE->event_id = 0;
	$_STATE->account_id = 0;
	$_STATE->columns = array(1,0,0,"");
	require_once "project_select.php";
	$projects = new PROJECT_SELECT(get_projects($_SESSION["person_id"]),true);
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
	$dates = new DATE_SELECT("wmp","p"); //show within week(w), month(m), period(p)(default)
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
	$_STATE->status = SELECTED_SPECS; //for possible goback
	$_STATE->replace();
//	break 1; //re_switch
case SELECTED_SPECS:
	log_list($_STATE);
	set_closedCols();
	$_STATE->msgGreet = "Add or change info: click on the lefthand column";
	$_STATE->scion_start("SHEET"); //create the child state stack
	$_STATE->status = SHEET_DISP;
	break 2;
case SHEET_DISP: //fill cells (if edit, starts with Hours)
	if (isset($_GET["sheet"])) { //change displayed sheet
		$_STATE = $_STATE->goback(1); //go back to log_list (BEFORE this project change)
		require_once "project_select.php";
		$projects = unserialize($_STATE->project_select);
		$projects->set_state($_GET["sheet"]);
		$_STATE->project_select = serialize($projects);
		$_STATE->replace();
		break 1;
	}
	if (isset($_GET["reset"])) {
		$_STATE = $_STATE->goback(1); //go back to log_list
		break 1;
	}
	if (isset($_GET["getdesc"])) { //asking for the description of a cell
		cell_desc($_STATE);;
		break 2;
	}
	if (isset($_POST["btnPut"])) { //asking for a download
		log_put();
		break 2;
	}

	//Add/Update a row of the displayed sheet:
	$SCION = $_STATE->scion_pull(); //use the child thread
	$response = "@"; //initialize to do an eval
	while (1==1) { switch ($SCION->status) { //the SCION state gate
	case STATE::INIT:
		$SCION->agent = $_GET["agent"];
		$SCION->row = $_GET["row"]; //working on this displayed row
		$SCION->path = array();
		switch ($SCION->agent) {
		case "BN": //button => adding/updating hours
			if ($SCION->row == 0) { //adding
				$SCION->path = array(ACCOUNT_DISP,
									 EVENT_DISP,
									 DATE_DISP,
									 COMMENTS_DISP);
			}
			$SCION->path[] = SESSIONS_DISP;
			break;
		case "AC": //account
			$SCION->path[] = ACCOUNT_DISP;
			break;
		case "EV": //event
			$SCION->path[] = EVENT_DISP;
			break;
		case "CM": //comment
			update_comment($SCION, $response);
			echo $response;
			break 3; //break out of here and the SCION state gate
		}
		$SCION->path[] = BUTTON_DISP;
		$response .= "document.getElementById('BN_".$SCION->row."')";
		$response .= ".innerHTML = \"<button type='button' name='btnReset' onclick='Reset()'>Cancel</button>\";\n";
		$SCION->status = array_shift($SCION->path);
		break 1; //go back around
	case ACCOUNT_DISP:
		if (account_send($SCION, $response) == 1) {
			account_select( $SCION, $response, $SCION->account_id);
			$SCION->status = array_shift($SCION->path);
			break 1; //go back around
		}
		$SCION->status = ACCOUNT_PICK;
		echo $response;
		break 2; //break out
	case ACCOUNT_PICK:
		account_select($SCION, $response);
		$SCION->status = array_shift($SCION->path);
		break 1; //go back around
	case EVENT_DISP:
		if (event_send($SCION, $response) == 1) {
			event_select($SCION, $response, $SCION->event_id);
			$SCION->status = array_shift($SCION->path);
			break 1; //go back around
		}
		$SCION->status = EVENT_PICK;
		echo $response;
		break 2; //break out
	case EVENT_PICK:
		event_select($SCION, $response);
		$SCION->status = array_shift($SCION->path);
		break 1; //go back around
	case DATE_DISP:
		include_once "callback/date_list.php";
		date_send($SCION, $response);
		$SCION->status = DATE_PICK;
		echo $response;
		break 2; //break out
	case DATE_PICK:
		include_once "callback/date_list.php";
		date_select($SCION, $response);
		$SCION->status = array_shift($SCION->path);
		break 1; //go back around
	case COMMENTS_DISP:
		include_once "callback/comments.php";
		comments_send($SCION, $response);
		$SCION->status = COMMENTS_PICK;
		echo $response;
		break 2; //break out
	case COMMENTS_PICK:
		//nothing to do
		$SCION->status = array_shift($SCION->path);
		break 1; //go back around
	case SESSIONS_DISP:	//Info input starting with sessions
		input_send($SCION, $response);
		$SCION->status = array_shift($SCION->path);
		break 1; //go back around
	case BUTTON_DISP:
		include_once "callback/buttons.php";
		button_send($SCION, $response);
		echo $response;
		$SCION->status = STATE::CHANGE;
		break 2; //break out
	case STATE::CHANGE:
		changes($SCION, $response); //DO IT!
		echo $response;
		break 2; //break out
	default:
		throw_the_bum_out(NULL,"Evicted(".__LINE__."): error");
	} } //while & switch
	$SCION->push();

	break 2;
default:
	throw_the_bum_out(NULL,"Evicted(".__LINE__."): Invalid state=".$_STATE->status);
} } //while & switch
//End Main State Gate

function set_state(&$dates) {
	global $_STATE;

	$_STATE->from_date = clone($dates->from);
	$_STATE->to_date = clone($dates->to);

	$_STATE->columns[COL_COUNT] = date_diff($_STATE->from_date, $_STATE->to_date)->days + 1;
	if ($_STATE->columns[COL_COUNT] > 60) {
		$_STATE->from_date = clone $_STATE->to_date;
		$_STATE->from_date->modify("-59 day");
		$_STATE->msgStatus .= "Max 60 days allowed; From Date modified accordingly";
		$_STATE->columns[COL_COUNT] = 60;
	}

	switch ($dates->checked) {
	case "w":
		$_STATE->heading .= "<br>for the week of ".$_STATE->from_date->format('Y-m-d').
							" to ".$_STATE->to_date->format('Y-m-d');
		break;
	case "m":
		$_STATE->heading .= "<br>for the month of ".$_STATE->from_date->format("M-Y");
		break;
	default:
		$_STATE->heading .= "<br>for dates from ".$_STATE->from_date->format('Y-m-d').
							" to ".$_STATE->to_date->format('Y-m-d');
	}
	return true;
}

function set_closedCols() {
	global $_DB, $_STATE;

	$sql = "SELECT inactive_asof, close_date FROM ".$_DB->prefix."a10_project
			WHERE project_id=".$_STATE->project_id.";";
	$row = $_DB->query($sql)->fetch(PDO::FETCH_ASSOC);
	$inactive = $row["inactive_asof"];

	$close = new DateTime($row["close_date"]);
	if ($_STATE->from_date > $close) {
		$_STATE->columns[COL_OPEN] = 0;
	} elseif ($_STATE->to_date <= $close) {
		$_STATE->columns[COL_OPEN] = $_STATE->columns[COL_COUNT]; //all closed
	} else {
		$_STATE->columns[COL_OPEN] = date_diff($_STATE->from_date, $close)->days + 1;
	}

	if (is_null($inactive)) {
		$_STATE->columns[COL_INACTIVE] = $_STATE->columns[COL_COUNT]; //none inactive
		return;
	}
	$inactive = new DateTime($inactive);
	if ($_STATE->to_date < $inactive) {
		$_STATE->columns[COL_INACTIVE] = $_STATE->columns[COL_COUNT]; //none inactive
		return;
	}
	$_STATE->columns[COL_AGENT] = "project";
	if ($_STATE->from_date >= $inactive) {
		$_STATE->columns[COL_INACTIVE] = 0; //all closed
	} else {
		$_STATE->columns[COL_INACTIVE] = date_diff($_STATE->from_date,$inactive)->days;
	}
}

function get_projects($person_id) { //projects connected to this person
	global $_STATE, $_DB, $_PERMITS;

	if ($_PERMITS->can_pass(PERMITS::_SUPERUSER)) return array(0); //superuser gets all
	$sql = "SELECT a10.project_id, a10.inactive_asof FROM ".$_DB->prefix."a10_project AS a10
			INNER JOIN (
				SELECT c02.project_idref FROM ".$_DB->prefix."c02_rate AS c02
					INNER JOIN ".$_DB->prefix."c00_person AS c00 ON c00.person_id = c02.person_idref
					WHERE c00.person_id = ".$person_id." GROUP BY c02.project_idref
				) AS c02 ON c02.project_idref = a10.project_id
			WHERE (organization_idref=".$_SESSION["organization_id"].")
			ORDER BY timestamp;";
	$stmt = $_DB->query($sql);
	$today = COM_NOW();
	$projects = array();
	while ($row = $stmt->fetchObject()) {
//		if (!$_PERMITS->can_pass("edit_logs")) {
//			if (!is_null($row->inactive_asof)) {
//				if (new DateTime($row->inactive_asof) <= $today) continue;
//			}
//		}
		$projects[] = $row->project_id;
	}
	$stmt->closeCursor();
	return $projects;
}

function log_list(&$state, $findrow=0) {
	global $_DB, $_PERMITS;

	$state->records = array();

	$sql = "";
	if (!$_PERMITS->can_pass("project_logs")) $sql = "(b10.person_idref=".$_SESSION["person_id"].") AND ";
	$sql = "SELECT b10.eventlog_id, b10.logdate, b10.session_count, b10.attendance, b10.comments,
			a30.event_id, a30.name AS event, a30.description AS event_desc, a30.inactive_asof AS event_inactive_asof,
			a10.project_id, a10.name AS project, a10.description AS project_desc,
			a21.account_id, a21.name AS account, a21.description AS account_desc, a21.inactive_asof AS account_inactive_asof,
			a00.organization_id
			FROM ".$_DB->prefix."b10_eventlog AS b10
			JOIN ".$_DB->prefix."a30_event AS a30 ON a30.event_id = b10.event_idref
			JOIN ".$_DB->prefix."a10_project AS a10 ON a10.project_id = a30.project_idref
			JOIN ".$_DB->prefix."a00_organization AS a00 ON a00.organization_id = a10.organization_idref
			JOIN ".$_DB->prefix."a21_account AS a21 ON a21.account_id = b10.account_idref
			WHERE ".$sql."(project_id=".$state->project_id.")
			AND (logdate BETWEEN :fromdate AND :todate)
			ORDER BY logdate;";
	$stmt = $_DB->prepare($sql);
	$stmt->bindValue(':fromdate', $state->from_date->format('Y-m-d'), db_connect::PARAM_DATE);
	$stmt->bindValue(':todate', $state->to_date->format('Y-m-d'), db_connect::PARAM_DATE);
	$stmt->execute();
	if (!($row = $stmt->fetchObject())) {
		$stmt->closeCursor();
		return;
	}
	$row_count = 0;
	do {
		++$row_count;
		if (($findrow > 0) && ($row_count > $findrow)) break; //get only this row
		if ($row_count >= $findrow) { //makes $findrow the first in the array
			$row->logdate = new DateTime($row->logdate);
			$record = array(
				"ID" =>			$row->eventlog_id,
				"logdate" =>	$row->logdate,
				"session_count" => $row->session_count,
				"attendance" =>	$row->attendance,
				"comments" =>	substr($row->comments,0,25),
				"row" =>		$row_count, //1 rel - 0 indicates add row
				"column" =>		date_diff($state->from_date, $row->logdate)->days, //tabular column (0 rel)
				"account" =>	($row->account == "*")?"":substr($row->account.": ".$row->account_desc,0,25),
				"account_id" =>	$row->account_id,
				"event" =>		substr($row->event.": ".$row->event_desc,0,25),
				"event_id" =>	$row->event_id,
			);
			foreach (array("account","event") as $name) {
				$item = $name."_inactive_asof";
				if (is_null($row->{$item})) continue;
				if ((new DateTime($row->{$item})) <= $state->to_date) {
						$record[$name] .= "<br>inactive as of (".$row->{$item}.")";
				}
			}
			$state->records[strval($row->eventlog_id)] = $record;
		}
	} while ($row = $stmt->fetchObject());
	$stmt->closeCursor();
}

function log_put() {
	global $_DB, $_STATE, $_PERMITS;
	global $version;

	$sql = "SELECT name FROM ".$_DB->prefix."a00_organization
			WHERE organization_id=".$_SESSION["organization_id"].";";
	$row = $_DB->query($sql)->fetchObject();
	$orgname = $row->name;

	$from = $_STATE->from_date->format('Y-m-d');
	$to = $_STATE->to_date->format('Y-m-d');

	$filename = "eventlog_".$orgname."_".$from."_to_".$to.".csv"; //for file_put...
	require_once "file_put.php";

	$out = fopen('php://output', 'w');

	$outline = array();
	$outline[] = "eventlog";
	$outline[] = $version;
	$outline[] = $from;
	$outline[] = $to;
	$outline[] = $orgname;
	fputcsv($out, $outline); //ID row

	$sql = "";
	if (!$_PERMITS->can_pass("project_logs")) $sql = "(b10.person_idref=".$_SESSION["person_id"].") AND ";
	$sql = "SELECT b10.eventlog_id, b10.logdate, b10.session_count, b10.attendance,
			a30.event_id, a30.name AS event, a30.description AS event_desc, a30.project_idref AS project_id,
			a21.account_id, a21.name AS account, a21.description AS account_desc,
			b10.comments
			FROM ".$_DB->prefix."b10_eventlog AS b10
			JOIN ".$_DB->prefix."a30_event AS a30 ON a30.event_id = b10.event_idref
			JOIN ".$_DB->prefix."a21_account AS a21 ON a21.account_id = b10.account_idref
			WHERE ".$sql."(a30.project_idref IN (".implode($_STATE->project_ids,",")."))
			AND (logdate BETWEEN :fromdate AND :todate)
			ORDER BY logdate, event_id, account_id;";
	$stmt = $_DB->prepare($sql);
	$stmt->bindValue(':fromdate', $from, db_connect::PARAM_DATE);
	$stmt->bindValue(':todate', $to, db_connect::PARAM_DATE);
	$stmt->execute();
	if (!($row = $stmt->fetch(PDO::FETCH_ASSOC))) {
		$_STATE->msgStatus = "No logs were downloaded";
		return;
	}
	$outline = array();
	$fields = "";
	foreach ($row as $name=>$value) { //headings
		if (substr($name,-3) == "_id") continue; //don't send id fields
		$outline[] = $name;
		$fields .= ",".$name;
	}
	fputcsv($out, $outline);

	foreach ($_STATE->project_ids as $project_id) {
		$sql = "SELECT name, description FROM ".$_DB->prefix."a10_project
				WHERE project_id=".$project_id.";";
		$row = $_DB->query($sql)->fetchObject();
		$outline = array();
		$outline[] = "project";
		$outline[] = $row->name;
		$outline[] = $row->description;
		fputcsv($out, $outline); //project row

		$sql = "";
		if (!$_PERMITS->can_pass("project_logs")) $sql = "(b10.person_idref=".$_SESSION["person_id"].") AND ";
		$sql = "SELECT b10.logdate, b10.session_count, b10.attendance,
			a30.name AS event, a30.description AS event_desc,
			a21.name AS account, a21.description AS account_desc,
			b10.comments
			FROM ".$_DB->prefix."b10_eventlog AS b10
			JOIN ".$_DB->prefix."a30_event AS a30 ON a30.event_id = b10.event_idref
			JOIN ".$_DB->prefix."a21_account AS a21 ON a21.account_id = b10.account_idref
			WHERE ".$sql."(a30.project_idref=".$project_id.")
			AND (logdate BETWEEN :fromdate AND :todate)
			ORDER BY logdate;";
		$stmt = $_DB->prepare($sql);
		$stmt->bindValue(':fromdate', $from, db_connect::PARAM_DATE);
		$stmt->bindValue(':todate', $to, db_connect::PARAM_DATE);
		$stmt->execute();
		while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
			fputcsv($out, $row);
		}
		$stmt->closeCursor();
	} //end projects

	$outline = array();
	$outline[] = "project";
	$outline[] = "<end>";
	fputcsv($out, $outline); //project row

	fclose($out);

	FP_end();
	$_STATE->msgStatus = "Logs successfully downloaded";
}

//	CALL BACK SECTION
//These routines handle the various server 'call-backs' not included from lib/callback.
//A 'call-back' leaves the page intact while a request is sent back to the server and the response then handled via script.

function cell_desc(&$state) {
	global $_DB;

	$field = "description";
	switch ($_GET["getdesc"]) {
	case "EV":
		$table = $_DB->prefix."a30_event";
		$id = "event_id";
		break;
	case "AC":
		$table = $_DB->prefix."a21_account";
		$id = "account_id";
		break;
	case "CM":
		$field = "comments";
		$table = $_DB->prefix."b10_eventlog";
		$id = "eventlog_id";
		break;
	default:
		throw_the_bum_out(NULL,"Evicted(".__LINE__."): invalid cell ID ".$_GET["getdesc"], true);
	}
	$key = $_GET["ID"];
	$sql = "SELECT ".$field." FROM ".$table." WHERE ".$id."=:key;";
	$stmt = $_DB->prepare($sql);
	$stmt->bindValue(":key", $key, PDO::PARAM_INT);
	$stmt->execute();
	$row = $stmt->fetchObject();
	echo "@got_desc('".$row->{$field}."');\n";
}

//Populate the event pulldown selection list then collect the response via server call-back:
function event_list(&$state) {
	global $_DB;

	$state->records = array();

	$sql = "SELECT * FROM ".$_DB->prefix."a30_event
			WHERE project_idref=".$state->project_id."
			ORDER BY name;";
	$stmt = $_DB->query($sql);
	while ($row = $stmt->fetchObject()) {
		$element = array();
		if ($row->name == "*") {
			$element[0] = "N/A";
		} else {
			$element[0] = substr($row->name.": ".$row->description,0,25);
		}
		$element[1] = "";
		if (!is_null($row->inactive_asof)) {
			$inact = new DateTime($row->inactive_asof);
			if ($inact <= $state->from_date) continue;
			if ($state->to_date >= $inact)
				$element[1] = $row->inactive_asof;
		}
		$state->records[strval($row->event_id)] = $element;
	}
	$stmt->closeCursor();
}

function event_send(&$state, &$HTML) {

	event_list($state);

	$HTML .= "//Events...\n";
	if (count($state->records) == 1) {
		reset($state->records);
		$solo = each($state->records); //get first available "key","value" pair
		$state->event_id = intval($solo["key"]); //event_select wants to see this

	} else {
    	$HTML .= "document.getElementById('msgGreet_ID').innerHTML = 'Select the ".$state->title_singular."';\n";
		$HTML .= "fill = \"<select name='selEvent' id='selEvent' size='1' onchange='proceed(this.parentNode,this.options[this.selectedIndex].value)'>\";\n";
		foreach($state->records as $value => $name) {
			$title = $name[1];
			$opacity = "1.0";
			if ($title != "") {
				$date = explode("-", $title);
				$date[1] -= 1; //month is 0 rel in JS
				$title = " title='inactive as of ".$title."'";
				$opacity = "0.5";
			}
			$HTML .= "fill += \"<option ".$title." value='".$value."' style='opacity:".$opacity."'>".$name[0]."\";\n";
		}
		$HTML .= "fill += \"</select>\";\n";
		$HTML .= "cell = document.getElementById('EV_".$state->row."');\n";
		$HTML .= "cell.innerHTML = fill;\n";
		$HTML .= "document.getElementById('selEvent').selectedIndex=-1;\n";
	}

	return count($state->records);
}

function event_select(&$state, &$HTML, $rec=-1) {

	if ($rec < 0) { //checking returned
		if (!isset($_GET["row"])) return;
		$rec = strval($_GET["row"]);
	}

	event_list($state); //restore the record list
	if (!array_key_exists($rec, $state->records)) {
		throw_the_bum_out(NULL,"Evicted(".__LINE__."): invalid event id ".$rec,true);
	}
	$record = $state->records[$rec];
	if ($record[1] != "") {
		$inactive = new DateTime($record[1]);
		$diff = date_diff($state->from_date, $inactive)->days;
		if ($diff < $state->columns[COL_INACTIVE]) {
			$state->columns[COL_INACTIVE] = $diff;
			$state->columns[COL_AGENT] = "event";
		}
		$record[0] .= "<br>(inactive as of ".$record[1].")";
	}
	$HTML .= "cell = document.getElementById('EV_".$state->row."');\n";
	$HTML .= "cell.innerHTML = '".$record[0]."';\n";
	$state->event_id = $rec;
	$state->msgStatus = "";
}

//Populate the account pulldown selection list then collect the response via server call-back:
function account_list(&$state) {
	global $_DB;

	$state->records = array();

	$sql = "SELECT * FROM ".$_DB->prefix."a21_account
			WHERE accounting_idref=".$state->accounting_id." ORDER BY description;";
	$stmt = $_DB->query($sql);
	while ($row = $stmt->fetchObject()) {
		$element = array();
		if ($row->name == "*") {
			$element[0] = "N/A";
		} else {
			$element[0] = substr($row->name.": ".$row->description,0,25);
		}
		$element[1] = "";
		if (!is_null($row->inactive_asof)) {
			$inact = new DateTime($row->inactive_asof);
			if ($inact <= $state->from_date) continue;
			if ($state->to_date >= $inact)
				$element[1] = $row->inactive_asof;
		}
		$state->records[strval($row->account_id)] = $element;
	}
	$stmt->closeCursor();
}

function account_send(&$state, &$HTML) {

	account_list($state);

	$HTML .= "//Accounts...\n";
	if (count($state->records) == 1) {
		reset($state->records);
		$solo = each($state->records); //get first available "key","value" pair
		$state->account_id = intval($solo["key"]); //account_select wants to see this

	} else {
    	$HTML .= "document.getElementById('msgGreet_ID').innerHTML = 'Select the ".$state->accounting."';\n";
		$HTML .= "fill = \"<select name='selAccount' id='selAccount' size='1' onchange='proceed(this.parentNode,this.options[this.selectedIndex].value)'>\";\n";
		foreach($state->records as $value => $name) {
			$title = $name[1];
			$opacity = "1.0";
			if ($title != "") {
				$date = explode("-", $title);
				$date[1] -= 1; //month is 0 rel in JS
				$title = " title='inactive as of ".$title."'";
				$opacity = "0.5";
			}
			$HTML .= "fill += \"<option ".$title." value='".$value."' style='opacity:".$opacity."'>".$name[0]."\";\n";
		}
		$HTML .= "fill += \"</select>\";\n";
		$HTML .= "cell = document.getElementById('AC_".$state->row."');\n";
		$HTML .= "cell.innerHTML = fill;\n";
		$HTML .= "document.getElementById('selAccount').selectedIndex=-1;\n";
	}

	return count($state->records);
}

function account_select(&$state, &$HTML, $rec=-1) {

	if ($rec < 0) { //checking returned
		if (!isset($_GET["row"])) return;
		$rec = strval($_GET["row"]);
	}

	account_list($state); //restore the record list
	if (!array_key_exists($rec, $state->records)) {
		throw_the_bum_out(NULL,"Evicted(".__LINE__."): invalid accounting id ".$rec,true);
	}
	$record = $state->records[$rec];
	if ($record[1] != "") {
		$inactive = new DateTime($record[1]);
		$diff = date_diff($state->from_date, $inactive)->days;
		if ($diff < $_STATE->columns[COL_INACTIVE]) {
			$_STATE->columns[COL_INACTIVE] = $diff;
			$_STATE->columns[COL_AGENT] = "account";
		}
		$record[0] .= "<br>(inactive as of ".$record[1].")";
	}
	$HTML .= "cell = document.getElementById('AC_".$state->row."');\n";
	$HTML .= "cell.innerHTML = '".$record[0]."';\n";
	$state->account_id = $rec;
	$state->msgStatus = "";
}

//Send the counts text entry fields via server call-back:
function input_send(&$state, &$HTML) {

	if ($state->row == 0) { //0 is add row
		$sessions = 0;
		$attendance = 0;
	} else {
		log_list($state, $state->row); //find row specific stuff
		$record = reset($state->records);
		$sessions = $record["session_count"];
		$attendance = $record["attendance"];
	}

	$HTML .= "//Sessions...\n";
	$HTML .= "fill = \"<input type='text' name='txtSessions' id='txtSessions_ID' size='3'";
	$HTML .= " maxlength='3' class='number' onblur='return audit_count(this,4)' value='".$sessions."'>\";\n";
	$HTML .= "document.getElementById('SN_".$state->row."').innerHTML = fill;\n";

	$HTML .= "//Attendance...\n";
	$HTML .= "cell = document.getElementById('AD_".$state->row."');\n";
	$HTML .= "fill = \"<input type='text' name='txtAttendance' id='txtAttendance_ID' size='5'";
	$HTML .= " maxlength='5' class='number' onblur='return audit_count(this,99)' value='".$attendance."'>\";\n";
	$HTML .= "cell = document.getElementById('AD_".$state->row."').innerHTML = fill;\n";

}

//Audit the input for update/add:
function audit_counts(&$state) {

	$state->msgStatus = "!Invalid counts";
	if (!isset($_POST["sessions"]) || !isset($_POST["attendance"])) return false;
$state->msgStatus .= ".";
	$sessions = $_POST["sessions"];
	$attendance = $_POST["attendance"];

	if (!is_numeric($sessions) || !is_numeric($attendance)) return false;
$state->msgStatus .= ".";
	if (($sessions > 24) || ($attendance > 2400)) return false;
$state->msgStatus .= ".";
	if (($state->row == 0) && ($sessions == 0)) return false;

	return true;

}

//DB changes for update/add:
function add_log(&$state, &$logdate) {
	global $_DB;

	$sql = "INSERT INTO ".$_DB->prefix."b10_eventlog
			(event_idref, person_idref, account_idref, session_count, attendance, logdate, comments)
			VALUES (".$state->event_id.", ".$_SESSION["person_id"].", ".$state->account_id.", ".
			$_POST["sessions"].", ".$_POST["attendance"].", '".
			$logdate->format('Y-m-d')."', :comments);";
	$stmt = $_DB->prepare($sql);
	$stmt->bindValue(':comments',COM_input_edit("comments"),PDO::PARAM_STR);
	$stmt->execute();

	$state->msgStatus = "-"; //tell server_call to reset page
}

function update_log(&$state) {
	global $_DB;

	$sql = "UPDATE ".$_DB->prefix."b10_eventlog
			SET session_count=".$_POST["sessions"].", attendance=".$_POST["attendance"].",
			comments=:comments
			WHERE eventlog_id=".$state->recID.";";
	$stmt = $_DB->prepare($sql);
	$stmt->bindValue(':comments',COM_input_edit("comments"),PDO::PARAM_STR);
	$stmt->execute();

	$state->msgStatus = "-"; //tell server_call to reset page
}

function delete_log(&$state) {
	global $_DB;

	$sql = "DELETE FROM ".$_DB->prefix."b10_eventlog
			WHERE eventlog_id=".$state->recID.";";
	$_DB->exec($sql);

	$state->msgStatus = "-"; //tell server_call to reset page
}

function update_comment(&$state, &$response) {
	global $_DB;

	log_list($state, $state->row); //find this row's records
	$record = reset($state->records);
	if ($record["column"] >= $state->columns[COL_OPEN]) {
		$sql = "UPDATE ".$_DB->prefix."b10_eventlog
				SET comments='".COM_string_decode($_GET["com"],-1)."' WHERE eventlog_id=".$record['ID'].";";
		$_DB->exec($sql);
	}

	$response = "."; //tell server_call we're done
	return true;
}

function new_counts(&$state) {

	log_list($state, $state->row); //find this row's records

	$state->recID = 0;
	if ($state->row > 0) { //updating (0 is add row)
		$record = reset($state->records);
		if ($record["ID"] == 0)
			throw_the_bum_out(NULL,"Evicted(".__LINE__."): invalid POST 1",true);
		$state->recID = $record["ID"];
	}

	if (!audit_counts($state)) return;

	if (substr($_POST["comments"],0,1) == "\n") $_POST["comments"] = "---";
	$logdate = clone $state->from_date;
	if ($state->row == 0) { //adding
		add_log($state, $state->logdate);
		return;
	}

	if (($record["event_id"] != $_POST["event"]) ||
		($record["account_id"] != $_POST["account"])) {
		throw_the_bum_out(NULL,"Evicted(".__LINE__."): invalid record ".$recID,true);
	}

	if ($_POST["sessions"] == 0) {
		delete_log($state);
	} else {
		update_log($state);
	}

}

function change_account(&$state) {
	global $_DB;

	account_list($state); //restore the record list
	if (!array_key_exists($state->account_id, $state->records)) {
		throw_the_bum_out(NULL,"Evicted(".__LINE__."): invalid accounting id ".$state->account_id,true);
	}
	log_list($state, $state->row); //find this row's records
	$record = reset($state->records);
	$old_account = $record["account_id"];
	if ($state->account_id == $old_account) return;

	foreach ($state->records as $ID=>$record) {
		if ($record["column"] < $state->columns[COL_OPEN]) continue;
		$sql = "UPDATE ".$_DB->prefix."b10_eventlog
				SET account_idref=".$state->account_id." WHERE eventlog_id=".$ID.";";
		$_DB->exec($sql);
	}
}

function change_event(&$state) {
	global $_DB;

	event_list($state); //restore the record list
	if (!array_key_exists($state->event_id, $state->records)) {
		throw_the_bum_out(NULL,"Evicted(".__LINE__."): invalid eventing id ".$state->event_id,true);
	}
	log_list($state, $state->row); //find this row's records
	$record = reset($state->records);
	$old_event = $record["event_id"];
	if ($state->event_id == $old_event) return;

	foreach ($state->records as $ID=>$record) {
		if ($record["column"] < $state->columns[COL_OPEN]) continue;
		$sql = "UPDATE ".$_DB->prefix."b10_eventlog
				SET event_idref=".$state->event_id." WHERE eventlog_id=".$ID.";";
		$_DB->exec($sql);
	}
}

function changes(&$state, &$response) {

	$response = "-"; //initialize to reset page

	switch ($state->agent) {
	case "BN": //button => adding/updating hours
		new_counts($state);
		$response = $state->msgStatus;
		break;
	case "AC": //account
		change_account($state);
		break;
	case "EV": //event
		change_event($state);
		break;
	}
}

//-------end function code; begin HTML------------

EX_pageStart(); //standard HTML page start stuff - insert SCRIPTS here

echo "<script type='text/javascript' src='".$EX_SCRIPTS."/call_server.js'></script>\n";
if ($_STATE->status == SELECT_SPECS) {
	echo "<script type='text/javascript' src='".$EX_SCRIPTS."/calendar.js'></script>\n";
} else if ($_STATE->status > SELECT_SPECS) {
	echo "<script type='text/javascript' src='".$EX_SCRIPTS."/eventlog.js'></script>\n";
}

EX_pageHead(); //standard page headings - after any scripts

//forms and display depend on process state; note, however, that the state was probably changed after entering
//the Main State Gate so this switch will see the next state in the process:
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
  <tr><td>&nbsp</td><td colspan="3">- - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - </td></tr>

  <tr><td colspan='3'>
<?php
	echo $calendar->create("h"); //horiz
?>
  </td></tr>

  <tr>
    <td>&nbsp</td>
    <td colspan="2" style="text-align:left">
      <button name="btnDates" type="button" value="<?php echo $_SESSION["person_id"]; ?>" onclick="this.form.submit()">Continue</button>
    </td>
  </tr>
</table>
</form>
<div id="msgStatus_ID"><?php echo $_STATE->msgStatus ?></div>

<?php //end SELECT_SPECS status ----END STATE: EXITING FROM PROCESS----
	break;
default: //list the hours and allow new entry:
?>
<div id="divPopopen_ID" class="popopen">
  Enter comments:<br>
  <textarea name="txtComments" id="txtComments_ID" rows="2" cols="50"></textarea><br>
  <input type="button" onclick="save_comments(true)" value="OK">
  <input type="button" id="cancelPop" onclick="save_comments(false)" value="cancel">
</div>
<?php
	require_once "project_select.php";
	$projects = unserialize($_STATE->project_select);
	echo $projects->tabs();
?>
<table align="center" id="tblLog" cellpadding="4" border="2">
  <tr>
    <th width='100'>&nbsp;</th>
    <th width='140'><?php echo $_STATE->accounting; ?></th>
    <th width='140'><?php echo $_STATE->title_singular; ?></th>
    <th width='74'>Date</th>
    <th width='30'>Sessions</th>
    <th width='30'>Attendance</th>
    <th width='140'>Comments</th>
  </tr>
  <tr id="add">
    <td id="BN_0" data-recid="0" title="Click to add new <?php echo $_STATE->title_singular; ?> counts">
      <img src="<?php echo $_SESSION["_SITE_CONF"]["_REDIRECT"]; ?>/images/add.png"></td>
    <td id="AC_0" data-recid="0"></td>
    <td id="EV_0" data-recid="0"></td>
    <td id='DT_0' data-recid='0' class='date'></td>
	<td id='SN_0' data-recid='0'></td>
	<td id='AD_0' data-recid='0'></td>
    <td id="CM_0" data-recid="0" data-value="\"></td>
  </tr>
<?php
function onerow(&$header, &$logs) {
	global $_STATE, $_PERMITS;

	$row = $header["row"];
	$open = " id='BN_".$row."' data-recid='".$row."' class=seq";
//	if ($_STATE->mode == "l") { //list mode
		if ($header["column"] < $_STATE->columns[COL_OPEN]) {
			echo "  <tr class='closed'>\n";
			if (!$_PERMITS->can_pass("edit_logs")) {
				echo "    <td title='closed to new input'";
			} else {
				echo "    <td".$open." title='PROJECT IS CLOSED; edit with care!'";
			}
		} else {
			echo "  <tr>\n";
			echo "    <td".$open;
		}
//	}
	echo ">".$row."</td>\n";
	echo "    <td id='AC_".$row."' data-recid='".$header["account_id"]."'>".$header["account"]."</td>\n";
	echo "    <td id='EV_".$row."' data-recid='".$header["event_id"]."'>".$header["event"]."</td>\n";
	echo "    <td id='DT_".$row."' class='date'>".$header["logdate"]->format("Y-m-d")."</td>\n";
	echo "    <td id='SN_".$row."' data-recid='".$header["ID"]."' class='number'>".$header["session_count"]."</td>\n";
	echo "    <td id='AD_".$row."' data-recid='".$header["ID"]."' class='number'>".$header["attendance"]."</td>\n";
	echo "    <td id='CM_".$row."' data-recid='".$header["ID"]."' data-value='\\'>".
		  $header["comments"]."</td>\n";
	echo "  </tr>\n";
} //end function onerow()

reset($_STATE->records);
$totals = array(); //totals: sessions, attendance
$logs = array();
for ($ndx=0; $ndx<$_STATE->columns[COL_COUNT]; $ndx++) { //save one row's worth of data:
	$totals[] = array(0,0);
	$logs[] = array(0,0,0); //$logs[][0,1]=>sessions/attendance, $logs[][2]=>timelog_id
}
//if ($_STATE->mode == "l") { //---begin LIST STYLE---
	foreach ($_STATE->records AS $ID=>$record) {
		onerow($record, $logs);
		$totals[$record["column"]][0] += $record["session_count"];
		$totals[$record["column"]][1] += $record["attendance"];
	}
	$grand = array(0,0);
	for ($ndx=0; $ndx<$_STATE->columns[COL_COUNT]; $ndx++) {
		$grand[0] += $totals[$ndx][0];
		$grand[1] += $totals[$ndx][1];
	}
//}
?>
  <tr>
    <td colspan="3"></td>
    <td>Totals:</td><td class='number'><?php echo $grand[0]; ?></td>
    <td class='number'><?php echo $grand[1]; ?></td>
    <td></td>
  </tr>
</table>

<?php
if ($_PERMITS->can_pass("project_logs")) { ?>
<form method="post" name="frmAction" id="frmAction_ID" action="<?php echo $_SERVER['SCRIPT_NAME']; ?>">
<br>You can
<button name="btnPut" type="submit" value="<?php echo $_SESSION["person_id"]; ?>" title="click here to download">Download</button>
this data for import into the timesheet template<br>(check your browser preferences for where the downloaded file will go)
</form>
<?php
} ?>

<div id="msgStatus_ID" class="status"><?php echo $_STATE->msgStatus ?></div>
<?php //end select ($_STATE->status) ----END STATE: EXITING FROM PROCESS----
}

EX_pageEnd(); //standard end of page stuff
?>

