<?php
//copyright 2016,2019 C.D.Price. Licensed under Apache License, Version 2.0
//See license text at http://www.apache.org/licenses/LICENSE-2.0
if (!$_PERMITS->can_pass("logs_prune")) throw_the_bum_out(NULL,"Evicted(".__LINE__."): no permit");

require_once "lib/field_edit.php";

$version = "v1.0"; //downloaded with the file for client verification
$PRUNE_PATH = $_SESSION["_SITE_CONF"]["_STASH"]."/prunings/";

//Main State Gate: (the while (1==1) allows a loop back through the switch using a 'break 1')
while (1==1) { switch ($_STATE->status) {
case STATE::INIT:
	require_once "lib/project_select.php";
	$projects = new PROJECT_SELECT();
	$_STATE->project_select = serialize(clone($projects));
	$_STATE->msgGreet = "Select the ".ucfirst($projects->get_label("project"))." to prune";
	Page_out();
	$_STATE->status = STATE::SELECT;
	break 2; //return to executive

case STATE::SELECT:
	require_once "lib/project_select.php"; //catches $_GET list refresh (assumes break 2)
	$projects = unserialize($_STATE->project_select);
	$projects->set_state();
	$_STATE->project = $projects->selected_name();
	$_STATE->record_id = $_STATE->project_id;
	$_STATE->status = STATE::SELECTED; //for possible goback
	$_STATE->replace();
case STATE::SELECTED:
	state_fields(); //creates the cutoff date for display
	$_STATE->msgGreet = "Enter the cutoff date to prune ".$_STATE->project;
	Page_out();
	$_STATE->status = STATE::UPDATE;
	break 2; //return to executive

case STATE::UPDATE:
	state_fields(); //creates the cutoff date for audit
	$_STATE->msgGreet = "Pruning...";
	if (update_audit()) {
		$_STATE->status = STATE::DONE;
		$_STATE->goback(1); //setup for goback
	}
	Page_out();
	break 2; //return to executive

default:
	throw_the_bum_out(NULL,"Evicted(".__LINE__."): invalid state=".$_STATE->status);
} } //while & switch
//End Main State Gate & return to executive

function state_fields() {
	global $_DB, $_STATE;

	$_STATE->fields = array( //pagename,DBname,load from DB?,write to DB?,required?,maxlength
			"Cutoff Date"=>new DATE_FIELD("txtCutoff","",FALSE,FALSE,TRUE,0),
			);
}

function field_input_audit() {
	global $_STATE, $_PERMITS;

	if (isset($_POST["chkOrphan"]) && (!$_PERMITS->can_pass(PERMITS::_SUPERUSER)))
		throw_the_bum_out(NULL,"Evicted(".__LINE__."): no permit");

	if (!isset($_POST["chkStale"])) return true; //no date to check

	$errors = "";
	foreach($_STATE->fields as $name => $field) {
		if (($msg = $field->audit(false)) === true) continue;
		$errors .= "<br>".$name.": ".$msg;
	}
	if ($errors != "") {
		$_STATE->msgStatus = "Error:".$errors;
		return false;
	}

	$diff = $_STATE->fields["Cutoff Date"]->value->diff(COM_NOW());
	if (($diff->days < 365) || ($diff->invert > 0)) {
		$_STATE->msgStatus = "The Cutoff Date must be at least a year ago";
		return false;
	}

	foreach ($_STATE->fields as $name => $field) {
		$field->disabled = true;
	}

	return true;
}

