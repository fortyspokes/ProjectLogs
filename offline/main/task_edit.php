<?php
//copyright 2015-2016,2019 C.D.Price. Licensed under Apache License, Version 2.0
//See license text at http://www.apache.org/licenses/LICENSE-2.0
if (!$_PERMITS->can_pass("task_edit")) throw_the_bum_out(NULL,"Evicted(".__LINE__."): no permit");

require_once "lib/field_edit.php";

//The Main State Gate cases:
define('LIST_PROJECTS',		STATE::INIT);
define('SELECT_PROJECT',		LIST_PROJECTS + 1);
define('SELECTED_PROJECT',		LIST_PROJECTS + 2);
define('LIST_TASKS',		STATE::INIT + 10);
define('SELECT_TASK',			LIST_TASKS + 1);
define('SELECTED_TASK',			LIST_TASKS + 2);
define('ADD_TASK',				LIST_TASKS + 3);
define('UPDATE_TASK',			LIST_TASKS + 4);
define('PROPERTIES',		STATE::INIT + 20);

//Main State Gate: (the while (1==1) allows a loop back through the switch using a 'break 1')
while (1==1) { switch ($_STATE->status) {
case STATE::INIT:
	$_STATE->project_id = 0;
	require_once "lib/project_select.php";
	$projects = new PROJECT_SELECT($_PERMITS->restrict("task_edit"));
	$_STATE->project_select = serialize(clone($projects));
	if ($projects->selected) {
		$_STATE->init = LIST_TASKS;
		$_STATE->status = SELECTED_PROJECT;
		break 1; //re-switch to SELECTED_PROJECT
	}
	$_STATE->msgGreet = "Select the ".ucfirst($projects->get_label("project"));
	Page_out();
	$_STATE->status = SELECT_PROJECT;
	break 2; //return to executive

case SELECT_PROJECT:
	require_once "lib/project_select.php"; //catches $_GET list refresh
	$projects = unserialize($_STATE->project_select);
	$projects->set_state();
	$_STATE->project_select = serialize(clone($projects));
case SELECTED_PROJECT:
	$_STATE->project_name = $projects->selected_name();

case LIST_TASKS:
	$_STATE->set_a_gate(LIST_TASKS); //for a 'goback' - sets status
	list_setup();
	$_STATE->msgGreet = $_STATE->project_name."<br>Select a task record to edit";
	$_STATE->backup = LIST_PROJECTS; //set goback
	Page_out();
	$_STATE->status = SELECT_TASK;
	break 2; //return to executive

case SELECT_TASK:
	record_select();
case SELECTED_TASK:
	$_STATE->set_a_gate(SELECTED_TASK); //for a 'goback' - sets status
	state_fields();
	if ($_STATE->record_id == -1) {
		$_STATE->msgGreet = "New task record";
		$_STATE->status = ADD_TASK;
	} else {
		record_info();
		$_STATE->msgGreet = "Edit task record?";
		$_STATE->status = UPDATE_TASK;
	}
	Page_out();
	$_STATE->goback_to(LIST_TASKS);
	break 2; //return to executive

case ADD_TASK:
	if (isset($_POST["btnReset"])) {
		$_STATE = $_STATE->goback_to(SELECTED_TASK, true);
		break 1;
	}
	state_fields();
	if (new_audit()) {
		$record_id = $_STATE->record_id;
		$_STATE = $_STATE->goback_to(SELECTED_TASK, true);
		$_STATE->record_id = $record_id;
		break 1; //re-switch with new record_id
	}
	Page_out(); //errors...
	break 2; //return to executive

case UPDATE_TASK:
	if (isset($_POST["btnReset"])) {
		$_STATE = $_STATE->goback_to(SELECTED_TASK, true);
		break 1;
	}
	state_fields();
	if (isset($_POST["btnProperties"])) {
		$_STATE->status = PROPERTIES;
		break 1; //re-switch to show property values
	}
	if (update_audit()) {
		$_STATE = $_STATE->goback_to(SELECTED_TASK, true);
		break 1; //re-switch
	}
	Page_out(); //errors...
	break 2; //return to executive

case PROPERTIES:
	require_once "lib/prop_set.php";
	if (!isset($_STATE->propset)) {
		$propset = new PROP_SET($_STATE, "a12", $_STATE->record_id, $_STATE->forwho);
		$_STATE->propset = serialize(clone($propset));
	} else {
		$propset = unserialize($_STATE->propset);
	}
	if (!$propset->state_gate()) { //let PROP_SET continue state gate processing
		$_STATE = $_STATE->goback_to(SELECTED_TASK, True);
		break 1;
	}
	$_STATE->propset = serialize(clone($propset));
	Page_out();
	break 2; //return to executive

default:
	throw_the_bum_out(NULL,"Evicted(".$_STATE->ID."/".__LINE__."): invalid state=".$_STATE->status);
} } //while & switch
//End Main State Gate & return to executive

