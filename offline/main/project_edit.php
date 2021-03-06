<?php
//copyright 2015-2016,2019 C.D.Price. Licensed under Apache License, Version 2.0
//See license text at http://www.apache.org/licenses/LICENSE-2.0
if (!$_PERMITS->can_pass("project_edit")) throw_the_bum_out(NULL,"Evicted(".__LINE__."): no permit");

require_once "lib/field_edit.php";

//The Main State Gate cases:
define('LIST_PROJECTS',		STATE::INIT);
define('SELECT_PROJECT',		LIST_PROJECTS + 1);
define('SELECTED_PROJECT',		LIST_PROJECTS + 2);
define('ADD_PROJECT',			LIST_PROJECTS + 3);
define('UPDATE_PROJECT',		LIST_PROJECTS + 4);
define('PREFERENCES',		STATE::INIT + 10);

//Main State Gate: (the while (1==1) allows a loop back through the switch using a 'break 1')
while (1==1) { switch ($_STATE->status) {
case LIST_PROJECTS:
	$_STATE->accounting_id = 0;
	$_STATE->acct_list = array();
	$_STATE->noSleep[] = "acct_list";
	$_STATE->budget_by = "t";
	require_once "lib/project_select.php";
	$projects = new PROJECT_SELECT();
	$projects->show_new = true;
	$_STATE->project_select = serialize(clone($projects));
	$_STATE->projLabel = ucfirst($projects->get_label("project"));
	$_STATE->msgGreet = "Select a ".$_STATE->projLabel." record to edit";
	Page_out();
	$_STATE->status = SELECT_PROJECT;
	break 2; //return to executive

case SELECT_PROJECT:
	require_once "lib/project_select.php"; //catches $_GET list refresh (assumes break 2)
	$projects = unserialize($_STATE->project_select);
	$projects->set_state();
	$_STATE->project_select = serialize(clone($projects));
	$_STATE->record_id = $_STATE->project_id;
	$_STATE->status = SELECTED_PROJECT; //for possible goback
	$_STATE->replace();
case SELECTED_PROJECT:
	$_STATE->set_a_gate(SELECTED_PROJECT); //for a 'goback' - sets status
	state_fields(); //creates the accounting list for display
	if ($_STATE->record_id == -1) {
		$_STATE->msgGreet = "New ".$_STATE->projLabel." record";
		$_STATE->status = ADD_PROJECT;
	} else {
		record_info();
		$_STATE->msgGreet = "Edit ".$_STATE->projLabel." record";
		$_STATE->status = UPDATE_PROJECT;
	}
	$_STATE->goback_to(LIST_PROJECTS);
	Page_out();
	break 2; //return to executive

case ADD_PROJECT:
	state_fields(); //creates the accounting list for audit
	$_STATE->msgGreet = "New ".$_STATE->projLabel." record";
	if (isset($_POST["btnReset"])) {
		$_STATE = $_STATE->goback_to(SELECTED_PROJECT, true);
		break 1;
	}
	if (new_audit()) {
		$record_id = $_STATE->record_id;
		$_STATE = $_STATE->goback_to(SELECTED_PROJECT, true);
		$_STATE->record_id = $record_id;
		break 1; //re-switch with new record_id
	}
	Page_out(); //errors...
	break 2; //return to executive

case UPDATE_PROJECT:
	if (isset($_POST["btnPrefs"])) {
		$_STATE->status = PREFERENCES;
		break 1; //re-switch to show preferences
	}
	state_fields(); //creates the accounting list for audit
	$_STATE->msgGreet = "Edit ".$_STATE->projLabel." record";
	if (isset($_POST["btnReset"])) {
		$_STATE = $_STATE->goback_to(SELECTED_PROJECT, true);
		break 1;
	}
	if (update_audit()) {
		$_STATE = $_STATE->goback_to(SELECTED_PROJECT, true);
		break 1; //re-switch
	}
	Page_out(); //errors...
	break 2; //return to executive

case PREFERENCES:
	require_once "lib/preference_set.php";
	if (!isset($_STATE->prefset)) { //first time thru
		$category = ($_PERMITS->can_pass(PERMITS::_SUPERUSER)) ? PREF_SET::STRUCTURAL : PREF_SET::COSMETIC;
		$prefset = new PREF_SET($_STATE,"a10", $_STATE->record_id, $category, $_STATE->forwho);
		$_STATE->prefset = serialize(clone($prefset));
	} else {
		$prefset = unserialize($_STATE->prefset);
	}
	if (!$prefset->state_gate($_STATE)) {
		$_STATE = $_STATE->goback_to(SELECTED_PROJECT, true);
		break 1;
	}
	$_STATE->prefset = serialize(clone($prefset)); //leave $prefset intact for later services
	$_STATE->replace();
	Page_out();
	break 2; //return to executive

default:
	throw_the_bum_out(NULL,"Evicted(".$_STATE->ID."/".__LINE__."): invalid state=".$_STATE->status);
} } //while & switch
//End Main State Gate & return to executive

