<?php
//copyright 2015-2016,2018,2019 C.D.Price. Licensed under Apache License, Version 2.0
//See license text at http://www.apache.org/licenses/LICENSE-2.0

require_once "lib/field_edit.php";

//The Main State Gate cases:
define('LIST_PERSONS',		STATE::INIT);
define('SELECT_PERSON',			LIST_PERSONS + 1);
define('SELECTED_PERSON',		LIST_PERSONS + 2);
define('UPDATE_PERSON',			LIST_PERSONS + 3);
define('ADD_PERSON',			LIST_PERSONS + 4);
define('DELETE_PERSON',			LIST_PERSONS + 5);
define('LIST_ALIENS',		LIST_PERSONS + 10); //'aliens' are persons not connected to this org
define('ADD_ALIEN',				LIST_ALIENS + 1);
define('PREFERENCES',		STATE::INIT + 20);

//Main State Gate: (the while (1==1) allows a loop back through the switch using a 'break 1')
while (1==1) { switch ($_STATE->status) {
case LIST_PERSONS:
	$_STATE->person_organization_id = 0;
	$_STATE->person_id = 0;
	$_STATE->backup = LIST_PERSONS;
	require_once "lib/person_select.php";
	$persons = new PERSON_SELECT();
	if (!$_PERMITS->can_pass("person_edit")) {
		$persons->set_state($_SESSION["person_id"]);
		$_STATE->status = SELECTED_PERSON;
		break 1; //re-switch to SELECTED_PERSON
	}
	$persons->show_new = true;
	$persons->show_inactive = true;
	$_STATE->person_select = serialize(clone($persons));
	$_STATE->msgGreet = "Select a person record to edit";
	Page_out();
	$_STATE->status = SELECT_PERSON;
	break 2; //return to executive

case SELECT_PERSON:
	require_once "lib/person_select.php"; //catches $_GET list refresh
	$persons = unserialize($_STATE->person_select);
	$persons->selected = false; //if only one in list, will be set true at object construct
	$persons->set_state();
	$_STATE->person_select = serialize(clone($persons));
	if ($_STATE->person_id == -1) { //adding...
		if (list_aliens()) { //aliens to connect
			$_STATE->set_a_gate(SELECTED_PERSON); //for a 'goback' - sets status
			$_STATE->status = LIST_ALIENS; //new state to list aliens
			break 1; //go list 'em
		}
	}
case SELECTED_PERSON:
	$_STATE->set_a_gate(SELECTED_PERSON); //for a 'goback' - sets status
	state_fields();
	$_STATE->record_id = $_STATE->person_id;
	if ($_STATE->record_id == -1) {
		$_STATE->msgGreet = "New person record";
		Page_out();
		$_STATE->status = ADD_PERSON;
	} else {
		record_info();
		$_STATE->msgGreet = "Edit person record?";
		Page_out();
		$_STATE->status = UPDATE_PERSON;
	}
	$_STATE->goback_to(LIST_PERSONS);
	break 2; //return to executive

case ADD_PERSON:
	state_fields();
	$_STATE->msgGreet = "New person record";
	if (isset($_POST["btnReset"])) {
		$_STATE = $_STATE->goback_to(SELECTED_PERSON, true);
		break  1;
	}
	if (new_audit()) {
		$record_id = $_STATE->record_id;
		$_STATE = $_STATE->goback_to(SELECTED_PERSON,true);
		$_STATE->person_id = $record_id;
		break 1; //re-switch with new record_id
	}
	Page_out(); //errors...
	break 2; //return to executive

case UPDATE_PERSON:
	if (isset($_POST["btnPrefs"])) {
		$_STATE->status = PREFERENCES;
		break 1; //re-switch to show preferences
	}
	//fall thru
case DELETE_PERSON:
	state_fields();
	$_STATE->msgGreet = "Edit person record";
	if (isset($_POST["btnReset"])) {
		$_STATE = $_STATE->goback_to(SELECTED_PERSON, true);
		break  1;
	}
	if (isset($_POST["btnSubmit"])) {
		if (update_audit()) {
			$msg = $_STATE->msgStatus;
			$_STATE = $_STATE->goback_to(SELECTED_PERSON, true);
			$_STATE->msgStatus = $msg;
			break 1; //re-switch
		}
		Page_out(); //errors...
		break 2; //return to executive
	}
	if (isset($_POST["btnRemove"])) {
		if (remove_audit()) {
			$msg = $_STATE->msgStatus;
			$_STATE = $_STATE->goback_to(LIST_PERSONS, true);
			$_STATE->msgStatus = $msg;
			break 1; //re-switch
		}
		Page_out(); //disallowed...
		break 2; //return to executive
	}
	if (isset($_POST["btnDelete"])) {
		if (delete_audit()) {
			$msg = $_STATE->msgStatus;
			$_STATE = $_STATE->goback_to(LIST_PERSONS, true);
			$_STATE->msgStatus = $msg;
			break 1; //re-switch
		}
		Page_out(); //disallowed...
		break 2; //return to executive
	}
	throw_the_bum_out(NULL,"Evicted(".__LINE__."): invalid Submit");

case LIST_ALIENS:
	$_STATE->msgGreet = "Connect to this organization - or create a new person";
	Page_out();
	$_STATE->status = ADD_ALIEN;
	break 2;

case ADD_ALIEN:
	if ($_POST["selPerson"][0] == -1) { //create a new person
		$_STATE = $_STATE->goback_to(SELECTED_PERSON, true);
		$_STATE->person_id = -1;		
		break 1;
	}
	list_aliens();
	connect_alien();
	$_STATE = $_STATE->goback_to(LIST_PERSONS, true);
	break 1; //re-switch

case PREFERENCES:
	require_once "lib/preference_set.php";
	if (!isset($_STATE->prefset)) { //first time thru
		$category = ($_PERMITS->can_pass(PERMITS::_SUPERUSER)) ? PREF_SET::STRUCTURAL : PREF_SET::COSMETIC;
		$prefset = new PREF_SET($_STATE,"c10", $_STATE->record_id, $category, $_STATE->forwho);
		$_STATE->prefset = serialize(clone($prefset));
	} else {
		$prefset = unserialize($_STATE->prefset);
	}
	if (!$prefset->state_gate($_STATE)) {
		$_STATE = $_STATE->goback_to(SELECTED_PERSON, true);
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

function list_aliens() {
	global $_DB, $_STATE;

	$aliens = array();
	$sql = "SELECT c00.*, c10.organization_idref FROM ".$_DB->prefix."c00_person AS c00
			LEFT OUTER JOIN ".$_DB->prefix."c10_person_organization AS c10
			ON (c00.person_id = c10.person_idref)
			ORDER BY c00.lastname, c00.firstname, c00.person_id;";
	$stmt = $_DB->query($sql);
	$person = array("last","first",-1,false);
	while ($row = $stmt->fetchObject()) {
		if ($row->person_id == 0) continue; //superduper user
		if ($person[2] != $row->person_id) {
			if ($person[3] == true) {
				$aliens[strval($person[2])] = array($person[0], $person[1]);
			}
			$person = array($row->lastname, $row->firstname, $row->person_id, true);
		}
		if ($row->organization_idref == $_SESSION["organization_id"]) $person[3] = false;
	}
	$stmt->closeCursor();
	if ($person[3] == true) {
		$aliens[strval($person[2])] = array($person[0], $person[1]);
	}
	if (count($aliens) == 0) return false;
	$_STATE->aliens = $aliens;
	$_STATE->noSleep[] = "aliens"; //don't save
	return true;
}

function connect_alien() {
	global $_DB, $_STATE;

	$alien = $_POST["selPerson"][0];
	if (!array_key_exists($alien, $_STATE->aliens)) {
		throw_the_bum_out(NULL,"Evicted(".__LINE__."): invalid person id ".$alien);
	}
	$sql = "INSERT INTO ".$_DB->prefix."c10_person_organization (person_idref, organization_idref)
			VALUES (".$alien.", ".$_SESSION["organization_id"].");";
	$_DB->exec($sql);
}

function state_fields() {
	global $_STATE;

	$_STATE->fields = array( //pagename,DBname,load from DB?,write to DB?,required?,maxlength
			"First Name"=>new FIELD("txtFirstName","firstname",TRUE,TRUE,TRUE,64),
			"Last Name"=>new FIELD("txtLastName","lastname",TRUE,TRUE,TRUE,64),
			"Log ID"=>new FIELD("txtLogID","loginname",TRUE,TRUE,FALSE,64),
			"Password"=>new PSWD_FIELD("txtPswd","password",FALSE,TRUE,FALSE,64),
			"RePassword"=>new PSWD_FIELD("txtRePswd","",FALSE,FALSE,FALSE,64),
			"Email"=>new FIELD("txtEmail","email",TRUE,TRUE,FALSE,64),
			"Inactive As Of"=>new DATE_FIELD("txtInactive","inactive_asof",TRUE,TRUE,FALSE,0),
			);
}

function record_info() {
	global $_DB, $_STATE;

	$sql = "SELECT c00.*, c10.inactive_asof FROM ".$_DB->prefix."c00_person AS c00
			INNER JOIN ".$_DB->prefix."c10_person_organization AS c10
			ON (c00.person_id = c10.person_idref)
			WHERE person_id=".$_STATE->record_id." AND c10.organization_idref=".$_SESSION["organization_id"].";";
	$stmt = $_DB->query($sql);
	$row = $stmt->fetchObject();
	foreach($_STATE->fields as $field=>&$props) { //preset record info on the page
		if ($props->load_from_DB) {
			$props->value($row->{$props->dbname});
		}
	}
	$_STATE->forwho = $row->lastname.", ".$row->firstname; //PREFERENCES wants to see this
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

	if ($_STATE->fields["Password"]->value() != "") {
		if ($_STATE->fields["Password"]->value() != $_STATE->fields["RePassword"]->value()) {
			$_STATE->msgStatus = "Passwords do not match!";
			return FALSE;
		}
	}

	if ($_POST["txtEmail"] != "" ) { //save the "@" that common::input_edit() took out
		$email = explode("@",$_POST["txtEmail"]);
		foreach ($email as &$part) {
			$part = COM_string_decode($part);
		}
		$_STATE->fields["Email"]->value(implode("@", $email));
	}

	return TRUE;

}

function find_login() { //check for dup loginname
	global $_DB, $_STATE;

	if ($_STATE->fields["Log ID"]->value() == "") return -1;

	$sql = "SELECT person_id FROM ".$_DB->prefix."c00_person
			WHERE loginname=:loginname;";
	$stmt = $_DB->prepare($sql);
	$stmt->bindValue(':loginname',$_STATE->fields["Log ID"]->value(),PDO::PARAM_STR);
	$stmt->execute();
	if (!($row = $stmt->fetchObject())) return -1;
	$stmt->closeCursor();
	return $row->person_id;
}

function update_db() {
	global $_DB, $_STATE;

	$sql = "UPDATE ".$_DB->prefix."c00_person
			SET lastname=:lastname, lastsoundex='".soundex($_STATE->fields["Last Name"]->value())."',
			firstname=:firstname";
	if ($_STATE->fields["Log ID"]->value() != "") $sql .= ", loginname=:loginname";
	if ($_STATE->fields["Password"]->value() != "") $sql .= ", password=:password";
	if ($_STATE->fields["Email"]->value() != "") $sql .= ", email=:email";
	$sql .= " WHERE person_id=".$_STATE->record_id.";";
	$stmt = $_DB->prepare($sql);
	$stmt->bindValue(':lastname',$_STATE->fields["Last Name"]->value(),PDO::PARAM_STR);
	$stmt->bindValue(':firstname',$_STATE->fields["First Name"]->value(),PDO::PARAM_STR);
	if ($_STATE->fields["Log ID"]->value() != "")
		$stmt->bindValue(':loginname',$_STATE->fields["Log ID"]->value(),PDO::PARAM_STR);

	if (PHP_VERSION_ID < 50500) require_once "password.php";

	if ($_STATE->fields["Password"]->value() != "")
		$stmt->bindValue(':password',password_hash($_STATE->fields["Password"]->value(), PASSWORD_DEFAULT), PDO::PARAM_STR);
	if ($_STATE->fields["Email"]->value() != "")
		$stmt->bindValue(':email',$_STATE->fields["Email"]->value(),PDO::PARAM_STR);
	$stmt->execute();

	$sql = "UPDATE ".$_DB->prefix."c10_person_organization SET inactive_asof=:inactive
			WHERE person_organization_id=".$_STATE->person_organization_id.";";
	$stmt = $_DB->prepare($sql);
	if ($_STATE->fields["Inactive As Of"]->value() == "") {
		$stmt->bindValue(':inactive', NULL, db_connect::PARAM_DATE);
	} else {
		$stmt->bindValue(':inactive',$_STATE->fields["Inactive As Of"]->value(),db_connect::PARAM_DATE);
	}
	$stmt->execute();
} //update_db()

function update_audit() {
	global $_DB, $_STATE;

	if (!field_input_audit()) return FALSE;
	$login_id = find_login();
	if (($login_id != -1) && ($login_id != $_STATE->record_id)) {
		$_STATE->msgStatus = "This login name already exists";
		return false;
	}

	if ($_STATE->fields["Inactive As Of"]->value() != "") {
		$sql = "SELECT * FROM ".$_DB->prefix."v00_timelog
				WHERE person_id=".$_STATE->record_id."
				AND organization_id=".$_SESSION["organization_id"]."
				AND logdate >= '".$_STATE->fields["Inactive As Of"]->format("Y-m-d")."';";
		$stmt = $_DB->query($sql);
		if ($row = $stmt->fetchObject()) {
			$stmt->closeCursor();
			$_STATE->msgStatus = "There are active time logs subsequent to this inactive date";
			return false;
		}
		$stmt->closeCursor();
		$sql = "SELECT * FROM ".$_DB->prefix."v01_expenselog
				WHERE person_id=".$_STATE->record_id."
				AND organization_id=".$_SESSION["organization_id"]."
				AND logdate >= '".$_STATE->fields["Inactive As Of"]->format("Y-m-d")."';";
		$stmt = $_DB->query($sql);
		if ($row = $stmt->fetchObject()) {
			$stmt->closeCursor();
			$_STATE->msgStatus = "There are active expense logs subsequent to this inactive date";
			return false;
		}
		$stmt->closeCursor();
	}

	update_db();

	$_STATE->msgStatus = "The person record for \"".$_STATE->fields["First Name"]->value()." ".$_STATE->fields["Last Name"]->value()."\" has been updated";
	return TRUE;
} //update_audit()

function new_audit() {
	global $_DB, $_STATE;

	if (!field_input_audit()) return FALSE;
	$login_id = find_login();
	if ($login_id != -1) {
		$_STATE->msgStatus = "This login name already exists";
		return false;
	}

	$hash = md5($_STATE->fields["First Name"]->value().$_STATE->fields["Last Name"]->value());
	$sql = "INSERT INTO ".$_DB->prefix."c00_person (lastname) VALUES (:hash);";
	$stmt = $_DB->prepare($sql);
	$stmt->bindValue(':hash',$hash,PDO::PARAM_STR);
	$stmt->execute();

	$sql = "SELECT person_id FROM ".$_DB->prefix."c00_person WHERE lastname=:hash;";
	$stmt = $_DB->prepare($sql);
	$stmt->bindValue(':hash',$hash,PDO::PARAM_STR);
	$stmt->execute();
	$_STATE->record_id = $stmt->fetchObject()->person_id;
	$stmt->closeCursor();

	update_db();

	$sql = "INSERT INTO ".$_DB->prefix."c10_person_organization (person_idref, organization_idref)
			VALUES (".$_STATE->record_id.", ".$_SESSION["organization_id"].");";
	$_DB->exec($sql);

	$_STATE->msgStatus = "The person record for \"".$_STATE->fields["First Name"]->value()." ".$_STATE->fields["Last Name"]->value()."\" has been added to your organization";
	$_STATE->msgStatus .= "<br>Add a RATE record before entering hours";
	return TRUE;
} //new_audit()

function remove_audit() {
	global $_DB, $_STATE;

	record_info(); //set state fields for display

	if ($_SESSION["person_id"] == $_STATE->record_id) {  //actually, won't get here because remove button
		$_STATE->msgStatus = "You can't remove yourself!";//won't show for yourself
		return FALSE;
	}

	if ((is_null($_STATE->fields["Inactive As Of"]->value)) ||
		($_STATE->fields["Inactive As Of"]->value > COM_NOW())) {
		$_STATE->msgStatus = "This person is still active and cannot be removed";
		return false;
	}

	//delete time logs and collect activities
	$logs = array();
	$activities = array();
	$sql = "SELECT timelog_id, activity_id FROM ".$_DB->prefix."v00_timelog
			WHERE person_id=".$_STATE->record_id."
			AND organization_id=".$_SESSION["organization_id"].";";
	$stmt = $_DB->query($sql);
	while ($row = $stmt->fetchObject()) {
		$logs[] = $row->timelog_id;
		if (!array_search($row->activity_id, $activities)) {
			$activities[] = $row->activity_id;
		}
	}
	$stmt->closeCursor();
	foreach ($logs as $log) {
		$_DB->exec("DELETE FROM ".$_DB->prefix."b00_timelog WHERE timelog_id=".$log.";");
	}
	//delete expense logs and collect activities
	$logs = array();
	$sql = "SELECT expenselog_id, activity_id FROM ".$_DB->prefix."v01_expenselog
			WHERE person_id=".$_STATE->record_id."
			AND organization_id=".$_SESSION["organization_id"].";";
	$stmt = $_DB->query($sql);
	while ($row = $stmt->fetchObject()) {
		$logs[] = $row->expenselog_id;
		if (!array_search($row->activity_id, $activities)) {
			$activities[] = $row->activity_id;
		}
	}
	$stmt->closeCursor();
	foreach ($logs as $log) {
		$_DB->exec("DELETE FROM ".$_DB->prefix."b20_expenselog WHERE expenselog_id=".$log.";");
	}
	//delete orphaned activities
	$orphans = array();
	foreach ($activities as $activity) {
		$sql = "SELECT activity_idref FROM ".$_DB->prefix."b00_timelog
				WHERE activity_idref = ".$activity.";";
		$stmt = $_DB->query($sql);
		if (!($row = $stmt->fetchObject())) $orphans[] = $activity;
		$stmt->closeCursor();
		$sql = "SELECT activity_idref FROM ".$_DB->prefix."b00_expenselog
				WHERE activity_idref = ".$activity.";";
		$stmt = $_DB->query($sql);
		if (!($row = $stmt->fetchObject())) {
			if (!array_search($activity, $orphans)) {
				$orphans[] = $activity;
			}
	 	}
		$stmt->closeCursor();
	}
	foreach ($orphans as $orphan) {
		$_DB->exec("DELETE FROM ".$_DB->prefix."b02_activity WHERE activity_id=".$orphan.";");
	}
	//don't delete eventlogs - may be needed independent of person
	//delete rates
	$rates = array();
	$sql = "SELECT rate_id FROM ".$_DB->prefix."c02_rate AS c02
			JOIN ".$_DB->prefix."a10_project AS a10 ON a10.project_id = c02.project_idref
			WHERE c02.person_idref=".$_STATE->record_id."
			AND a10.organization_idref=".$_SESSION["organization_id"].";";
	$stmt = $_DB->query($sql);
	while ($row = $stmt->fetchObject()) {
		$rates[] = $row->rate_id;
	}
	$stmt->closeCursor();
	foreach ($rates as $rate) {
		$_DB->exec("DELETE FROM ".$_DB->prefix."c02_rate WHERE rate_id=".$rate.";");
	}
	//delete permits
	$permits = array();
	$sql = "SELECT person_permit_id FROM ".$_DB->prefix."c20_person_permit
			WHERE person_idref=".$_STATE->record_id."
			AND organization_idref=".$_SESSION["organization_id"].";";
	$stmt = $_DB->query($sql);
	while ($row = $stmt->fetchObject()) {
		$permits[] = $row->person_permit_id;
	}
	$stmt->closeCursor();
	foreach ($permits as $permit) {
		$_DB->exec("DELETE FROM ".$_DB->prefix."c20_person_permit WHERE person_permit_id=".$permit.";");
	}
	//delete person/org
	$_DB->exec("DELETE FROM ".$_DB->prefix."c10_person_organization
				WHERE person_organization_id = ".$_STATE->person_organization_id.";");

	$_STATE->msgStatus = "The person record for \"".$_STATE->fields["First Name"]->value()." ".$_STATE->fields["Last Name"]->value()."\" has been removed from your organization";
	return true;

} //end function remove_audit()

function delete_audit() {
	return false; //for now, no delete; use inactive instead
	global $_DB, $_STATE;

	record_info(); //set state fields for display

	if ($_SESSION["person_id"] == $_STATE->record_id) {  //actually, won't get here because delete button
		$_STATE->msgStatus = "You can't delete yourself!";//won't show for yourself
		return FALSE;
	}

//delete event logs

	$name = $_STATE->fields["First Name"]->value()." ".$_STATE->fields["Last Name"]->value();

	$sql = "DELETE FROM ".$_DB->prefix."c10_person_organization
			WHERE person_idref=".$_STATE->record_id." AND organization_idref=".$_SESSION["organization_id"].";";
	$_DB->exec($sql);
	$sql = "SELECT COUNT(*) AS count FROM ".$_DB->prefix."c10_person_organization
			WHERE person_idref=".$_STATE->record_id.";";
	$stmt = $_DB->query($sql);
	if ($stmt->fetchObject()->count > 0) {
		$_STATE->msgStatus = "The person \"".$name."\" has been removed from your organization";
		$stmt->closeCursor;
		return TRUE;
	}
	$stmt->closeCursor();
	$sql = "DELETE FROM ".$_DB->prefix."c20_person_permit
			WHERE person_organization_idref=".$_STATE->person_organization_id.";";
	$_DB->exec($sql);
	$sql = "DELETE FROM ".$_DB->prefix."c00_person WHERE person_id=".$_STATE->record_id.";";
	$_DB->exec($sql);

	$_STATE->msgStatus = "The person record for \"".$name."\" has been deleted";
	return TRUE;
} //delete_audit()

function Page_out() {
	global $_DB, $_STATE;

	$scripts = array("call_server.js");
	if ($_STATE->status == PREFERENCES) {
		global $prefset;
		$_STATE->msgGreet = $prefset->greeting();
		$scripts = $prefset->set_script();
	}
	EX_pageStart($scripts); //standard HTML page start stuff - insert scripts here
?>
<script language="JavaScript">
function compare_pswds() {
	if (document.getElementById("txtPswd_ID").value != document.getElementById("txtRePswd_ID").value) {
		alert ("Passwords do not match!");
		return false;
	}
	return true;
}

function RemoveBtn() {
	return(confirm("Removing this person will delete all their logs.  Continue?"));
}

function DeleteBtn() {
	return(confirm("Are you sure you want to delete this record?"));
}
</script>
<?php
	EX_pageHead(); //standard page headings - after any scripts

	switch ($_STATE->status) {
	case LIST_PERSONS:

		global $persons;
		echo $persons->set_list();

		break; //end LIST_PERSONS status ----END STATUS PROCESSING----

	case SELECTED_PERSON:
	case ADD_PERSON:		//comes back here if error...
	case UPDATE_PERSON:
	case DELETE_PERSON:
?>
<form method="post" name="frmAction" id="frmAction_ID" action="<?php echo $_SESSION["IAm"]; ?>">
  <table align="center">
    <tr>
      <td class="label"><?php echo $_STATE->fields['First Name']->HTML_label("First Name: "); ?></td>
      <td><?php echo $_STATE->fields['First Name']->HTML_input(20) ?></td>
    </tr>
    <tr>
      <td class="label"><?php echo $_STATE->fields['Last Name']->HTML_label("Last Name: "); ?></td>
      <td><?php echo $_STATE->fields['Last Name']->HTML_input(20) ?></td>
    </tr>
    <tr>
      <td class="label"><?php echo $_STATE->fields['Log ID']->HTML_label("Login ID: "); ?></td>
      <td><?php echo $_STATE->fields['Log ID']->HTML_input(20) ?></td>
    </tr>
    <tr>
      <td class="label"><?php echo $_STATE->fields['Password']->HTML_label("Password: "); ?></td>
      <td><?php echo $_STATE->fields['Password']->HTML_input(20,"type='password'") ?></td>
    </tr>
    <tr>
      <td class="label"><?php echo $_STATE->fields['RePassword']->HTML_label("Re-enter Password: "); ?></td>
      <td><?php echo $_STATE->fields['RePassword']->HTML_input(20,"password","onchange=\"compare_pswds();\"") ?></td>
    </tr>
    <tr>
      <td class="label"><?php echo $_STATE->fields['Email']->HTML_label("E-Mail: "); ?></td>
      <td><?php echo $_STATE->fields['Email']->HTML_input(20) ?></td>
    </tr>
    <tr>
      <td class="label"><?php echo $_STATE->fields['Inactive As Of']->HTML_label("Inactive As Of: "); ?></td>
      <td><?php foreach ($_STATE->fields['Inactive As Of']->HTML_input() as $line) echo $line."\n"; ?></td>
    </tr>
  </table>
  <p>
<?php
		if ($_STATE->record_id == -1 ) {
			echo FIELD_edit_buttons(FIELD_ADD);
		} else {
			echo Field_edit_buttons(FIELD_UPDATE);
			//note: can't remove or delete yourself
			global $_PERMITS;
			if (($_PERMITS->can_pass("person_edit")) && ($_SESSION["person_id"] != $_STATE->record_id)) {
				echo "  <br><button type='submit' name='btnRemove' id='btnRemove_ID' value = 'remove' onclick='return RemoveBtn()'>Remove this person record</button><br>\n";
				//for now, no delete; use inactive instead
//			echo "<button type='submit' name='btnDelete' id='btnDelete_ID' value = 'delete' onclick='return DeleteBtn()'>Delete this person record</button>\n";
			}
			echo "  <br><button type='submit' name='btnPrefs' id='btnPrefs_ID' value='preferences'>Preferences</button>\n";
		}
?>
</form>
<?php
		break; //end SELECTED_PERSON/ADD/UPDATE/DELETE_PERSON status ----END STATUS PROCESSING----

	case LIST_ALIENS:
?>
<form method="post" name="frmAction" id="frmAction_ID" action="<?php echo $_SESSION["IAm"]; ?>">
  <select name="selPerson[]" size="<?php echo count($_STATE->aliens) + 1; ?>" onclick='this.form.submit()'>
    <option value='-1' style='opacity:1.0'>--create another person--
<?php
		foreach ($_STATE->aliens as $key => $record) {
			echo "    <option value='".$key."'>".$record[0].", ".$record[1]."\n";
		}
?>
   </select>
</form>
<?php
		break; //end LIST_ALIENS status

	case PREFERENCES: //show preferences and allow update:
		$state = $prefset->get_page();
		EX_pageEnd($state);
		return;
		break;

	default:
		throw_the_bum_out(NULL,"Evicted(".$_STATE->ID."/".__LINE__."): invalid state=".$_STATE->status);

	} //end select ($_STATE->status)

	EX_pageEnd(); //standard end of page stuff

} //Page_out()
?>
