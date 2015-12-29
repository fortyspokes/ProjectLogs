<?php
//copyright 2015 C.D.Price. Licensed under Apache License, Version 2.0
//See license text at http://www.apache.org/licenses/LICENSE-2.0
if (!$_PERMITS->can_pass("subtask_edit")) throw_the_bum_out(NULL,"Evicted(".__LINE__."): no permit");

require_once "field_edit.php";
define('SELECT_PROJECT', STATE::SELECT);
define('SELECTED_PROJECT', STATE::SELECTED);
define('SELECT_TASK', STATE::SELECT + 1);
define('SELECTED_TASK', STATE::SELECTED + 1);
define('SELECT_SUBTASK', STATE::SELECT + 2);
define('SELECTED_SUBTASK', STATE::SELECTED + 2);

function state_fields() {
	global $_STATE;

	$_STATE->fields = array( //pagename,DBname,load from DB?,write to DB?,required?,maxlength
			"Name"=>new FIELD("txtName","name",TRUE,TRUE,TRUE,64),
			"Description"=>new AREA_FIELD("txtDesc","description",TRUE,TRUE,TRUE,256),
			"Inactive As Of"=>new DATE_FIELD("txtInactive","inactive_asof",TRUE,TRUE,FALSE,0),
			);
}

function task_list() {
	global $_DB, $_STATE;

	$_STATE->records = array();

	$sql = "SELECT * FROM ".$_DB->prefix."a12_task
			WHERE project_idref=".$_STATE->project_id." ORDER BY description;";
	$stmt = $_DB->query($sql);
	while ($row = $stmt->fetchObject()) {
		$_STATE->records[strval($row->task_id)] = substr($row->name.": ".$row->description,0,25);
	}
	$stmt->closeCursor();
}

function task_select($ID=-1) {
	global $_STATE;

	if ($ID < 0) { //not yet selected
		task_list(); //restore the record list
		if (!array_key_exists(strval($_POST["selTask"]), $_STATE->records)) {
			throw_the_bum_out(NULL,"Evicted(".__LINE__."): invalid task id ".$_POST["selTask"]); //we're being spoofed
		}
		$ID = intval($_POST["selTask"]);
	}
	$_STATE->task_id = $ID;
}

function subtask_list() {
	global $_DB, $_STATE;

	$_STATE->records = array();
	$_STATE->records["-1"] = "--create a new subtask record--";

	$sql = "SELECT * FROM ".$_DB->prefix."a14_subtask
			WHERE task_idref=".$_STATE->task_id." ORDER BY description;";
	$stmt = $_DB->query($sql);
	while ($row = $stmt->fetchObject()) {
		$_STATE->records[strval($row->subtask_id)] = substr($row->name.": ".$row->description,0,25);
	}
	$stmt->closeCursor();
}

function subtask_select($ID=-1) {
	global $_STATE;

	if ($ID < 0) { //not yet selected
		subtask_list(); //restore the record list
		if (!array_key_exists(strval($_POST["selSubtask"]), $_STATE->records)) {
			throw_the_bum_out(NULL,"Evicted(".__LINE__."): invalid subtask id ".$_POST["selSubtask"]); //we're being spoofed
		}
		$ID = intval($_POST["selSubtask"]);
	}
	$_STATE->record_id = $ID;
}

function subtask_info() {
	global $_DB, $_STATE;

	$sql = "SELECT * FROM ".$_DB->prefix."a14_subtask WHERE subtask_id=".$_STATE->record_id.";";
	$stmt = $_DB->query($sql);
	$row = $stmt->fetchObject();
	foreach($_STATE->fields as $field=>&$props) { //preset record info on the page
		if ($props->load_from_DB) {
			$props->value($row->{$props->dbname});
		}
	}
	$stmt->closeCursor();
}

function field_input_audit() {
	global $_STATE;

	$errors = "";
	foreach($_STATE->fields as $name => $field) {
		//allow an "*" for the name field:
		if (($name == "Name") && ($_POST[$field->pagename] == "*")) {
			$field->value = "*";
			continue;
		}
		if (($msg = $field->audit()) === true) continue;
		$errors .= "<br>".$name.": ".$msg;
	}
	if ($errors != "") {
		$_STATE->msgStatus = "Error:".$errors;
		return false;
	}

//Should check to see if inactive is greater than any timelogs?

	foreach ($_STATE->fields as $name => $field) {
		$field->disabled = true;
	}

	return TRUE;
}