function state_fields() {
	global $_DB, $_STATE;

	$_STATE->fields = array( //pagename,DBname,load from DB?,write to DB?,required?,maxlength
			"Name"=>new FIELD("txtName","name",TRUE,TRUE,TRUE,64),
			"Description"=>new AREA_FIELD("txtDesc","description",TRUE,TRUE,TRUE,256),
			"Budget"=>new FIELD("txtBudget","budget",TRUE,TRUE,FALSE,10),
			"Budget_exp"=>new FIELD("txtBudget_exp","budget_exp",TRUE,TRUE,FALSE,10),
			"Budget_by"=>new FIELD("null","budget_by",TRUE,TRUE,FALSE,1), //audit returns NULL
			"Mileage"=>new FIELD("txtMileage","mileage",TRUE,TRUE,FALSE,7),
			"Close Date"=>new DATE_FIELD("txtClose","close_date",TRUE,TRUE,TRUE,0),
			"Inactive As Of"=>new DATE_FIELD("txtInactive","inactive_asof",TRUE,TRUE,FALSE,0),
			);
	$_STATE->acct_list = array();
	$sql = "SELECT * FROM ".$_DB->prefix."a20_accounting
			WHERE organization_idref=".$_SESSION["organization_id"]." ORDER BY timestamp;";
	$stmt = $_DB->query($sql);
	while ($row = $stmt->fetchObject()) {
		$_STATE->acct_list[strval($row->accounting_id)] = substr($row->name.": ".$row->description,0,25);
	}
	$stmt->closeCursor();
	$_STATE->BudgetBy_list = array("p"=>"Project","t"=>"Task");
}

function record_info() {
	global $_DB, $_STATE;

	$sql = "SELECT * FROM ".$_DB->prefix."a10_project WHERE project_id=".$_STATE->record_id.";";
	$stmt = $_DB->query($sql);
	$row = $stmt->fetchObject();
	foreach($_STATE->fields as $field => &$props) { //preset record info on the page
		if ($props->load_from_DB) {
			$props->value($row->{$props->dbname});
		}
	}
	$_STATE->accounting_id = $row->accounting_idref;
	$_STATE->forwho = $row->name.": ".$row->description; //PREFERENCES wants to see this
	$stmt->closeCursor();
}

function field_input_audit() {
	global $_STATE;

	$errors = "";
	foreach($_STATE->fields as $name => $field) {
		if (($msg = $field->audit()) === true) continue;
		$errors .= "<br>".$name.": ".$msg;
	}
	if ($errors != "") {
		$_STATE->msgStatus = "Error:".$errors;
		return false;
	}

	$diff = date_diff($_STATE->fields["Close Date"]->value, COM_NOW(), true);
	if ($diff->m > 2) {
		$_STATE->msgStatus = "The Close Date is suspect - proceeding anyway";
	}

	if (!array_key_exists(strval($_POST["selBudgetBy"]), $_STATE->BudgetBy_list)) {
		throw_the_bum_out(NULL,"Evicted(".__LINE__."): invalid budget-by value ".$_POST["selBudgetBy"]); //we're being spoofed
	}
	$_STATE->fields["Budget_by"]->value = $_POST["selBudgetBy"];
	if (!array_key_exists(strval($_POST["selAccounting"]), $_STATE->acct_list)) {
		throw_the_bum_out(NULL,"Evicted(".__LINE__."): invalid accounting id ".$_POST["selAccounting"]); //we're being spoofed
	}
	$_STATE->accounting_id = intval($_POST["selAccounting"]);

//Should check to see if inactive is greater than any timelogs?

	foreach ($_STATE->fields as $name => $field) {
		$field->disabled = true;
	}

	return TRUE;

}

