<?php
//copyright 2015 C.D.Price. Licensed under Apache License, Version 2.0
//See license text at http://www.apache.org/licenses/LICENSE-2.0
if (!$_PERMITS->can_pass("event_edit")) throw_the_bum_out(NULL,"Evicted(".__LINE__."): no permit");

require_once "field_edit.php";

define('SELECT_PROJECT', STATE::SELECT);
define('SELECTED_PROJECT', STATE::SELECTED);
define('SELECT_EVENT', STATE::SELECT + 1);
define('SELECTED_EVENT', STATE::SELECTED + 1);

//Main State Gate: (the while (1==1) allows a loop back through the switch using a 'break 1')
while (1==1) { switch ($_STATE->status) {
case STATE::INIT:
	$_STATE->project_id = 0;
	require_once "project_select.php";
	$projects = new PROJECT_SELECT($_PERMITS->restrict("event_edit"));
	$_STATE->project_select = serialize(clone($projects));
	if ($projects->selected) {
		$_STATE->status = SELECTED_PROJECT;
		break 1; //re-switch to SELECTED_PROJECT
	}
	$_STATE->msgGreet = "Select the project for this event";
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
	list_setup();
	$_STATE->msgGreet = $_STATE->project_name."<br>Select an event record to edit";
	$_STATE->status = SELECT_EVENT;
	break 2;
case SELECT_EVENT:
	record_select();
	$_STATE->status = SELECTED_EVENT; //for possible goback
	$_STATE->replace();
//	break 1; //re_switch
case SELECTED_EVENT:
	state_fields();
	if ($_STATE->record_id == -1) {
		$_STATE->msgGreet = "New event record";
		$_STATE->status = STATE::ADD;
	} else {
		record_info();
		$_STATE->msgGreet = "Edit event record?";
		$_STATE->status = STATE::UPDATE;
	}
	break 2;
case STATE::ADD:
	state_fields();
	$_STATE->msgGreet = "New event record";
	if (isset($_POST["btnReset"])) {
		break 2;
	}
	if (new_audit()) {
		$_STATE->status = STATE::DONE;
		$_STATE->goback(1); //setup for goback
	}
	break 2;
case STATE::UPDATE:
	state_fields();
	$_STATE->msgGreet = "Edit event record";
	if (isset($_POST["btnReset"])) {
		record_info();
		break 2;
	}
	if (update_audit()) {
		$_STATE->status = STATE::DONE;
		$_STATE->goback(1); //setup for goback
	}
	break 2;
default:
	throw_the_bum_out(NULL,"Evicted(".__LINE__."): invalid state=".$_STATE->status);
} } //while & switch
//End Main State Gate

function state_fields() {
	global $_STATE;

	$_STATE->fields = array( //pagename,DBname,load from DB?,write to DB?,required?,maxlength
			"Name"=>new FIELD("txtName","name",TRUE,TRUE,TRUE,64),
			"Description"=>new AREA_FIELD("txtDesc","description",TRUE,TRUE,TRUE,256),
			"Budget"=>new FIELD("txtBudget","budget",TRUE,TRUE,FALSE,10),
			"Inactive As Of"=>new DATE_FIELD("txtInactive","inactive_asof",TRUE,TRUE,FALSE,0),
			);
}

function list_setup() {
	global $_DB, $_STATE;

	$_STATE->records = array();
	$_STATE->records["-1"] = "--create a new event record--";

	$sql = "SELECT * FROM ".$_DB->prefix."a30_event
			WHERE project_idref=".$_STATE->project_id." ORDER BY name;";
	$stmt = $_DB->query($sql);
	while ($row = $stmt->fetchObject()) {
		$_STATE->records[strval($row->event_id)] = substr($row->name.": ".$row->description,0,25);
	}
	$stmt->closeCursor();
}