function orphan_logs() {
	global $_DB, $_STATE;
	global $version, $PRUNE_PATH;

	$file = $PRUNE_PATH."logsorphans_".$_STATE->orgname."_".$_STATE->projname;
	$file .= "_".COM_NOW()->format("Ymdhi").".csv"; //make the filename unique
	$handle = fopen($file, "w");

	$line = array();
	$line[] = "logsorphans";
	$line[] = $version;
	$line[] = $_STATE->orgname;
	$line[] = $_STATE->projname;
	$line[] = $_DB->prefix;
	$line[] = $_SESSION['_SITE_CONF']['DBCONN'];
	fputcsv($handle, $line); //ID row

//Projects:
	$ids = find_logs("project", "organization");
	save_logs($handle, "project", $ids);
	delete_logs("project", $ids);
	$_STATE->msgStatus .= "<br>".count($ids)." project orphans found/deleted";

//Tasks:
	$ids = find_logs("task", "project");
	save_logs($handle, "task", $ids);
	delete_logs("task", $ids);
	$_STATE->msgStatus .= "<br>".count($ids)." task orphans found/deleted";

//Subtasks:
	$ids = find_logs("subtask", "task");
	save_logs($handle, "subtask", $ids);
	delete_logs("subtask", $ids);
	$_STATE->msgStatus .= "<br>".count($ids)." subtask orphans found/deleted";

//Timelogs:
	$ids = find_logs("timelog", "subtask");
	save_logs($handle, "timelog", $ids);
	delete_logs("timelog", $ids);
	$_STATE->msgStatus .= "<br>".count($ids)." timelog orphans (from subtask) found/deleted";

//Expenselogs:
	$ids = find_logs("expenselog", "subtask");
	save_logs($handle, "expenselog", $ids);
	delete_logs("expenselog", $ids);
	$_STATE->msgStatus .= "<br>".count($ids)." expenselog orphans (from subtask) found/deleted";

//Activity
	$sql = "SELECT activity_id
			FROM ".$_DB->prefix."b02_activity AS b02
			LEFT OUTER JOIN
			(
				SELECT b00.activity_idref FROM ".$_DB->prefix."b00_timelog AS b00
				UNION
				SELECT b20.activity_idref FROM ".$_DB->prefix."b20_expenselog AS b20
			) AS logs
			ON b02.activity_id = logs.activity_idref
			WHERE logs.activity_idref IS NULL
			ORDER BY b02.activity_id;";
	$stmt = $_DB->query($sql);
	$ids = array();
	while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
		$ids[$row['activity_id']] = true;
	}
	save_logs($handle, "activity", $ids);
	delete_logs("activity", $ids);
	$_STATE->msgStatus .= "<br>".count($ids)." activity orphans found/deleted";

	$line = array();
	$line[] = "<table>";
	$line[] = "<end>";
	fputcsv ($handle, $line);

	fclose($handle);
} //end orphan_logs()

function stale_logs() {
	global $_DB, $_STATE;
	global $version, $PRUNE_PATH;

//Step1: find all the records to delete:
	$time_ids = array();
	$exp_ids = array();
	$evt_ids = array();
	$act_ids = array(); //these activities belong to to-be-deleted logs

	//timelog:
	$sql = "SELECT timelog_id, activity_id FROM ".$_DB->prefix."v00_timelog
			WHERE logdate<:cutoff AND project_id=".$_STATE->project_id."
			ORDER BY activity_id;";
	$stmt = $_DB->prepare($sql);
	$stmt->bindValue(':cutoff',$_STATE->fields["Cutoff Date"]->value(), db_connect::PARAM_DATE);
	$stmt->execute();
	$count_time = 0;
	while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
		$time_ids[$row['timelog_id']] = true;
		$act_ids[$row['activity_id']] = true;
		++$count_time;
	}
	$stmt->closeCursor();

	//expenselog:
	$sql = "SELECT expenselog_id, activity_id FROM ".$_DB->prefix."v01_expenselog
			WHERE logdate<:cutoff AND project_id=".$_STATE->project_id."
			ORDER BY activity_id;";
	$stmt = $_DB->prepare($sql);
	$stmt->bindValue(':cutoff',$_STATE->fields["Cutoff Date"]->value(), db_connect::PARAM_DATE);
	$stmt->execute();
	$count_exp = 0;
	while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
		$exp_ids[$row['expenselog_id']] = true;
		$act_ids[$row['activity_id']] = true;
		++$count_exp;
	}
	$stmt->closeCursor();

	//eventlog:
	$sql = "SELECT b10.logdate, b10.eventlog_id, a30.event_id
			FROM ".$_DB->prefix."b10_eventlog AS b10
			JOIN ".$_DB->prefix."a30_event AS a30 ON b10.event_idref=a30.event_id
			WHERE b10.logdate<:cutoff AND a30.project_idref=".$_STATE->project_id."
			ORDER BY eventlog_id;";
	$stmt = $_DB->prepare($sql);
	$stmt->bindValue(':cutoff',$_STATE->fields["Cutoff Date"]->value(), db_connect::PARAM_DATE);
	$stmt->execute();
	$count_evt = 0;
	while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
		$evt_ids[$row['eventlog_id']] = true;
		++$count_evt;
	}
	$stmt->closeCursor();