function state_fields() {
	global $_STATE;

	$_STATE->fields = array( //pagename,DBname,load from DB?,write to DB?,required?,maxlength
			"Name"=>new FIELD("txtName","name",TRUE,TRUE,TRUE,64),
			"Description"=>new AREA_FIELD("txtDesc","description",TRUE,TRUE,TRUE,256),
			"Budget"=>new FIELD("txtBudget","budget",TRUE,TRUE,FALSE,10),
			"Budget_exp"=>new FIELD("txtBudget_exp","budget_exp",TRUE,TRUE,FALSE,10),
			"Inactive As Of"=>new DATE_FIELD("txtInactive","inactive_asof",TRUE,TRUE,FALSE,0),
			);
}

function list_setup() {
	global $_DB, $_STATE;

	$_STATE->records = array();
	$_STATE->records["-1"] = array("--create a new task record--", "");

	$sql = "SELECT * FROM ".$_DB->prefix."a12_task
			WHERE project_idref=".$_STATE->project_id." ORDER BY name;";
	$stmt = $_DB->query($sql);
	$today = COM_now();
	while ($row = $stmt->fetchObject()) {
		$inactive = new DATE_FIELD("null","null",FALSE,FALSE,FALSE,0,FALSE,$row->inactive_asof);
		$title = $inactive->format();
		if (($title != "") && ($today < $inactive->value)) $title = "";
		$_STATE->records[strval($row->task_id)] = array(substr($row->name.": ".$row->description,0,25), $title);
	}
	$stmt->closeCursor();
}

function record_select() {
	global $_DB, $_STATE;

	list_setup(); //restore the record list
	if (!array_key_exists(strval($_POST["selTask"]), $_STATE->records)) {
		throw_the_bum_out(NULL,"Evicted(".__LINE__."): invalid task id ".$_POST["selTask"]); //we're being spoofed
	}
	$_STATE->record_id = intval($_POST["selTask"]);
}

function record_info() {
	global $_DB, $_STATE;

	$sql = "SELECT * FROM ".$_DB->prefix."a12_task WHERE task_id=".$_STATE->record_id.";";
	$stmt = $_DB->query($sql);
	$row = $stmt->fetchObject();
	$_STATE->forwho = $row->name.": ".$row->description; //PROPERTIES wants to see this
	foreach($_STATE->fields as $field=>&$props) { //preset record info on the page
		if ($props->load_from_DB) {
			$props->value($row->{$props->dbname});
		}
	}
	$stmt->closeCursor();
}

