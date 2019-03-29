<?php
//copyright 2015-2017,2019 C.D.Price. Licensed under Apache License, Version 2.0
//See license text at http://www.apache.org/licenses/LICENSE-2.0

require_once "lib/field_edit.php";

//The Main State Gate cases:
define('LIST_PERSONS',		STATE::INIT);
define('SELECT_PERSON',			LIST_PERSONS + 1);
define('SELECTED_PERSON',		LIST_PERSONS + 2);
define('LIST_PROJECTS',		STATE::INIT + 10);
define('SELECT_PROJECT',		LIST_PROJECTS + 1);
define('SELECTED_PROJECT',		LIST_PROJECTS + 2);
define('SHOW_SPECS',		STATE::INIT + 20);
define('SELECT_SPECS',			SHOW_SPECS + 1);
define('SELECTED_SPECS',		SHOW_SPECS + 2);
define('SHEET_DISP',		STATE::INIT + 30);
//SCION State Gate cases:
define ('TASK_DISP',		STATE::SELECT);
define ('TASK_PICK',		STATE::SELECTED);
define ('SUBTASK_DISP',		STATE::SELECT + 1);
define ('SUBTASK_PICK',		STATE::SELECTED + 1);
define ('ACCOUNT_DISP',		STATE::SELECT + 2);
define ('ACCOUNT_PICK',		STATE::SELECTED + 2);
define ('TYPE_DISP',		STATE::SELECT + 3);
define ('TYPE_PICK',		STATE::SELECTED + 3);
define ('DATE_DISP',		STATE::SELECT + 4);
define ('DATE_PICK',		STATE::SELECTED + 4);
define ('ACTIVITY_DISP',	STATE::SELECT + 5);
define ('ACTIVITY_PICK',	STATE::SELECTED + 5);
define ('AMOUNT_DISP',		STATE::SELECT + 6);
define ('BUTTON_DISP',		STATE::SELECT + 7);

//Define $_STATE->columns array: (a 'column' corresponds to one day within the date range)
define ('COL_COUNT', 0); //total columns (1 rel)
define ('COL_OPEN',1); //first open column (0 rel)
define ('COL_INACTIVE',2); //first 'inactive' column (0 rel)
define ('COL_AGENT',3); //name of 'inactive' agent: 'project','task','subtask'

$ExpTypes = array("ca"=>"cash","bi"=>"billed","mi"=>"mileage"); //make it easy to alter types in the future

$version = "v2.0"; //goes with the downloaded expense file for client verification