//Step 2: Write the backup files (except activity)
	$cutoff = $_STATE->fields["Cutoff Date"]->value->format('Y-m-d');
	$file = $PRUNE_PATH."logsprune_".$_STATE->orgname."_".$_STATE->projname."_".$cutoff;
	$file .= "_".COM_NOW()->format("Ymdhi").".csv"; //make the filename unique - even if same cutoff
	$handle = fopen($file, "w");

	$line = array();
	$line[] = "logsprune";
	$line[] = $version;
	$line[] = $cutoff;
	$line[] = $_STATE->orgname;
	$line[] = $_STATE->projname;
	$line[] = $_DB->prefix;
	$line[] = $_SESSION['_SITE_CONF']['DBCONN'];
	fputcsv($handle, $line); //ID row

	save_logs($handle, "timelog", $time_ids);
	save_logs($handle, "expenselog", $exp_ids);
	save_logs($handle, "eventlog", $evt_ids);

//Step 3: Delete the records (except activity)
	delete_logs("timelog", $time_ids);
	delete_logs("expenselog", $exp_ids);
	delete_logs("eventlog", $evt_ids);

//Activity: now that logs are deleted mark any activity that is still connected to a remaining log,
// then save and delete activity.
	$sql = "0"; //A 0 record does not exist but it makes 'IN' clause legal even if no other records
	foreach ($act_ids as $key=>$value) $sql .= ",".$key;
	$sql = "SELECT b02.activity_id, logs.logdate FROM
			(
				SELECT activity_id FROM ".$_DB->prefix."b02_activity
				WHERE activity_id IN (".$sql.")
			) as b02
			LEFT OUTER JOIN
			(
				SELECT v00.activity_id, v00.logdate FROM ".$_DB->prefix."v00_timelog AS v00
				WHERE (v00.project_id = ".$_STATE->project_id.")
				UNION
				SELECT v01.activity_id, v01.logdate FROM ".$_DB->prefix."v01_expenselog AS v01
				WHERE (v01.project_id = ".$_STATE->project_id.")
			) AS logs
			ON b02.activity_id = logs.activity_id
			ORDER BY b02.activity_id;";
	$stmt = $_DB->query($sql);
	$count_act = 0;
	while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
		if (!is_null($row['logdate'])) {
			$act_ids[$row['activity_id']] = false; //don't delete this guy
			continue;
		}
		++$count_act;
	}
	save_logs($handle, "activity", $act_ids);

	$line = array();
	$line[] = "<table>";
	$line[] = "<end>";
	fputcsv ($handle, $line);

	fclose($handle);

	delete_logs("activity", $act_ids);

	$_STATE->msgStatus = "<br>".$count_time." time log records deleted".
						"<br>".$count_exp." expense log records deleted".
						"<br>".$count_evt." event log records deleted".
						"<br>".$count_act." activity records deleted";
} //end stale_logs()

function find_logs($name, $parent) {
	global $_DB, $_STATE;

	$child = $_STATE->records[$name];
	$parent = $_STATE->records[$parent];
	$sql = "SELECT ".$child->idname." AS child
			FROM ".$child->name."
			LEFT OUTER JOIN ".$parent->name."
			ON ".$parent->idname."ref = ".$parent->idname."
			WHERE ".$parent->idname." IS NULL
			ORDER BY ".$child->idname.";";
	$ids = array();
	$stmt = $_DB->query($sql);
	while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
		$ids[$row['child']] = true;
	}
	$stmt->closeCursor();
	return $ids;
}

function save_logs($handle, $name, &$ids) {
	global $_DB, $_STATE;

	$table = $_STATE->records[$name];
	$line = array();
	$line[] = "<table>";
	$line[] = $table->name;
	fputcsv ($handle, $line); //start table
	$fields = array();
	foreach ($table->fields as $name=>$value) {
		$fields[] = $name;
	}
	fputcsv ($handle, $fields); //headers
	$fields = implode(",",$fields);
	$sql = "0"; //A 0 record does not exist but it makes 'IN' clause legal even if no other records
	foreach ($ids as $key=>$value) {
		if (!$value) continue; //don't delete this one
		$sql .= ",".$key;
	}
	if ($sql == "") return; //nothing to save
	$sql = "SELECT ".$fields." FROM ".$table->name." WHERE ".$table->idname." IN (".$sql.");";
	$stmt = $_DB->query($sql);
	while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
		fputcsv ($handle, $row);
	}
}