function update_db() {
	global $_DB, $_STATE;

	$sql = "UPDATE ".$_DB->prefix."a14_subtask SET name=:name, description=:description, inactive_asof=:inactive";
	$sql .= " WHERE subtask_id=".$_STATE->record_id.";";
	$stmt = $_DB->prepare($sql);
	$stmt->bindValue(':name',$_STATE->fields["Name"]->value(),PDO::PARAM_STR);
	$stmt->bindValue(':description',$_STATE->fields["Description"]->value(),PDO::PARAM_STR);
	if ($_STATE->fields["Inactive As Of"]->value() == "") {
		$stmt->bindValue(':inactive', NULL, db_connect::PARAM_DATE);
	} else {
		$stmt->bindValue(':inactive',$_STATE->fields["Inactive As Of"]->value(),db_connect::PARAM_DATE);
	}
	$stmt->execute();
}

function update_audit() {
	global $_STATE;

	if (!field_input_audit()) return FALSE;

	update_db();

	$_STATE->msgStatus = "The subtask record for \"".$_STATE->fields["Name"]->value()."\" has been updated";
	return TRUE;
}

function new_audit() {
	global $_DB, $_STATE;

	if (!field_input_audit()) return FALSE;
	
	$hash = md5($_STATE->fields["Name"]->value().$_STATE->fields["Description"]->value());
	$sql = "INSERT INTO ".$_DB->prefix."a14_subtask (name, task_idref) VALUES (:hash, ".$_STATE->task_id.");";
	$stmt = $_DB->prepare($sql);
	$stmt->bindValue(':hash',$hash,PDO::PARAM_STR);
	$stmt->execute();

	$sql = "SELECT subtask_id FROM ".$_DB->prefix."a14_subtask WHERE name=:hash;";
	$stmt = $_DB->prepare($sql);
	$stmt->bindValue(':hash',$hash,PDO::PARAM_STR);
	$stmt->execute();
	$_STATE->record_id = $stmt->fetchObject()->subtask_id;
	$stmt->closeCursor();

	update_db();

	$_STATE->msgStatus = "The subtask record for \"".$_STATE->fields["Name"]->value()."\" has been added to the task";
	return TRUE;
}

state_fields();

//Main State Gate: (the while (1==1) allows a loop back through the switch using a 'break 1')
while (1==1) { switch ($_STATE->status) {
case STATE::INIT:
	$_STATE->close_date = false;
	$_STATE->accounting_id = 0;
	$_STATE->accounting = "";
	$_STATE->project_id = 0;
	$_STATE->task_id = 0;
	require_once "project_select.php";
	$projects = new PROJECT_SELECT($_PERMITS->restrict("subtask_edit"));
	$_STATE->project_select = serialize(clone($projects));
	if ($projects->selected) {
		$_STATE->status = SELECTED_PROJECT;
		break 1; //re-switch to SELECTED_PROJECT
	}
	$_STATE->msgGreet = "Select the project for this subtask";
	$_STATE->status = SELECT_PROJECT;
	break 2;
case SELECT_PROJECT:
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
	task_list();
	if (count($_STATE->records) == 1) { //solo task?
		$record = each($_STATE->records);
		task_select($record[0]); //select this one
		$_STATE->status = SELECTED_TASK;
		break 1; //re-switch to SELECTED_TASK
	}
	$_STATE->msgGreet = $_STATE->project_name."<br>Select the task for this subtask";
	$_STATE->status = SELECT_TASK;
	break 2;
case SELECT_TASK:
	task_select();
	$_STATE->heading .= "<br>Task: ".$_STATE->records[$_STATE->task_id]."<br>";
	$_STATE->status = SELECTED_TASK; //for possible goback
	$_STATE->replace();
//	break 1; //re_switch
case SELECTED_TASK:
	subtask_list();
	$_STATE->msgGreet = $_STATE->project_name."<br>Select a subtask record to edit";
	$_STATE->status = SELECT_SUBTASK;
	break 2;
case SELECT_SUBTASK:
	subtask_select();
	$_STATE->status = SELECTED_SUBTASK; //for possible goback
	$_STATE->replace();
//	break 1; //re_switch
case SELECTED_SUBTASK:
	if ($_STATE->record_id == -1) {
		$_STATE->msgGreet = "New subtask record";
		$_STATE->status = STATE::ADD;
	} else {
		subtask_info();
		$_STATE->msgGreet = "Edit subtask record?";
		$_STATE->status = STATE::UPDATE;
	}
	break 2;
case STATE::ADD:
	$_STATE->msgGreet = "New subtask record";
	if (isset($_POST["btnReset"])) {
		break 2;
	}
//	if ($_POST["btnSubmit"] != "add") { //IE < v8 submits name/InnerText NOT name/value
//		throw_the_bum_out(NULL,"Evicted(".__LINE__."): invalid btnSubmit ".$_POST["btnSubmit"]);
//	}
	if (new_audit()) {
		$_STATE->status = STATE::DONE;
		$_STATE->goback(1); //setup for goback
	}
	break 2;
case STATE::UPDATE:
	$_STATE->msgGreet = "Edit action record";
	if (isset($_POST["btnReset"])) {
		subtask_info();
		break 2;
	}
//	if ($_POST["btnSubmit"] != "update") {
//		throw_the_bum_out(NULL,"Evicted(".__LINE__."): invalid btnSubmit ".$_POST["btnSubmit"]);
//	}
	if (update_audit()) {
		$_STATE->status = STATE::DONE;
		$_STATE->goback(1); //setup for goback
	}
	break 2;
default:
	throw_the_bum_out(NULL,"Evicted(".__LINE__."): invalid state=".$_STATE->status);
} } //while & switch