function update_db() {
	global $_DB, $_STATE;

	$sql = "UPDATE ".$_DB->prefix."a10_project
			SET name=:name, description=:description, close_date=:close,
			budget_by=:budget_by, accounting_idref=:accounting, inactive_asof=:inactive";
	if ($_STATE->fields["Budget"]->value() != "") $sql .= ", budget=:budget";
	if ($_STATE->fields["Budget_exp"]->value() != "") $sql .= ", budget_exp=:budget_exp";
	if ($_STATE->fields["Mileage"]->value() != "") $sql .= ", mileage=:mileage";
	$sql .= " WHERE project_id=".$_STATE->record_id.";";
	$stmt = $_DB->prepare($sql);
	$stmt->bindValue(':name', $_STATE->fields["Name"]->value(), PDO::PARAM_STR);
	$stmt->bindValue(':description', $_STATE->fields["Description"]->value(), PDO::PARAM_STR);
	$stmt->bindValue(':budget_by', $_STATE->fields["Budget_by"]->value(), PDO::PARAM_STR);
	$stmt->bindValue(':close', $_STATE->fields["Close Date"]->value(), db_connect::PARAM_DATE);
	$stmt->bindValue(':accounting', $_STATE->accounting_id, PDO::PARAM_INT);
	if ($_STATE->fields["Budget"]->value() != "")
		$stmt->bindValue(':budget',$_STATE->fields["Budget"]->value(),PDO::PARAM_STR);
	if ($_STATE->fields["Budget_exp"]->value() != "")
		$stmt->bindValue(':budget_exp',$_STATE->fields["Budget_exp"]->value(),PDO::PARAM_STR);
	if ($_STATE->fields["Mileage"]->value() != "")
		$stmt->bindValue(':mileage',$_STATE->fields["Mileage"]->value(),PDO::PARAM_STR);
	if ($_STATE->fields["Inactive As Of"]->value() == "") {
		$stmt->bindValue(':inactive', NULL, db_connect::PARAM_DATE);
	} else {
		$stmt->bindValue(':inactive', $_STATE->fields["Inactive As Of"]->value(), db_connect::PARAM_DATE);
	}
	$stmt->execute();
}

function update_audit() {
	global $_STATE;

	if (!field_input_audit()) return FALSE;

	update_db();

	$_STATE->msgStatus = "The project record for \"".$_STATE->fields["Name"]->value()."\" has been updated";
	return TRUE;
}

function new_audit() {
	global $_DB, $_STATE;

	if (!field_input_audit()) return FALSE;

	//hash the name to make sure we get this record back - then update it with correct name:
	$hash = md5($_STATE->fields["Name"]->value().$_STATE->fields["Description"]->value());
	$sql = "INSERT INTO ".$_DB->prefix."a10_project (name, organization_idref)
			VALUES (:hash,".$_SESSION["organization_id"].");";
	$stmt = $_DB->prepare($sql);
	$stmt->bindValue(':hash',$hash,PDO::PARAM_STR);
	$stmt->execute();

	$sql = "SELECT project_id FROM ".$_DB->prefix."a10_project WHERE name=:hash;";
	$stmt = $_DB->prepare($sql);
	$stmt->bindValue(':hash',$hash,PDO::PARAM_STR);
	$stmt->execute();
	$_STATE->record_id = $stmt->fetchObject()->project_id;
	$stmt->closeCursor();

	update_db();

	$sql = "INSERT INTO ".$_DB->prefix."a12_task (project_idref,name,description)
			VALUES (".$_STATE->record_id.",'".$hash."','initial seed task - please change');";
	$_DB->exec($sql);
	$sql = "SELECT task_id FROM ".$_DB->prefix."a12_task WHERE name='".$hash."';";
	$stmt = $_DB->query($sql);
	$ID = $stmt->fetchObject()->task_id;
	$stmt->closeCursor();
	$sql = "UPDATE ".$_DB->prefix."a12_task SET name='seed' WHERE task_id=".$ID.";";
	$_DB->exec($sql);

	$sql = "INSERT INTO ".$_DB->prefix."a14_subtask (task_idref,name,description)
			VALUES (".$ID.",'seed','initial seed subtask - please change');";
	$_DB->exec($sql);

	$_STATE->msgStatus = "The project record for \"".$_STATE->fields["Name"]->value()."\" has been added to your organization";
	return TRUE;
}