function field_input_audit() {
	global $_DB, $_STATE;

	$errors = "";
	foreach($_STATE->fields as $name => $field) {
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

	return true;
}

function update_db() {
	global $_DB, $_STATE;

	$sql = "UPDATE ".$_DB->prefix."a12_task
			SET name=:name, description=:description, inactive_asof=:inactive";
	if ($_STATE->fields["Budget"]->value() != "") $sql .= ", budget=:budget";
	if ($_STATE->fields["Budget_exp"]->value() != "") $sql .= ", budget_exp=:budget_exp";
	$sql .= " WHERE task_id=".$_STATE->record_id.";";
	$stmt = $_DB->prepare($sql);
	$stmt->bindValue(':name',$_STATE->fields["Name"]->value(),PDO::PARAM_STR);
	$stmt->bindValue(':description',$_STATE->fields["Description"]->value(),PDO::PARAM_STR);
	if ($_STATE->fields["Budget"]->value() != "")
		$stmt->bindValue(':budget',$_STATE->fields["Budget"]->value(),PDO::PARAM_STR);
	if ($_STATE->fields["Budget_exp"]->value() != "")
		$stmt->bindValue(':budget_exp',$_STATE->fields["Budget_exp"]->value(),PDO::PARAM_STR);
	if ($_STATE->fields["Inactive As Of"]->value() == "") {
		$stmt->bindValue(':inactive', NULL, db_connect::PARAM_DATE);
	} else {
		$stmt->bindValue(':inactive',$_STATE->fields["Inactive As Of"]->value(),db_connect::PARAM_DATE);
	}
	$stmt->execute();
}

function update_audit() {
	global $_DB, $_STATE;

	if (!field_input_audit()) return false;

	update_db();

	$_STATE->msgStatus = "The task record for \"".$_STATE->fields["Name"]->value()."\" has been updated";
	return true;
}

function new_audit() {
	global $_DB, $_STATE;

	if (!field_input_audit()) return false;
	
	$hash = md5($_STATE->fields["Name"]->value().$_STATE->fields["Description"]->value());
	$sql = "INSERT INTO ".$_DB->prefix."a12_task (name, project_idref)
			VALUES (:hash, ".$_STATE->project_id.");";
	$stmt = $_DB->prepare($sql);
	$stmt->bindValue(':hash',$hash,PDO::PARAM_STR);
	$stmt->execute();

	$sql = "SELECT task_id FROM ".$_DB->prefix."a12_task WHERE name=:hash;";
	$stmt = $_DB->prepare($sql);
	$stmt->bindValue(':hash',$hash,PDO::PARAM_STR);
	$stmt->execute();
	$_STATE->record_id = $stmt->fetchObject()->task_id;
	$stmt->closeCursor();

	update_db();

	$sql = "INSERT INTO ".$_DB->prefix."a14_subtask (task_idref,name,description)
			VALUES (".$_STATE->record_id.",'seed','initial seed subtask - please change');";
	$_DB->exec($sql);

	$_STATE->msgStatus = "The task record for \"".$_STATE->fields["Name"]->value()."\" has been added to the project";
	return true;
}

function Page_out() {
	global $_DB, $_STATE;

	if ($_STATE->status == LIST_PROJECTS)
		$scripts = array("call_server.js");
	elseif ($_STATE->status == PROPERTIES) {
		global $propset;
		$_STATE->msgGreet = $propset->greeting();
		$scripts = $propset->set_script();
	} else {
		$scripts = array();
	}
	EX_pageStart($scripts); //standard HTML page start stuff - insert SCRIPTS here
	EX_pageHead(); //standard page headings - after any scripts

	switch ($_STATE->status) {

	case LIST_PROJECTS:
		global $projects;
		echo $projects->set_list();
		break; //end LIST_PROJECTS status ----END STATE: EXITING FROM PROCESS----

	case LIST_TASKS:
?>
  <p>
<form method="post" name="frmAction" id="frmAction_ID" action="<?php echo $_SESSION["IAm"]; ?>">
  <select name='selTask' size="<?php echo count($_STATE->records); ?>" onclick="this.form.submit()" style='cursor:pointer'>
<?php
		$title = "Click to select";
		foreach($_STATE->records as $value => $name) {
			$opacity = "1.0"; //opacity value = fully opaque
			$inact = "";
			if ($name[1] != "") {
				$opacity = "0.5";
				$inact = "; inactive as of ".$name[1];
			}
			echo "    <option value=\"".$value."\" title='".$title.$inact."' style='opacity:".$opacity."'>".$name[0]."\n";
		}
?>
  </select>
</form>
  </p>
<?php
		break; //end LIST_TASKS status ----END STATUS PROCESSING----

	case ADD_TASK:
	case UPDATE_TASK:
?>
<form method="post" name="frmAction" id="frmAction_ID" action="<?php echo $_SESSION["IAm"]; ?>">
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
      <td class="label"><?php echo $_STATE->fields['Budget']->HTML_label("Budget(labor): "); ?></td>
      <td colspan="2"><?php echo $_STATE->fields['Budget']->HTML_input(10) ?></td>
    </tr>
    <tr>
      <td class="label"><?php echo $_STATE->fields['Budget_exp']->HTML_label("Budget(non-labor): "); ?></td>
      <td colspan="2"><?php echo $_STATE->fields['Budget_exp']->HTML_input(10) ?></td>
    </tr>
    <tr>
      <td class="label"><?php echo $_STATE->fields['Inactive As Of']->HTML_label("Inactive As Of: "); ?></td>
      <td><?php foreach ($_STATE->fields['Inactive As Of']->HTML_input() as $line) echo $line."\n"; ?></td>
      <td>&nbsp</td>
    </tr>
  </table>
  <p>
 <?php
		if ($_STATE->status == ADD_TASK ) {
			echo FIELD_edit_buttons(FIELD_ADD);
		} else {
			echo Field_edit_buttons(FIELD_UPDATE);
?>
  <br><button type='submit' name='btnProperties' id='btnProperties_ID' value='values'>Show Properties</button><br>
<?php
	} ?>
</form>
<?php
		break; //end ADD/UPDATE_TASK status ----END STATUS PROCESSING----

	case PROPERTIES: //list properties and allow new entry:
		$state = $propset->get_page();
		EX_pageEnd($state);
		return;
		break; //end PROPERTIES status ----END STATUS PROCESSING----

	default:
		throw_the_bum_out(NULL,"Evicted(".$_STATE->ID."/".__LINE__."): invalid state=".$_STATE->status);

	} //end select ($_STATE->status) ----END STATE: EXITING FROM PROCESS----

	EX_pageEnd(); //standard end of page stuff

} //end Page_out()
?>