EX_pageStart(); //standard HTML page start stuff - insert SCRIPTS here

if ($_STATE->status == SELECT_PROJECT)
	echo "<script type='text/javascript' src='".$EX_SCRIPTS."/call_server.js'></script>\n";

EX_pageHead(); //standard page headings - after any scripts

//forms and display depend on process state; note, however, that the state was probably changed after entering
//the Main State Gate so this switch will see the next state in the process:
switch ($_STATE->status) {
case SELECT_PROJECT:

	echo $projects->set_list();

	break; //end SELECT_PROJECT status ----END STATE: EXITING FROM PROCESS----
case SELECT_TASK:
?>
  <p>
<form method="post" name="frmAction" id="frmAction_ID" action="<?php echo $_SERVER['SCRIPT_NAME']; ?>">
  <select name='selTask' size="<?php echo count($_STATE->records); ?>" onclick="this.form.submit()">
<?php
	foreach($_STATE->records as $value => $name) {
  		echo "    <option value=\"".$value."\">".$name."\n";
	} ?>
  </select>
</form>
  </p>
<?php //end SELECT_TASK status ----END STATUS PROCESSING----
	break;
case SELECT_SUBTASK:
?>
  <p>
<form method="post" name="frmAction" id="frmAction_ID" action="<?php echo $_SERVER['SCRIPT_NAME']; ?>">
  <select name='selSubtask' size="<?php echo count($_STATE->records); ?>" onclick="this.form.submit()">
<?php	foreach($_STATE->records as $value => $name) {
  		echo "    <option value=\"".$value."\">".$name."\n";
	} ?>
  </select>
  </p>
</form>
<?php //end SELECT_SUBTASK status ----END STATUS PROCESSING----
	break;
default:
?>
<form method="post" name="frmAction" id="frmAction_ID" action="<?php echo $_SERVER['SCRIPT_NAME']; ?>">
  <table align="center">
    <tr>
      <td class="label"><?php echo $_STATE->fields['Name']->HTML_label("Name: "); ?></td>
      <td colspan="2"><?php echo $_STATE->fields['Name']->HTML_input(20) ?></td>
    </tr>
    <tr>
      <td class="label"><?php echo $_STATE->fields['Description']->HTML_label("Description: "); ?></td>
      <td colspan="2"><?php echo $_STATE->fields['Description']->HTML_input(32); ?></td>
    </tr>
    <tr>
      <td class="label"><?php echo $_STATE->fields['Inactive As Of']->HTML_label("Inactive As Of(yyyy-mm-dd): "); ?></td>
      <td><?php echo $_STATE->fields['Inactive As Of']->HTML_input(10) ?></td>
      <td>&nbsp</td>
    </tr>
  </table>
  <p>
<?php
	if ($_STATE->status != STATE::DONE) {
		if ($_STATE->status == STATE::ADD ) {
			echo FIELD_edit_buttons(FIELD_ADD);
		} else {
			echo Field_edit_buttons(FIELD_UPDATE);
		}
	} ?>
</form>
<?php //end default status ----END STATUS PROCESSING----
} ?>
<?php
EX_pageEnd(); //standard end of page stuff
?>