function record_select() {
	global $_STATE;

	list_setup(); //restore the record list
	if (!array_key_exists(strval($_POST["selEvent"]), $_STATE->records)) {
		throw_the_bum_out(NULL,"Evicted(".__LINE__."): invalid event id ".$_POST["selEvent"]); //we're being spoofed
	}
	$_STATE->record_id = intval($_POST["selEvent"]);
}

function record_info() {
	global $_DB, $_STATE;

	$sql = "SELECT * FROM ".$_DB->prefix."a30_event WHERE event_id=".$_STATE->record_id.";";
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

//Should check to see if inactive is greater than any eventlogs?

	foreach ($_STATE->fields as $name => $field) {
		$field->disabled = true;
	}

	return TRUE;
}

function update_db() {
	global $_DB, $_STATE;

	$sql = "UPDATE ".$_DB->prefix."a30_event
			SET name=:name, description=:description, inactive_asof=:inactive";
	if ($_STATE->fields["Budget"]->value() != "") $sql .= ", budget=:budget";
	$sql .= " WHERE event_id=".$_STATE->record_id.";";
	$stmt = $_DB->prepare($sql);
	$stmt->bindValue(':name',$_STATE->fields["Name"]->value(),PDO::PARAM_STR);
	$stmt->bindValue(':description',$_STATE->fields["Description"]->value(),PDO::PARAM_STR);
	if ($_STATE->fields["Budget"]->value() != "")
		$stmt->bindValue(':budget',$_STATE->fields["Budget"]->value(),PDO::PARAM_STR);
	if ($_STATE->fields["Inactive As Of"]->value() == "") {
		$stmt->bindValue(':inactive', NULL, db_connect::PARAM_DATE);
	} else {
		$stmt->bindValue(':inactive',$_STATE->fields["Inactive As Of"]->value(),db_connect::PARAM_DATE);
	}
	$stmt->execute();
}

function update_audit() {
	global $_DB, $_STATE;

	if (!field_input_audit()) return FALSE;

	update_db();

	$_STATE->msgStatus = "The event record for \"".$_STATE->fields["Name"]->value()."\" has been updated";
	return TRUE;
}

function new_audit() {
	global $_DB, $_STATE;

	if (!field_input_audit()) return FALSE;
	
	$hash = md5($_STATE->fields["Name"]->value().$_STATE->fields["Description"]->value());
	$sql = "INSERT INTO ".$_DB->prefix."a30_event (name, project_idref)
			VALUES (:hash, ".$_STATE->project_id.");";
	$stmt = $_DB->prepare($sql);
	$stmt->bindValue(':hash',$hash,PDO::PARAM_STR);
	$stmt->execute();

	$sql = "SELECT event_id FROM ".$_DB->prefix."a30_event WHERE name=:hash;";
	$stmt = $_DB->prepare($sql);
	$stmt->bindValue(':hash',$hash,PDO::PARAM_STR);
	$stmt->execute();
	$_STATE->record_id = $stmt->fetchObject()->event_id;
	$stmt->closeCursor();

	update_db();

	$_STATE->msgStatus = "The event record for \"".$_STATE->fields["Name"]->value()."\" has been added to the project";
	return TRUE;
}

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
case SELECT_EVENT:
?>
  <p>
<form method="post" name="frmAction" id="frmAction_ID" action="<?php echo $_SERVER['SCRIPT_NAME']; ?>">
  <select name='selEvent' size="<?php echo count($_STATE->records); ?>" onclick="this.form.submit()">
<?php
	foreach($_STATE->records as $value => $name) {
  		echo "    <option value=\"".$value."\">".$name."\n";
	} ?>
  </select>
</form>
  </p>
<?php //end SELECT_EVENT status ----END STATUS PROCESSING----
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
      <td class="label"><?php echo $_STATE->fields['Budget']->HTML_label("Budget: "); ?></td>
      <td colspan="2"><?php echo $_STATE->fields['Budget']->HTML_input(10) ?></td>
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