function delete_logs($name, &$ids) {
	global $_DB, $_STATE;

	if (isset($_POST["chkReport"])) return;

	$table = $_STATE->records[$name];
	$sql = "0"; //A 0 record does not exist but it makes 'IN' clause legal even if no other records
	foreach ($ids as $key=>$value) {
		if (!$value) continue; //don't delete this one
		$sql .= ",".$key;
	}
	if ($sql == "") return; //nothing to delete
	$sql = "DELETE FROM ".$table->name." WHERE ".$table->idname." IN (".$sql.");";
	$stmt = $_DB->exec($sql);
}

function update_audit() {
	global $_DB, $_STATE;

	if (!field_input_audit()) return false;

	require_once ("lib/tables_list.php");
	$_STATE->records = DB_tables($_DB->prefix);
	$sql = "SELECT name FROM ".$_DB->prefix."a00_organization
			WHERE organization_id=".$_SESSION["organization_id"].";";
	$_STATE->orgname = $_DB->query($sql)->fetchObject()->name;
	$_STATE->projname = explode(":",$_STATE->project)[0];

	$_STATE->msgStatus = "";
	if (isset($_POST["chkStale"])) stale_logs();
	if (isset($_POST["chkOrphan"])) orphan_logs();

	$_STATE->msgStatus = "Pruning for \"".$_STATE->project."\" has been done".$_STATE->msgStatus;
	return true;
}

function Page_out() {
	global $_DB, $_STATE, $_PERMITS, $_VERSION;

	EX_pageStart(); //standard HTML page start stuff - insert SCRIPTS here
?>
<script type='text/javascript'>

function audit_form() {
	if (!document.getElementById("chkStale_id").checked) return true;

	YYYY = document.getElementById("txtCutoffYYYY_ID");
	MM = document.getElementById("txtCutoffMM_ID");
	DD = document.getElementById("txtCutoffDD_ID");

	if (isNaN(YYYY.value) || isNaN(MM.value) || isNaN(DD.value)) {
		alert("Date values must be numeric");
		return false;
	}
	//note: the "-1" is necessary because JS expects month relative to 0 (but not day); without the "intval" we'll
	//get a leading zero which JS interprets as an octal number (the "-1" does the same as "intval")
	var date = new Date(Number(YYYY.value),Number(MM.value)-1,Number(DD.value));
	if (!confirm("Are you sure you want the cutoff date of "+date.toDateString()+"?")) return false;

	return true;
}

</script>
<?php
	EX_pageHead(); //standard page headings - after any scripts

	switch ($_STATE->status) {

	case STATE::INIT:
		global $projects;
		echo $projects->set_list();
		break; //end STATE::INIT status ----END STATUS PROCESSING----

	default:
?>
<form method="post" name="frmAction" id="frmAction_ID" action="<?php echo $_SESSION["IAm"]; ?>" onsubmit="return audit_form()">
  <table align="center">
    <tr>
      <td class="label"><?php echo $_STATE->fields['Cutoff Date']->HTML_label("Logs Cutoff Date(YYYY-MM-DD): "); ?></td>
      <td><?php foreach ($_STATE->fields['Cutoff Date']->HTML_input() as $line) echo $line."\n"; ?></td>
    </tr>
    <tr>
      <td style='text-align:right'><input type='checkbox' name='chkReport' value='report' checked></td>
      <td style='text-align:left'>Report only</td>
    </tr>
    <tr>
      <td style='text-align:right'><input type='checkbox' name='chkStale' value='stale' id='chkStale_id'></td>
      <td style='text-align:left'>Prune stale records (prior to cutoff date)</td>
    </tr>
<?php if ($_PERMITS->can_pass(PERMITS::_SUPERUSER)) { ?>
    <tr>
      <td style='text-align:right'><input type='checkbox' name='chkOrphan' value='orphan'></td>
      <td style='text-align:left'>Prune orphan records</td>
    </tr>
<?php } ?>
  </table>
  <button type="submit">Prune</button>
</form>
<?php
		break; //end default status ----END STATUS PROCESSING----

	} //end select ($_STATE->status) ----END STATE: EXITING FROM PROCESS----

	EX_pageEnd(); //standard end of page stuff

} //end Page_out()
?>