function Page_out() {
	global $_DB, $_STATE;

	if ($_STATE->status == LIST_PROJECTS) {
		$scripts = array("call_server.js");
	} else if ($_STATE->status == PREFERENCES) {
		global $prefset;
		$_STATE->msgGreet = $prefset->greeting();
		$scripts = $prefset->set_script();
	} else {
		$scripts = array();
	}
	EX_pageStart($scripts); //standard HTML page start stuff - insert SCRIPTS here
	EX_pageHead(); //standard page headings - after any scripts

	switch ($_STATE->status) {

	case LIST_PROJECTS:
		global $projects;
		echo $projects->set_list();
		break; //end LIST_PROJECTS status ----END STATUS PROCESSING----

	case ADD_PROJECT:
	case UPDATE_PROJECT:
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
      <td class="label"><label for="selBudgetBy_ID" class='required'>*Budget by:</label></td>
      <td>
        <select name='selBudgetBy' id='selBudgetBy_ID' size="<?php echo count($_STATE->BudgetBy_list); ?>">
<?php
		foreach($_STATE->BudgetBy_list as $value => $name) {
	  		echo "        <option value=\"".$value."\"";
			if ($_STATE->fields["Budget_by"]->value == $value) echo " selected";
			echo ">".$name."\n";
		}
?>
        </select>
      </td>
      <td>&nbsp</td>
    </tr>
    <tr>
      <td class="label"><?php echo $_STATE->fields['Mileage']->HTML_label("Mileage reimbursement: "); ?></td>
      <td colspan="2"><?php echo $_STATE->fields['Mileage']->HTML_input(7) ?></td>
    </tr>
    <tr>
      <td class="label"><?php echo $_STATE->fields['Close Date']->HTML_label("Log entry Close Date: "); ?></td>
      <td><?php foreach ($_STATE->fields['Close Date']->HTML_input() as $line) echo $line."\n"; ?></td>
       <td>&nbsp</td>
    </tr>
    <tr>
      <td class="label"><?php echo $_STATE->fields['Inactive As Of']->HTML_label("Inactive As Of: "); ?></td>
      <td><?php foreach ($_STATE->fields['Inactive As Of']->HTML_input() as $line) echo $line."\n"; ?></td>
      <td>&nbsp</td>
    </tr>
    <tr>
      <td class="label"><label for="selAccounting_ID" class='required'>*Accounting group:</label></td>
      <td>
        <select name='selAccounting' id='selAccounting_ID' size="<?php echo count($_STATE->acct_list); ?>">
<?php
		foreach($_STATE->acct_list as $value => $name) {
	  		echo "        <option value=\"".$value."\"";
			if ($_STATE->accounting_id == $value) echo " selected";
			echo ">".$name."\n";
		}
?>
        </select>
      </td>
      <td>&nbsp</td>
    </tr>
    <tr><td></td><td>
      <button type="submit" name="btnPrefs" id="btnPrefs_ID" value="preferences">Preferences</button>
    </td></tr>
  </table>
  <p>
<?php
		if ($_STATE->status == ADD_PROJECT ) {
			echo FIELD_edit_buttons(FIELD_ADD);
		} else {
			echo Field_edit_buttons(FIELD_UPDATE);
		}
?>
</form>
<?php
		break; //end ADD/UPDATE_PROJECT status ----END STATUS PROCESSING----

	case PREFERENCES: //show preferences and allow update:
		$state = $prefset->get_page();
		EX_pageEnd($state);
		return;
		break;

	default:

		throw_the_bum_out(NULL,"Evicted(".$_STATE->ID."/".__LINE__."): invalid state=".$_STATE->status);

	} //end select ($_STATE->status) ----END STATE: EXITING FROM PROCESS----

	EX_pageEnd(); //standard end of page stuff

} //end Page_out()
?>