//Main State Gate: (the while (1==1) allows a loop back through the switch using a 'break 1')
while (1==1) { switch ($_STATE->status) {
case LIST_PERSONS:
	$_STATE->person_id = 0;
	$_STATE->project_id = 0;
	$_STATE->accounting_id = 0;
	$_STATE->accounting = "";
	$_STATE->task_id = 0;
	$_STATE->subtask_id = 0;
	$_STATE->activity_id = 0;
	$_STATE->columns = array(1,0,0,"");
	$_STATE->type = "";
	$_STATE->mode = "t"; //tabular (list mode => "l")
	require_once "lib/person_select.php";
	$persons = new PERSON_SELECT(array(0),true); //everybody, multiple
	if (!$_EDIT) { //set by executive.php
		$persons->set_state($_SESSION["person_id"]);
		$_STATE->person_select = serialize($persons);
		$_STATE->init = LIST_PROJECTS;
		$_STATE->status = SELECTED_PERSON;
		break 1; //re-switch to SELECTED_PERSON
	}
	if (!$_PERMITS->can_pass("edit_logs")) throw_the_bum_out(NULL,"Evicted(".__LINE__."): no permit");
	$_STATE->person_select = serialize(clone($persons));
	if ($persons->selected) {
		$_STATE->init = LIST_PROJECTS;
		$_STATE->status = SELECTED_PERSON;
		break 1; //re-switch to SELECTED_PERSON
	}
	$_STATE->msgGreet = "Select the person whose logs are to be editted";
	$_STATE->status = SELECT_PERSON;
	break 2;
case SELECT_PERSON: //select the person whose logs are to be editted (person_id=0 is superduperuser)
	require_once "lib/person_select.php"; //catches $_GET list refresh
	$persons = unserialize($_STATE->person_select);
	$persons->set_state();
	$_STATE->person_select = serialize($persons);
case SELECTED_PERSON:

	$_STATE->status = LIST_PROJECTS; //our new starting point for goback
	$_STATE->replace(); //so loopback() can find it
case LIST_PROJECTS:
	require_once "lib/project_select.php";
	$projects = new PROJECT_SELECT(get_projects($_SESSION["person_id"]), true);
	$_STATE->project_select = serialize(clone($projects));
	if ($projects->selected) {
		$_STATE->init = SELECT_SPECS;
		$_STATE->status = SELECTED_PROJECT;
		break 1; //re-switch to SELECTED_PROJECT
	}
	$_STATE->msgGreet = "Select the ".ucfirst($projects->get_label("project"));
	$_STATE->backup = LIST_PERSONS;
	$_STATE->status = SELECT_PROJECT;
	break 2;
case SELECT_PROJECT: //select the project
	require_once "lib/project_select.php"; //catches $_GET list refresh (assumes break 2)
	$projects = unserialize($_STATE->project_select);
	$projects->set_state();
	$_STATE->project_select = serialize(clone($projects));
case SELECTED_PROJECT:
	$_STATE->project_name = $projects->selected_name();

	$_STATE->status = SHOW_SPECS; //our new starting point for goback
	$_STATE->replace(); //so loopback() can find it
case SHOW_SPECS:
	require_once "lib/date_select.php";
	$dates = new DATE_SELECT("wmp","p"); //show within week(w), month(m), period(p)(default)
	$_STATE->date_select = serialize(clone($dates));
	require_once "lib/calendar.php";
	$calendar = new CALENDAR(2, "FT"); //2 pages
	$_STATE->calendar = serialize(clone($calendar));
	$_STATE->msgGreet = $_STATE->project_name."<br>Select the date range";
	$_STATE->backup = LIST_PROJECTS; //set goback
	$_STATE->status = SELECT_SPECS;
	break 2;
case SELECT_SPECS: //set the from and to dates
	require_once "lib/calendar.php"; //catches $_GET refresh
	require_once "lib/date_select.php";
	$dates = unserialize($_STATE->date_select);
	if (!$dates->POST()) {
		$calendar = unserialize($_STATE->calendar);
		$_STATE->msgGreet = $_STATE->project_name."<br>Select the date range";
		break 2;
	}
	set_state($dates);
	$_STATE->status = SELECTED_SPECS; //for possible goback
	$_STATE->replace();
case SELECTED_SPECS:
	total_amounts($_STATE); //for all projects
	log_list($_STATE);
	set_closedCols(); //and 'mileage'
	$_STATE->msgGreet = "Log entry for ".$_STATE->person_name.
						"<br>To add or change amounts: click on the lefthand column";
	$_STATE->scion_start("SHEET"); //create the child state stack
	$_STATE->backup = SHOW_SPECS; //set goback
	$_STATE->status = SHEET_DISP;
	break 2;
case SHEET_DISP:
	if (isset($_GET["sheet"])) { //change displayed sheet
		$_STATE = $_STATE->loopback(SELECTED_SPECS);
		require_once "lib/project_select.php";
		$projects = unserialize($_STATE->project_select);
		$projects->set_state($_GET["sheet"]);
		$_STATE->project_select = serialize($projects);
		$_STATE->replace();
		break 1;
	}
	if (isset($_POST["selPerson"])) { //change displayed person
		$_STATE = $_STATE->loopback(SELECTED_SPECS);
		require_once "lib/person_select.php";
		$persons = unserialize($_STATE->person_select);
		$persons->set_state($_POST["selPerson"]);
		$_STATE->person_select = serialize($persons);
		$_STATE->replace();
		break 1;
	}
	if (isset($_POST["btnMode"])) { //switch modes
		$_STATE = $_STATE->loopback(SELECTED_SPECS);
		$_STATE->mode = ($_STATE->mode == "l")?"t":"l";
		$_STATE->replace();
		break 1;
	}
	if (isset($_GET["reset"])) {
		$_STATE = $_STATE->loopback(SELECTED_SPECS);
		break 1;
	}
	if (isset($_GET["getdesc"])) { //asking for the description of a cell
		cell_desc();;
		break 2;
	}
	if (isset($_POST["btnPut"])) { //asking for a download
		log_put();
		break 2;
	}
	if (!(isset($_GET["agent"]) || isset($_POST["row"])))
		throw_the_bum_out(NULL,"Evicted(".__LINE__."): GET/POST row not supplied");

	//Add/Update a row of the displayed sheet:
	$SCION = $_STATE->scion_pull(); //use the child thread
	$response = "@"; //initialized to do an eval
	while (1==1) { switch ($SCION->status) { //the SCION state gate
	case STATE::INIT:
		$SCION->agent = $_GET["agent"];
		$SCION->row = $_GET["row"]; //working on this displayed row
		$SCION->path = array();
		switch ($SCION->agent) {
		case "BN": //button => adding/updating amounts
			if ($SCION->row == 0) { //adding
				$SCION->path = array(TASK_DISP,
									 SUBTASK_DISP,
									 ACCOUNT_DISP,
									 TYPE_DISP,
									 ACTIVITY_DISP);
				if ($SCION->mode == "l") { //list style
					$SCION->path[] = DATE_DISP;
				}
			}
			$SCION->path[] = AMOUNT_DISP;
			break;
		case "TK": //task
			$SCION->path[] = TASK_DISP;
			$SCION->path[] = SUBTASK_DISP;
			break;
		case "ST": //subtask
			log_list($SCION, $SCION->row);
			$record = reset($SCION->records);
			$SCION->task_id = $record["task_id"];
			$SCION->path[] = SUBTASK_DISP;
			break;
		case "AC": //account
			$SCION->path[] = ACCOUNT_DISP;
			break;
		case "TP": //type
			$SCION->path[] = TYPE_DISP;
			break;
		case "AT": //activity
			if (isset($_GET["actupd"])) { //a direct update
				update_activity($SCION, $response);
				echo $response;
				break 3; //break out of here and the SCION state gate
			}
			log_list($SCION, $SCION->row);
			$record = reset($SCION->records);
			$SCION->subtask_id = $record["subtask_id"];
			$SCION->path[] = ACTIVITY_DISP;
			break;
		default:
			throw_the_bum_out(NULL,"Evicted(".__LINE__."): invalid agent ".$SCION->agent,true);
		}
		$SCION->path[] = BUTTON_DISP;
		$response .= "document.getElementById('BN_".$SCION->row."')";
		$response .= ".innerHTML = \"<button type='button' name='btnReset' onclick='Reset()'>Cancel</button>\";\n";
		$SCION->status = array_shift($SCION->path);
		break 1; //go back around
	case TASK_DISP:
		include_once "lib/callback/task.php";
		if (task_send($SCION, $response) == 1) {
			task_select($SCION, $response, $SCION->task_id);
			$SCION->status = array_shift($SCION->path);
			break 1; //go back around
		}
		$SCION->status = TASK_PICK;
		echo $response;
		break 2; //break out
	case TASK_PICK:
		include_once "lib/callback/task.php";
		task_select($SCION, $response);
		$SCION->status = array_shift($SCION->path);
		break 1; //go back around
	case SUBTASK_DISP:
		include_once "lib/callback/subtask.php";
		if (subtask_send($SCION, $response) == 1) {
			subtask_select($SCION, $response, $SCION->subtask_id);
			$SCION->status = array_shift($SCION->path);
			break 1; //go back around
		}
		$SCION->status = SUBTASK_PICK;
		echo $response;
		break 2; //break out
	case SUBTASK_PICK:
		include_once "lib/callback/subtask.php";
		subtask_select($SCION, $response);
		$SCION->status = array_shift($SCION->path);
		break 1; //go back around
	case ACCOUNT_DISP:
		include_once "lib/callback/account.php";
		if (account_send($SCION, $response) == 1) {
			account_select($SCION, $response, $SCION->account_id);
			$SCION->status = array_shift($SCION->path);
			break 1; //go back around
		}
		$SCION->status = ACCOUNT_PICK;
		echo $response;
		break 2; //break out
	case ACCOUNT_PICK:
		include_once "lib/callback/account.php";
		account_select($SCION, $response);
		$SCION->status = array_shift($SCION->path);
		break 1; //go back around
	case TYPE_DISP:
		type_send($SCION, $response);
		$SCION->status = TYPE_PICK;
		echo $response;
		break 2; //break out
	case TYPE_PICK:
		type_select($SCION, $response);
		$SCION->status = array_shift($SCION->path);
		break 1; //go back around
	case DATE_DISP:
		include_once "lib/callback/date_list.php";
		date_send($SCION, $response);
		$SCION->status = DATE_PICK;
		echo $response;
		break 2; //break out
	case DATE_PICK:
		include_once "lib/callback/date_list.php";
		date_select($SCION, $response);
		$SCION->status = array_shift($SCION->path);
		break 1; //go back around
	case ACTIVITY_DISP:
		activity_send($SCION, $response);
		$SCION->status = ACTIVITY_PICK;
		echo $response;
		break 2; //break out
	case ACTIVITY_PICK:
		activity_select($SCION, $response);
		$SCION->status = array_shift($SCION->path);
		break 1; //go back around
	case AMOUNT_DISP:
		amount_send($SCION, $response);
		$SCION->status = array_shift($SCION->path);
		break 1; //go back around
	case BUTTON_DISP:
		include_once "lib/callback/buttons.php";
		button_send($SCION, $response);
		echo $response;
		$SCION->status = STATE::CHANGE;
		break 2; //break out
	case STATE::CHANGE:
		changes($SCION, $response); //DO IT!
		$temp = STATE_pull($_STATE->thread,1);
		total_amounts($temp); //re-calculate for all projects
		$temp->replace();
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

	$from = new DATE_FIELD($_STATE->from_date);
	$to = new DATE_FIELD($_STATE->to_date);
	switch ($dates->checked) {
	case "w":
		$_STATE->heading .= ": for the week of ".$from->format()." to ".$to->format();
		break;
	case "m":
		$_STATE->heading .= "<br>for the month of ".$from->format("M-Y");
		break;
	default:
		$_STATE->heading .= "<br>for dates from ".$from->format()." to ".$to->format();
	}
}

function set_closedCols() { //plus 'mileage'
	global $_DB, $_STATE;

	$sql = "SELECT mileage, inactive_asof, close_date FROM ".$_DB->prefix."a10_project
			WHERE project_id=".$_STATE->project_id.";";
	$row = $_DB->query($sql)->fetch(PDO::FETCH_ASSOC);
	$inactive = $row["inactive_asof"];
	$_STATE->mileage = $row["mileage"]; //mileage reimbursement rate

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

function active_rates($project_id) { //does user have an hourly rate set for each day in the period
	global $_DB, $_STATE;

	$rates = array();

	$sql = "SELECT * FROM ".$_DB->prefix."c02_rate
			WHERE person_idref=".$_STATE->person_id."
			AND project_idref=".$project_id."
			ORDER BY effective_asof;";
	$stmt = $_DB->query($sql);
	$one_day= new DateInterval('P1D'); //P=period, 1=number, D=days
	$next = clone $_STATE->from_date; //a DateTime object
	$row["expire_after"] = clone $next;
	$row["expire_after"]->sub($one_day); //force a stmt->fetch 1st time thru
	for ($ndx=0; $next <= $_STATE->to_date; $ndx++) {
		while ($next > $row["expire_after"]) { //find an unexpired one
			if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
				$row["effective_asof"] = new DateTime($row["effective_asof"]);
				if (is_null($row["expire_after"])) {
					$row["expire_after"] = clone $_STATE->to_date;
				} else {
					$row["expire_after"] = new DateTime($row["expire_after"]);
				}
			} else {
				$row["effective_asof"] = clone $_STATE->to_date;
				$row["effective_asof"]->add($one_day);
				$row["expire_after"] = clone $row["effective_asof"];
			}
		}
		if ($next < $row["effective_asof"]) {
			$rates[$ndx] = 0;
		} else {
			$rates[$ndx] = $row["rate_id"];
		}
		$next->add($one_day);
	}

	return $rates;
}

function total_amounts(&$state) { //for all selected projects (won't work in list mode)
	global $_DB, $ExpTypes;

	if ($state->mode == "l") return; //list style

	$totals = array();
	for ($ndx=0; $ndx<$state->columns[COL_COUNT]; $ndx++) $totals[] = 0;
	$sql = "SELECT logdate, amount, type, v11.project_id, mileage
			FROM ".$_DB->prefix."v11_expensereport AS v11
			INNER JOIN ".$_DB->prefix."a10_project AS a10 ON a10.project_id = v11.project_id
			WHERE (person_id=".$state->person_id.") AND (v11.project_id IN (".
			implode($state->project_ids,",").
			")) AND (logdate BETWEEN :fromdate AND :todate)
			ORDER BY logdate;";
	$stmt = $_DB->prepare($sql);
	$stmt->bindValue(':fromdate', $state->from_date->format('Y-m-d'), db_connect::PARAM_DATE);
	$stmt->bindValue(':todate', $state->to_date->format('Y-m-d'), db_connect::PARAM_DATE);
	$stmt->execute();
	$one_day= new DateInterval('P1D'); //P=period, 1=number, D=days
	$next = clone $state->from_date; //a DateTime object
	$ndx = 0;
	while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
		while (new DateTime($row["logdate"]) > $next) {
			++$ndx;
			$next->add($one_day);
		}
		if ($row["type"] == "mi") $row["amount"] = $row["mileage"] * $row["amount"];
		$totals[$ndx] += $row["amount"];
	}

	$state->totals = $totals;
}

function get_projects($person_id) { //projects connected to this person
	global $_STATE, $_DB, $_PERMITS;

	if ($_PERMITS->can_pass(PERMITS::_SUPERUSER)) return array(0); //superuser gets all
	$sql = "SELECT a10.project_id, a10.inactive_asof FROM ".$_DB->prefix."a10_project AS a10
			INNER JOIN (
				SELECT c02.project_idref FROM ".$_DB->prefix."c02_rate AS c02
					INNER JOIN ".$_DB->prefix."c00_person AS c00 ON c00.person_id = c02.person_idref
					WHERE c00.person_id = ".$person_id." GROUP BY c02.project_idref
				) AS rate ON rate.project_idref = a10.project_id
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
	global $_DB;

	$state->records = array();

	//sort order is different in list vs tabular:
	$sql = ($state->mode == "t")?
			"activity_id, task_id, subtask_id, account_id, logdate":
			"logdate, activity_id, task_id, subtask_id, account_id";
	$sql = "SELECT * FROM ".$_DB->prefix."v01_expenselog
			WHERE (person_id=".$state->person_id.") AND (project_id=".$state->project_id.")
			AND (logdate BETWEEN :fromdate AND :todate)
			ORDER BY ".$sql.";";
	$stmt = $_DB->prepare($sql);
	$stmt->bindValue(':fromdate', $state->from_date->format('Y-m-d'), db_connect::PARAM_DATE);
	$stmt->bindValue(':todate', $state->to_date->format('Y-m-d'), db_connect::PARAM_DATE);
	$stmt->execute();
	if (!($row = $stmt->fetchObject())) {
		$stmt->closeCursor();
		return;
	}
	$row_sav = clone($row);
	$row_sav->logdate = new DateTime('2000-01-01'); //first rec is not a dup
	$row_sav->task_id = 0; //force an initial row increment in tabular mode
	$row_count = 0; //will start at 1
	do {
		$samerow = (($row_sav->activity_id == $row->activity_id)
				 && ($row_sav->account_id == $row->account_id)
				 && ($row_sav->type == $row->type)
				 && ($row_sav->subtask_id == $row->subtask_id)
				 && ($row_sav->task_id == $row->task_id));
		if ($state->mode == "t") {
			if (!$samerow) ++$row_count;
		} else {
			++$row_count; //in list style, every record is a new row
		}
		if (($findrow > 0) && ($row_count > $findrow)) break; //get only this row
		if ($row_count >= $findrow) { //makes $findrow the first in the array
			$row->logdate = new DateTime($row->logdate);
			$record = array(
				"ID" =>			$row->expenselog_id,
				"logdate"=>		new DATE_FIELD("txtLog","logdate",FALSE,FALSE,FALSE,0,FALSE,$row->logdate),
				"amount" =>		$row->amount,
				"row" =>		$row_count, //1 rel (0 => add row)
				"column" =>		date_diff($state->from_date, $row->logdate)->days, //tabular column (0 rel)
				"type" =>		$row->type,
				"account" =>	($row->account == "*")?"":substr($row->account.": ".$row->account_desc,0,25),
				"account_id" =>	$row->account_id,
				"task" =>		($row->task == "*")?"":substr($row->task.": ".$row->task_desc,0,25),
				"task_id" =>	$row->task_id,
				"subtask" =>	($row->subtask == "*")?"":substr($row->subtask.": ".$row->subtask_desc,0,25),
				"subtask_id" =>	$row->subtask_id,
				"activity" =>	substr($row->activity,0,50),
				"activity_id" => $row->activity_id,
			);

			if ($samerow && ($row_sav->logdate == $row->logdate))
				$record["amount"] = -$record["amount"]; //it's a duplicate
			foreach (array("account","task","subtask") as $name) { //check these for 'inactive_asof'
				$item = $name."_inactive_asof";
				if (is_null($row->{$item})) continue;
				if ((new DateTime($row->{$item})) <= $state->to_date) {
						$record[$name] .= "<br>inactive as of (".$row->{$item}.")";
				}
			}
			$state->records[strval($row->expenselog_id)] = $record;
		}
		$row_sav = clone($row);
	} while ($row = $stmt->fetchObject());
	$stmt->closeCursor();
}

function log_put() {
	global $_DB, $_STATE;
	global $version;

	require_once "lib/props_send.php"; //routines for sending property values
	$props_send = new PROPS_SEND(array("a12","a14","a21"));

	$sql = "SELECT name, description FROM ".$_DB->prefix."a00_organization
			WHERE organization_id=".$_SESSION["organization_id"].";";
	$row = $_DB->query($sql)->fetchObject();
	$orgname = $row->name;
	$orgdesc = $row->description;

	$from = $_STATE->from_date->format('Y-m-d');
	$to = $_STATE->to_date->format('Y-m-d');

	$sql = "SELECT * FROM ".$_DB->prefix."v11_expensereport
			WHERE (person_id IN(".implode($_STATE->person_ids,",")."))
			AND (project_id IN (".implode($_STATE->project_ids,",")."))
			AND (logdate BETWEEN :fromdate AND :todate)
			ORDER BY logdate LIMIT 1;";
	$stmt = $_DB->prepare($sql);
	$stmt->bindValue(':fromdate', $from, db_connect::PARAM_DATE);
	$stmt->bindValue(':todate', $to, db_connect::PARAM_DATE);
	$stmt->execute();
	if (!($row = $stmt->fetch(PDO::FETCH_ASSOC))) {
		$_STATE->msgStatus = "No logs were downloaded";
		return;
	}

	$filename = "expensesheet_".$orgname."_".$from."_to_".$to.".csv"; //for file_put...
	require_once "lib/file_put.php";

	$out = FP_open($filename);

	$outline = array();
	$outline[] = "expensesheet";
	$outline[] = $version;
	$outline[] = $from;
	$outline[] = $to;
	$outline[] = $orgname;
	$outline[] = $orgdesc;
	fputcsv($out, $outline); //ID row

	$outline = array();
	$fields = "";
	$type = 3; //offset to these fields
	$rate = 15;
	$ndx = 0;
	foreach ($row as $name=>$value) { //headings
//		if (substr($name,-3) == "_id") continue; //don't send id fields
		if (($name == "project_id") || ($name == "lastname") || ($name == "firstname")) continue;
		if ($name == "type") $type = $ndx;
		if ($name == "rate") $rate = $ndx;
		$outline[] = $name;
		$fields .= ",".$name;
		++$ndx;
	}
	fputcsv($out, $outline);

	$props_send->init($outline); //set up to get property values

	$sql_logs = "SELECT ".substr($fields,1)." FROM ".$_DB->prefix."v11_expensereport
			WHERE (person_id=:person_id) AND (project_id=:project_id)
			AND (logdate BETWEEN :fromdate AND :todate)
			ORDER BY logdate;";
	$stmt_logs = $_DB->prepare($sql_logs);

	foreach ($_STATE->project_ids as $project_id) {
		$sql = "SELECT name, description, mileage FROM ".$_DB->prefix."a10_project
				WHERE project_id=".$project_id.";";
		$row = $_DB->query($sql)->fetchObject();
		$outline = array();
		$outline[] = "<project>";
		$outline[] = $row->name;
		$outline[] = $row->description;
		$outline[] = $project_id;
		$mileage = $row->mileage;
		fputcsv($out, $outline); //project row

		foreach ($_STATE->person_ids as $person_id) {
			$sql = "SELECT lastname, firstname FROM ".$_DB->prefix."c00_person
					WHERE person_id=".$person_id.";";
			$row = $_DB->query($sql)->fetchObject();
			$outline = array();
			$outline[] = "<person>";
			$outline[] = $row->lastname;
			$outline[] = $row->firstname;
			$outline[] = $person_id;
			fputcsv($out, $outline); //person row

			$stmt_logs->bindvalue(':person_id', $person_id, db_connect::PARAM_INT);
			$stmt_logs->bindvalue(':project_id', $project_id, db_connect::PARAM_INT);
			$stmt_logs->bindValue(':fromdate', $from, db_connect::PARAM_DATE);
			$stmt_logs->bindValue(':todate', $to, db_connect::PARAM_DATE);
			$stmt_logs->execute();
			while ($row_logs = $stmt_logs->fetch(PDO::FETCH_NUM)) {

				$props_send->add_ids($row_logs); //add property value ids

				if ($row_logs[$type] == "mi") $row_logs[$rate] = $mileage;
				fputcsv($out, $row_logs);
			}
			$stmt_logs->closeCursor();
		} //end persons

		$outline = array();
		$outline[] = "<person>";
		$outline[] = "<end>";
		fputcsv($out, $outline); //project row
	} //end projects

	$outline = array();
	$outline[] = "<project>";
	$outline[] = "<end>";
	fputcsv($out, $outline); //project row

	$props_send->send_all($out);

	$_STATE->msgStatus = "Logs successfully downloaded";
	FP_close($out); //does not return
}

//	CALL BACK SECTION
//These routines handle the various server 'call-backs' not included from lib/callback.
//A 'call-back' leaves the page intact while a request is sent back to the server and the response then handled via script.

function cell_desc() {
	global $_DB;

	$field = "description";
	switch ($_GET["getdesc"]) {
	case "TK":
		$table = "a12_task";
		$id = "task_id";
		break;
	case "ST":
		$table = "a14_subtask";
		$id = "subtask_id";
		break;
	case "AC":
		$table = "a21_account";
		$id = "account_id";
		break;
	case "AT":
		$table = "b02_activity";
		$id = "activity_id";
		break;
	default:
		throw_the_bum_out(NULL,"Evicted(".__LINE__."): invalid cell ID ".$_GET["getdesc"], true);
	}
	$key = $_GET["ID"];
	$sql = "SELECT ".$field." FROM ".$_DB->prefix.$table." WHERE ".$id."=:key;";
	$stmt = $_DB->prepare($sql);
	$stmt->bindValue(":key", $key, PDO::PARAM_INT);
	$stmt->execute();
	$row = $stmt->fetchObject();
	echo "@got_desc('".$row->{$field}."');\n";
}

//Populate the activity pulldown selection list then collect the response via server call-back:
function activity_list(&$state) {
	global $_DB;

	$state->records = array();

	$sql = "SELECT activity_id, activity FROM ".$_DB->prefix."v01_expenselog
		WHERE person_id=".$state->person_id." AND subtask_id=".$state->subtask_id." AND
		logdate BETWEEN '".$state->from_date->format("Y-m-d")."' AND '".$state->to_date->format("Y-m-d")."'
		ORDER BY logdate;";
	$stmt = $_DB->query($sql);
	while ($row = $stmt->fetchObject()) {
		$state->records[strval($row->activity_id)] = $row->activity;
	}
	$stmt->closeCursor();
}

function activity_send(&$state, &$HTML) {

	activity_list($state);

	$HTML .= "//Activities...\n";
   	$HTML .= "document.getElementById('msgGreet_ID').innerHTML = 'Select the activity';\n";
	$HTML .= "fill = \"<select name='selActivity' id='selActivity' size='1' onchange='select_activity(this)'>\";\n";
	$HTML .= "fill += \"<option value='\\\\AT0' selected>...new activity...\";\n"; //select_activity() recognizes the 'backslashAT0'...
	foreach($state->records as $value => $name) {
			$HTML .= "fill += \"<option value='".$value."'>".substr($name,0,50)."\";\n";
	}
	$HTML .= "fill += \"</select>\";\n";
	$HTML .= "cell = document.getElementById('AT_".$state->row."');\n";
	$HTML .= "cell.innerHTML = fill;\n";
	if (count($state->records) == 0) {
		$HTML .= "document.getElementById('selActivity').selectedIndex=0;\n";
		$HTML .= "select_activity(document.getElementById('selActivity'));\n";
	} else {
		$HTML .= "document.getElementById('selActivity').selectedIndex=-1;\n";
	}

	return count($state->records);
}

function activity_select(&$state, &$HTML) {

	if (!isset($_GET["row"])) return;
	$rec = $_GET["row"]; //get row number

	$state->activity_id = $rec;
	$state->msgStatus = "";
	if ($rec != 0) {
		activity_list($state); //restore the record list
		if (!array_key_exists($rec, $state->records)) {
			throw_the_bum_out(NULL,"Evicted(".__LINE__."): invalid activity ".$rec,true);
		}
	}
}

//Populate the type pulldown selection list then collect the response via server call-back:
function type_send(&$state, &$HTML) {
	global $ExpTypes;

	$HTML .= "//types...\n";
   	$HTML .= "document.getElementById('msgGreet_ID').innerHTML = 'Select the expense type';\n";
	$HTML .= "fill = \"<select name='selType' id='selType' size='1' onchange='proceed(this.parentNode,this.options[this.selectedIndex].value)'>\";\n";
	foreach($ExpTypes as $value => $name) {
		$HTML .= "fill += \"<option value='".$value."'>".$name."</option>\";\n";
	}
	$HTML .= "fill += \"</select>\";\n";
	$HTML .= "cell = document.getElementById('TP_".$state->row."');\n";
	$HTML .= "cell.innerHTML = fill;\n";
	$HTML .= "document.getElementById('selType').selectedIndex=-1;\n";

	return;
}

function type_select(&$state, &$HTML) {
	global $ExpTypes;

	$type = $_GET["row"];
	if (!array_key_exists($type, $ExpTypes)) {
		throw_the_bum_out(NULL,"Evicted(".__LINE__."): invalid expense type ".$type,true);
	}
	$HTML .= "cell = document.getElementById('TP_".$state->row."');\n";
	$HTML .= "cell.innerHTML = '".$ExpTypes[$type]."';\n";
	$state->type = $type;
	$state->msgStatus = "";
}

//Send the amount text entry field(s) via server call-back:
function amount_send(&$state, &$HTML) {

	if ($state->mode == "l") { //list style ; only one amount field
		$offset = 0;
		$maxcol = 1;
	} else { //tabular style
		$offset = $state->columns[COL_OPEN];
		$maxcol = $state->columns[COL_INACTIVE];
		if ($state->row == 0) { //0 is add row
			$state->extension = "";
		} else {
			log_list($state, $state->row); //find row specific stuff
			$record = reset($state->records);
		}
	}
	$rates = active_rates($state->project_id);
	for ($offset=$offset; $offset<$maxcol; ++$offset) {
		$cellID = "AM_".$state->row."_".$offset;
		$HTML .= "//next Amount ".$offset."...\n";
		$HTML .= "cell = document.getElementById('".$cellID."');\n";
		$HTML .= "cellValue = cell.getAttribute('data-value');\n";
		if ($rates[$offset] == 0) { //no hourly rate so no input
			$HTML .= "fill = \"<div name='txtAmount".$offset."' id='txtAmount".$offset."_ID'>";
			$HTML .= "Rate not available</div>\";\n";
		} else {
			$HTML .= "fill = \"<input type='text' name='txtAmount".$offset."' id='txtAmount".$offset."_ID' size='5'";
			$HTML .= " maxlength='6' class='number' onblur='audit_amount(this,99)' value='\"+cellValue+\"'>\";\n";
		}
    	$HTML .= "if (cell.getAttribute('data-recid') >= 0) {\n";
		$HTML .= "  cell.innerHTML = fill;\n";
		$HTML .= "}\n";
	}
}

//Audit the input for update/add:
function audit_amount(&$state, $recID, $amount, $day) {
	global $_DB;

		if (!is_numeric($amount)) return false;
		if ($amount == 0) return true;
		if (($amount > 500) || ($amount < 0)) return false;

		$sql = "SELECT * FROM ".$_DB->prefix."v01_expenselog
				WHERE (person_id=".$state->person_id.") AND (project_id=".$state->project_id.")
				AND type='".$state->type."'
				AND logdate = '".$day."' AND expenselog_id <> ".$recID.";";
		$stmt = $_DB->query($sql);
		while ($row = $stmt->fetchObject()) {
			if (($recID == 0) && ($row->subtask_id == $state->subtask_id)
			 && ($row->type == $state->type)
			 && ($row->account_id == $state->account_id)) { //$recId=0 => adding
				$state->msgStatus = "!You have hours previously logged to this subtask/".
					$state->accounting." for ".$day."<br>To change those hours, use the update facility";
				return false;
			}
		}
	return true;
}

function audit_amounts(&$state, &$logdate, &$status) { //set status = '', 'a(dd)', 'u(pdate)', 'd(elete)'
	global $_DB;

	if ($state->row > 0) { //updating (0 is add row)
		$record = reset($state->records);
		if ($record["ID"] == 0)
			throw_the_bum_out(NULL,"Evicted(".__LINE__."): invalid POST 1",true);
	}

	$day = clone($logdate);
	$columns = ($state->mode == "l")?1:$state->columns[COL_COUNT];
	for ($ndx=0; $ndx < $columns; $ndx++, $day->value->add(new DateInterval('P1D'))) {
		if (!isset($_POST["amount".$ndx]) || ($_POST["amount".$ndx] == "")
		 || (($ndx < $state->columns[COL_OPEN])) && ($state->mode =="t")) {
			$status[] = ''; //no change to this record
			continue;
		}
		if (!isset($_POST["rec".$ndx]))
			throw_the_bum_out(NULL,"Evicted(".__LINE__."): invalid POST 3",true);
		$recID = $_POST["rec".$ndx]; //from data-recid attribute
		$amount = $_POST["amount".$ndx];

		$state->msgStatus = "!Please enter a valid amount (".$ndx.")";
		if (!audit_amount($state, $recID, $amount, $day->format())) return false;

		if ($recID == 0) { //if adding hours, we're done
			if ($amount == 0) {
				$status[] = ''; //no change to this record
			} else {
				$status[] = 'a'; //add this record
			}
			continue;
		}
		foreach ($state->records as $ID=>$record) { //find our record
			if ($record["ID"] == $recID) break;
			array_shift($state->records);
			if (count($state->records) == 0)
				throw_the_bum_out(NULL,"Evicted(".__LINE__."): invalid POST 5",true);
		}
		if ($amount == 0) {
			$status[] = 'd'; //delete this record
		} elseif ($amount == $record["amount"]) {
			$status[] = ''; //no change to this record
		} else {
			$status[] = 'u'; //update this record
		}
	}

	$state->msgStatus = "-"; //tell server_call to reset page
	return true;
}

//DB changes for update/add hours:
function add_log(&$state, &$logdate, $offset) {
	global $_DB;

	$sql = "INSERT INTO ".$_DB->prefix."b20_expenselog
			(activity_idref, person_idref, subtask_idref, account_idref, type, logdate, amount)
			VALUES (".$state->activity_id.", ".$state->person_id.", ".$state->subtask_id.", ".$state->account_id.",
			'".$state->type."', '".$logdate->format('Y-m-d')."', ".$_POST["amount".$offset].");";
	$_DB->exec($sql);

	$state->msgStatus = "-"; //tell server_call to reset page
}

function update_log(&$state, $offset) {
	global $_DB;

	$sql = "UPDATE ".$_DB->prefix."b20_expenselog
			SET amount=".$_POST["amount".$offset]."
			WHERE expenselog_id=".$_POST["rec".$offset].";";
	$_DB->exec($sql);

	$state->msgStatus = "-"; //tell server_call to reset page
}

function delete_log(&$state, $offset) {
	global $_DB;

	$sql = "SELECT activity_idref FROM ".$_DB->prefix."b20_timelog
			WHERE expenselog_id=".$_POST["rec".$offset].";";
	$stmt = $_DB->query($sql);
	$activity = $stmt->fetchObject()->activity_idref;
	$stmt->closeCursor();

	$sql = "DELETE FROM ".$_DB->prefix."b20_expenselog
			WHERE expenselog_id=".$state->recID.";";
	$_DB->exec($sql);

	$sql = "SELECT COUNT(*) AS count FROM ".$_DB->prefix."b20_expenselog WHERE activity_idref=".$activity."";
	$stmt = $_DB->query($sql);
	if ($stmt->fetchObject()->count == 0) {
		$sql = "DELETE FROM ".$_DB->prefix."b02_activity WHERE activity_id=".$activity."";
		$_DB->exec($sql);
	}
	$stmt->closeCursor();

	$state->msgStatus = "-"; //tell server_call to reset page
}

function add_activity(&$state) {
	global $_DB;

	$activity = COM_input_edit("act");

	$hash = md5($activity);
	$sql = "INSERT INTO ".$_DB->prefix."b02_activity (description) VALUES (:hash);";
	$stmt = $_DB->prepare($sql);
	$stmt->bindValue(':hash',$hash,PDO::PARAM_STR);
	$stmt->execute();

	$sql = "SELECT activity_id FROM ".$_DB->prefix."b02_activity WHERE description=:hash;";
	$stmt = $_DB->prepare($sql);
	$stmt->bindValue(':hash',$hash,PDO::PARAM_STR);
	$stmt->execute();
	$state->activity_id = $stmt->fetchObject()->activity_id;
	$stmt->closeCursor();

	$sql = "UPDATE ".$_DB->prefix."b02_activity SET description=:desc WHERE activity_id=".$state->activity_id.";";
	$stmt = $_DB->prepare($sql);
	$stmt->bindValue(':desc',$activity,PDO::PARAM_STR);
	$stmt->execute();
}

function update_activity(&$state, &$response) {
	global $_DB;

	$activity = COM_string_decode($_GET["act"],-1);
	$sql = "UPDATE ".$_DB->prefix."b02_activity SET description=:desc
			WHERE activity_id=".$_GET["actupd"].";";
	$stmt = $_DB->prepare($sql);
	$stmt->bindValue(':desc',$activity,PDO::PARAM_STR);
	$stmt->execute();

	$response = "."; //tell server_call we're done
	return true;
}

function new_amounts(&$state) {

	log_list($state, $state->row); //find this row's records

	//Do audits:
	$record = reset($state->records);
	if ($state->type == "") $state->type = $record["type"];
	if ($state->mode == "t") {
		$logdate = new DATE_FIELD("txtLog","logdate",FALSE,FALSE,FALSE,0,FALSE,clone($state->from_date));
	} elseif ($state->row == 0) { //adding in List mode
		$logdate = clone $state->logdate; //created by DATE_PICK
	} else {
		$logdate = clone($record["logdate"]);
	}
	$status = array();
	if (!audit_amounts($state, $logdate, $status)) return;

	//Do DB changes:
	//	adding a row but didn't select existing activity:
	if (($state->row == 0) && ($state->activity_id == 0)) add_activity($state);
	$columns = ($state->mode == "l")?1:$state->columns[COL_COUNT];
	for ($ndx=0; $ndx < $columns; $ndx++, $logdate->value->add(new DateInterval('P1D'))) {
		switch ($status[$ndx]) {
		case 'a': //add
			add_log($state, $logdate, $ndx);
			break;
		case 'u': //update
			update_log($state, $ndx);
			break;
		case 'd': //delete
			delete_log($state, $ndx);
			break;
		}
	}
}

function change_subtask(&$state) {
	global $_DB;

	include_once "lib/callback/subtask.php";
	subtask_list($state); //restore the record list
	if (!array_key_exists($state->subtask_id, $state->records)) {
		throw_the_bum_out(NULL,"Evicted(".__LINE__."): invalid subtask id ".$state->subtask_id,true);
	}
	log_list($state, $state->row); //find this row's records
	$record = reset($state->records);
	if ($state->subtask_id == $record["subtask_id"]) return;

	foreach ($state->records as $ID=>$record) {
		if ($record["column"] < $state->columns[COL_OPEN]) continue;
		$sql = "UPDATE ".$_DB->prefix."b20_expenselog
				SET subtask_idref=".$state->subtask_id." WHERE expenselog_id=".$ID.";";
		$_DB->exec($sql);
	}
}

function change_type(&$state) {
	global $_DB;

	log_list($state, $state->row); //find this row's records
	$record = reset($state->records);
	if ($state->type == $record["type"]) return; //no change

	foreach ($state->records as $ID=>$record) {
		if ($record["column"] < $state->columns[COL_OPEN]) continue;
		$sql = "UPDATE ".$_DB->prefix."b20_expenselog
				SET type='".$state->type."' WHERE expenselog_id=".$ID.";";
		$_DB->exec($sql);
	}
}

function change_account(&$state) {
	global $_DB;

	include_once "lib/callback/account.php";
	account_list($state); //restore the record list
	if (!array_key_exists($state->account_id, $state->records)) {
		throw_the_bum_out(NULL,"Evicted(".__LINE__."): invalid accounting id ".$state->account_id,true);
	}
	log_list($state, $state->row); //find this row's records
	$record = reset($state->records);
	if ($state->account_id == $record["account_id"]) return;

	foreach ($state->records as $ID=>$record) {
		if ($record["column"] < $state->columns[COL_OPEN]) continue;
		$sql = "UPDATE ".$_DB->prefix."b20_expenselog
				SET account_idref=".$state->account_id." WHERE expenselog_id=".$ID.";";
		$_DB->exec($sql);
	}
}

function change_activity(&$state) {
	global $_DB;

	if ($state->activity_id == 0) { //creating a new one
		add_activity($state);
	} else {
		activity_list($state); //restore the record list
		if (!array_key_exists($state->activity_id, $state->records)) {
			throw_the_bum_out(NULL,"Evicted(".__LINE__."): invalid activity id ".$state->activity_id,true);
		}
	}

	log_list($state, $state->row); //find this row's records
	$record = reset($state->records);
	$old_activity = $record["activity_id"];
	if ($state->activity_id == $old_activity) return;

	foreach ($state->records as $ID=>$record) {
		if ($record["column"] < $state->columns[COL_OPEN]) continue;
		$sql = "UPDATE ".$_DB->prefix."b20_expenselog
				SET activity_idref=".$state->activity_id." WHERE expenselog_id=".$ID.";";
		$_DB->exec($sql);
	}

	$sql = "SELECT COUNT(*) AS count FROM ".$_DB->prefix."b20_expenselog WHERE activity_idref=".$old_activity."";
	$stmt = $_DB->query($sql);
	if ($stmt->fetchObject()->count == 0) {
		$sql = "DELETE FROM ".$_DB->prefix."b02_activity WHERE activity_id=".$old_activity."";
		$_DB->exec($sql);
	}
	$stmt->closeCursor();
}

function changes(&$state, &$response) {

	$response = "-"; //initialize to reset page

	switch ($state->agent) {
	case "BN": //button => adding/updating amounts
		new_amounts($state);
		$response = $state->msgStatus;
/*$response = "!".$response."\n";;
foreach ($_POST as $key => $value) {
	$response .= $key."=".$value.";\n";
}*/
		break;
	case "TK": //task
	case "ST": //subtask
		change_subtask($state);
		break;
	case "AC": //account
		change_account($state);
		break;
	case "TP": //type
		change_type($state);
		break;
	case "AT": //activity
		change_activity($state);
		break;
	}
}

//-------end function code; begin HTML------------

$scripts = array("call_server.js");
if ($_STATE->status == SELECT_SPECS) {
	$scripts[] = "calendar.js";
} else if ($_STATE->status > SELECT_SPECS) {
	$scripts[] = "expenselog.js";
}
EX_pageStart($scripts); //standard HTML page start stuff - insert SCRIPTS here
?>
<script>
var COLs = <?php echo $_STATE->columns[COL_COUNT]; ?>;
</script>
<?php
EX_pageHead(); //standard page headings - after any scripts

//forms and display depend on process state; note, however, that the state was probably changed after entering
//the Main State Gate so this switch will see the next state in the process:
switch ($_STATE->status) {
case SELECT_PERSON:

	echo $persons->set_list();

	break; //end SELECT_PERSON status ----END STATE: EXITING FROM PROCESS----

case SELECT_PROJECT:

	echo $projects->set_list();

	break; //end SELECT_PROJECT status ----END STATE: EXITING FROM PROCESS----

case SELECT_SPECS:
?>

<form method="post" name="frmAction" id="frmAction_ID" action="<?php echo $_SESSION["IAm"]; ?>">
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
    <td>&nbsp</td>
    <td colspan="2" style="text-align:left">
      <button name="btnDates" type="button" value="<?php echo $_STATE->person_id ?>" onclick="this.form.submit()">Continue</button>
    </td>
  </tr>
</table>
</form>
<div id="msgStatus_ID"><?php echo $_STATE->msgStatus ?></div>

<?php //end SELECT_SPECS status ----END STATE: EXITING FROM PROCESS----
	break;

default: //list the amounts and allow new entry:
	$mode = ($_STATE->mode == "l")?"Tabular":"List"; //for 'Show in ___ Mode' message
?>
<div style='position:fixed; left:10px; top:5px;'>
<form method='post' name='frmAction' id='frmAction_ID' action='<?php echo $_SESSION["IAm"]; ?>'>
      <button name="btnMode" type="submit" onclick="this.form.submit()">Show in <?php echo $mode ?> Mode</button>
</form>
</div>
<?php
	if ($_EDIT) { //set by executive.php if admin Edit Logs
		require_once "lib/person_select.php";
		$persons = unserialize($_STATE->person_select);
		$select_list = $persons->selected();
		If (count($select_list) > 1) {
			$HTML = "";
			$HTML .= "<form method='post' name='frmAction' id='frmAction_ID' action='".$_SESSION["IAm"]."'>\n";
			$HTML .= "<select name='selPerson' onclick=";
			$HTML .= "'if (this.selectedIndex > 0) this.form.submit();'";
			$HTML .= " title='to select, click and hold down arrow'>\n";
			$HTML .= "<option>Select another person...</option>\n";
			foreach ($select_list as $person_ID=>$person) {
				$HTML .= "<option value='".$person_ID."'>";
				$HTML .= $person[PERSON_SELECT::LASTNAME].",".$person[PERSON_SELECT::FIRSTNAME];	
				$HTML .= "</option>\n";
			}
			$HTML .= "</select>\n";
			$HTML .= "</form>\n";
			echo $HTML;
		}
	}
?>
<div id="divPopopen_ID" class="popopen">
  Enter the new activity:<br>
  <textarea name="txtActivity" id="txtActivity_ID" rows="2" cols="50"></textarea><br>
  <input type="button" onclick="save_activity(true)" value="OK">
  <input type="button" id="cancelPop" onclick="save_activity(false)" value="cancel">
</div>
<?php
	require_once "lib/project_select.php";
	$projects = unserialize($_STATE->project_select);
	echo $projects->tabs();
?>
<table align="center" id="tblLog" cellpadding="4" border="2">
<?php //set up header & add rows:
if ($_STATE->mode == "l") {	//list style
	$headrow = "<th width='74'>Date</th><th width='30'>Amount</th>";
	$addrow = "<td id='DT_0' data-recid='0'></td>\n    <td id='AM_0_0' data-recid='0' data-value=''></td>\n";
} else {					//tabular style
	$week = array("Sun","Mon","Tue","Wed","Thu","Fri","Sat");
	$dayadd = new DateInterval('P1D');
	$headrow = "";
	for ($ndx=0,$day=clone $_STATE->from_date; $ndx<$_STATE->columns[COL_COUNT]; $ndx++,$day->add($dayadd)) {
		$dayname = $day->format("w");
		$headrow .= "<th";
		if ($ndx < $_STATE->columns[COL_OPEN]) $headrow .= " class='closed'";
		if ((($dayname == 0) || ($dayname == 6)) && ($ndx >= $_STATE->columns[COL_OPEN])) $headrow .= " class='weekend'";
		$headrow .= " width='50'>";
		$headrow .= $week[$day->format("w")]."<br>".$day->format("M d");
		$headrow .= "</th>";
	}
	$addrow = "";
	for ($ndx=0; $ndx<$_STATE->columns[COL_COUNT]; $ndx++) {
		$addrow .= "<td id='AM_0_".$ndx."' data-recid='0' data-value=''";
		if (($ndx < $_STATE->columns[COL_OPEN]) || ($ndx >= $_STATE->columns[COL_INACTIVE]))
			$addrow .= " class='closed'";
		$addrow .= "></td>\n";
	}
} //finish header & add rows ?>
  <tr>
    <th width='100'>&nbsp;</th>
    <th width='140'>Task</th>
    <th width='140'>Subtask</th>
    <th width='140'><?php echo $_STATE->accounting; ?></th>
    <th width='10'>Type</th>
    <?php echo $headrow; ?>
    <th width='140'>Activity</th>
  </tr>
  <tr id="add">
    <td id="BN_0" data-recid="0" title="Click to add amounts for new expenses">
      <img src="<?php echo $_SESSION["BUTLER"]; ?>?IAm=IG&file=add.png&ver=<?php echo $_VERSION; ?>"></td>
    <td id="TK_0" data-recid="0"></td>
    <td id="ST_0" data-recid="0"></td>
    <td id="AC_0" data-recid="0"></td>
    <td id="TP_0" data-recid=""></td>
    <?php echo $addrow; ?>
    <td id="AT_0" data-recid="0" data-value="\"></td>
  </tr>
<?php
function onerow(&$header, &$logs) {
	global $_STATE, $_PERMITS, $ExpTypes;

	$row = $header["row"];
	$openBN = " id='BN_".$row."' data-recid='".$row."' class=seq";
	$openID = "";
	if ($_STATE->mode == "l") { //list mode
		if ($header["column"] < $_STATE->columns[COL_OPEN]) {
			echo "  <tr class='closed'>\n";
			if (!$_PERMITS->can_pass("edit_logs")) {
				$openID = "-"; //a minus sign
				echo "    <td title='closed to new input'";
			} else {
				echo "    <td".$openBN." title='PROJECT IS CLOSED; edit with care!'";
			}
		} else {
			echo "  <tr>\n";
			echo "    <td".$openBN;
		}
	} else { //tabular
		echo "  <tr>\n";
		echo "    <td";
		if ($_STATE->columns[COL_OPEN] < $_STATE->columns[COL_COUNT]) {
			echo $openBN; //will allow edit after inactive date
		} else {
			$openID = "-"; //a minus sign
		}
	}
	echo ">".$row."</td>\n";
	echo "    <td id='TK_".$row."' data-recid='".$openID.$header["task_id"]."'>".$header["task"]."</td>\n";
	echo "    <td id='ST_".$row."' data-recid='".$openID.$header["subtask_id"]."'>".$header["subtask"]."</td>\n";
	echo "    <td id='AC_".$row."' data-recid='".$openID.$header["account_id"]."'>".$header["account"]."</td>\n";
	echo "    <td id='TP_".$row."' data-recid='".$openID.$header["type"]."'>".$ExpTypes[$header["type"]]."</td>\n";
	$max = ($_STATE->mode == "l")?1:$_STATE->columns[COL_COUNT];
	for ($ndx=0; $ndx<$max; $ndx++) {
		if ($_STATE->mode == "l") //add date in list mode
			echo "    <td id='DT_".$row."' class='date'>".$header["logdate"]->format()."</td>\n";
		echo "    <td id='AM_".$row."_".$ndx."' class='number'";
		echo " data-recid='".$logs[$ndx][LOG_ID]."' data-value='".$logs[$ndx][LOG_AMT]."'";
		if ($_STATE->mode == "l") {
			if ($logs[$ndx][LOG_DSP] < 0) {
				echo " style='background-color:red' title='DUPLICATE!'";
			}
		} else {
			if ($logs[$ndx][LOG_DSP] < 0) {
				echo " style='background-color:red' title='SUM OF DUPLICATES! List mode shows both'";
			} elseif ($ndx < $_STATE->columns[COL_OPEN]) { //allow edit after inactive date
				echo " class='closed'";
			}
		}
		if ($logs[$ndx][LOG_TITLE] != "") {
			echo " title='".$logs[$ndx][LOG_TITLE]."'>=";
		} else {
			echo ">";
		}
		echo abs($logs[$ndx][LOG_DSP])."</td>\n";
		$logs[$ndx] = array(0,0,0,""); //reset back to initial values
	}
	echo "\n";
	echo "    <td id='AT_".$row."' data-recid='".$openID.$header["activity_id"]."' data-value='\\'>".
		  $header["activity"]."</td>\n";
	echo "  </tr>\n";
} //end function onerow()

reset($_STATE->records);
$totals = array();
define ('LOG_DSP',0);
define ('LOG_ID',1);
define ('LOG_AMT',2);
define ('LOG_TITLE',3);
$logs = array();
for ($ndx=0; $ndx<$_STATE->columns[COL_COUNT]; $ndx++) { //save one row's worth of data:
	$totals[] = 0;
	$logs[] = array(0,0,0,"");
}
$row = 1;
foreach ($_STATE->records AS $ID=>$record) {
	if ($row != $record["row"]) { //starting a new row; write out old one
		onerow($recsav, $logs);
		$row = $record["row"];
	}
	$col = ($_STATE->mode == "l")?0:$record["column"];
	$amt = $record["amount"];
	$logs[$col][LOG_ID] = $ID;
	$logs[$col][LOG_AMT] = $amt;
	if ($record["type"] == "mi") {
		$logs[$col][LOG_TITLE] = $amt." entered * ".$_STATE->mileage. " rate";
		$amt = $_STATE->mileage * $amt;
	}
	$totals[$col] += abs($amt);
	if (($amt < 0) && ($_STATE->mode == "t")) { //dups in tabular are combined into one
		$amt += -$logs[$col][LOG_DSP]; //sum both amounts as a neg number
		$ID = -$ID; //recID also shows as negative (we won't allow edit)
	}
	$logs[$col][LOG_DSP] = $amt;
	$recsav = $record;
}
if (count($_STATE->records) > 0) { //get the last row - if there were any
	onerow($recsav, $logs);
}
$grand = 0;
for ($ndx=0; $ndx<$_STATE->columns[COL_COUNT]; $ndx++) {
	$grand += $totals[$ndx];
}
if ($_STATE->mode == "l") { //list
	echo "<tr>\n";
   	echo "  <td colspan='5'></td><td>Total:</td><td class='number'>".$grand,"</td><td></td>\n";
	echo "</tr>\n";

} else { //tabular
	echo "<tr>\n";
	echo "<td colspan='4'></td><td style='text-align:right'>project Totals:</td>\n";
	for ($ndx=0; $ndx<$_STATE->columns[COL_COUNT]; $ndx++) {
		echo "<td class='number'>".$totals[$ndx]."</td>";
	}
	echo "<td class='number'>project Grand Total: ".$grand."</td>\n";
	echo "</tr>\n";
	if (count($_STATE->project_ids) > 1) { //more than 1 project: show totals for all
		echo "<tr style='border-top:thin dashed'>\n";
		echo "<td colspan='4'></td><td style='text-align:right'>all projects:</td>\n";
		$grand = 0;
		for ($ndx=0; $ndx<$_STATE->columns[COL_COUNT]; $ndx++) {
			$grand += $_STATE->totals[$ndx];
			echo "<td class='number'>".$_STATE->totals[$ndx]."</td>";
		}
		echo "<td class='number'>all projects: ".$grand."</td>\n";
		echo "</tr>\n";
	}
}
?>
</table>

<form method="post" name="frmAction" id="frmAction_ID" action="<?php echo $_SESSION["IAm"]; ?>">
<br>You can
<button name="btnPut" type="submit" value="<?php echo $_STATE->person_id ?>" title="click here to download">
Download</button>
this data for import into the timesheet template
<br>(check your browser preferences for where the downloaded file will go)
</form>

<div id="msgStatus_ID" class="status"><?php echo $_STATE->msgStatus ?></div>
<?php //end select ($_STATE->status) ----END STATE: EXITING FROM PROCESS----
}

EX_pageEnd(); //standard end of page stuff
?>
