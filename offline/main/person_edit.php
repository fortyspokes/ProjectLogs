<?php
//copyright 2015-2016,2018,2019 C.D.Price. Licensed under Apache License, Version 2.0
//See license text at http://www.apache.org/licenses/LICENSE-2.0

require_once "lib/field_edit.php";

//The Main State Gate cases:
define('LIST_PERSONS',		STATE::INIT);
define('SELECT_PERSON',			LIST_PERSONS + 1);
define('SELECTED_PERSON',		LIST_PERSONS + 2);
define('CHANGE_PERSON',			LIST_PERSONS + 3);
define('UPDATE_PERSON',			LIST_PERSONS + 4);
define('ADD_PERSON',			LIST_PERSONS + 5);
define('DELETE_PERSON',			LIST_PERSONS + 6);
define('PREFERENCES',		STATE::INIT + 20);

//Main State Gate: (the while (1==1) allows a loop back through the switch using a 'break 1')
while (1==1) { switch ($_STATE->status) {
case LIST_PERSONS:
	$_STATE->person_organization_id = 0;
	$_STATE->person_id = 0;
	require_once "lib/person_select.php";
	$persons = new PERSON_SELECT();
	if (!$_PERMITS->can_pass("person_edit")) {
		$persons->set_state($_SESSION["person_id"]);
		$_STATE->status = SELECTED_PERSON;
		break 1; //re-switch to SELECTED_PERSON
	}
	$persons->show_new = true;
	$_STATE->person_select = serialize(clone($persons));
	$_STATE->msgGreet = "Select a person record to edit";
	$_STATE->status = SELECT_PERSON;
	break 2;
case SELECT_PERSON:
	require_once "lib/person_select.php"; //catches $_GET list refresh
	$persons = unserialize($_STATE->person_select);
	$persons->set_state();
	$_STATE->person_select = serialize(clone($persons));
	$_STATE->status = SELECTED_PERSON; //for possible goback
	$_STATE->replace();
case SELECTED_PERSON:
	state_fields();
	$_STATE->record_id = $_STATE->person_id;
	if ($_STATE->record_id == -1) {
		$_STATE->msgGreet = "New person record";
		$_STATE->status = ADD_PERSON;
	} else {
		record_info();
		$_STATE->msgGreet = "Edit person record?";
		$_STATE->status = CHANGE_PERSON;
	}
	break 2;
case ADD_PERSON:
	state_fields();
	$_STATE->msgGreet = "New person record";
	if (isset($_POST["btnReset"])) {
		break  2;
	}
	if (new_audit()) {
		$record_id = $_STATE->record_id;
		$_STATE = $_STATE->loopback(SELECTED_PERSON);
		$_STATE->record_id = $record_id;
		break 1; //re-switch with new record_id
	}
	break 2;
case CHANGE_PERSON:
	if (isset($_POST["btnPrefs"])) {
		$_STATE->status = PREFERENCES;
		break 1; //re-switch to show preferences
	}
	//fall thru
case UPDATE_PERSON:
case DELETE_PERSON:
	state_fields();
	$_STATE->msgGreet = "Edit person record";
	if (isset($_POST["btnReset"])) {
		record_info();
		break 2;
	}
	if (isset($_POST["btnSubmit"])) {
		if (update_audit()) {
			$_STATE = $_STATE->loopback(SELECTED_PERSON);
			break 1; //re-switch
		}
		break 2;
	}
	if (isset($_POST["btnDelete"])) {
		if (delete_audit()) {
			$_STATE = $_STATE->loopback(LIST_PERSONS);
			break 1; //re-switch
		}
		break 2;
	}
	throw_the_bum_out(NULL,"Evicted(".__LINE__."): invalid Submit");
case PREFERENCES:
	require_once "lib/preference_set.php";
	if (!isset($_STATE->prefset)) { //first time thru
		$category = PREF_SET::COSMETIC;
		$_STATE->prefset = serialize(new PREF_SET($_STATE,"c10", $_STATE->record_id, $category, $_STATE->forwho));
	}
	$prefset = unserialize($_STATE->prefset);
	if (!$prefset->state_gate($_STATE)) {
		$_STATE = $_STATE->loopback(SELECTED_PERSON);
		break 1;
	}
	$_STATE->prefset = serialize(clone($prefset)); //leave $prefset intact for later services
	$_STATE->replace();
	break 2;
default:
	throw_the_bum_out(NULL,"Evicted(".__LINE__."): invalid state=".$_STATE->status);
} } //while & switch
//End Main State Gate

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
			WHERE person_id=".$_STATE->record_id.";";
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

	foreach ($_STATE->fields as $name => $field) {
		$field->disabled = true;
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
}

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
	}

	update_db();

	$_STATE->msgStatus = "The person record for \"".$_STATE->fields["First Name"]->value()." ".$_STATE->fields["Last Name"]->value()."\" has been updated";
	return TRUE;
}

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
}

function delete_audit() {
	return false; //for now, no delete; use inactive instead
	global $_DB, $_STATE;

	record_info(); //set state fields for display

	if ($_SESSION["person_id"] == $_STATE->record_id) {  //actually, won't get here because delete button
		$_STATE->msgStatus = "You can't delete yourself!";//won't show for yourself
		return FALSE;
	}

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
}

//-------end function code; begin HTML------------

$scripts = array("call_server.js");
if ($_STATE->status == PREFERENCES) {
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

function DeleteBtn() {
	return(confirm("Are you sure you want to delete this record?"));
}
</script>
<?php
EX_pageHead(); //standard page headings - after any scripts

//forms and display depend on process state; note, however, that the state was probably changed after entering
//the Main State Gate so this switch will see the next state in the process:
switch ($_STATE->status) {
case SELECT_PERSON:

	echo $persons->set_list();

	break; //end SELECT_PERSON status ----END STATUS PROCESSING----

case CHANGE_PERSON:
case UPDATE_PERSON:
case ADD_PERSON:
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
	if ($_STATE->status == ADD_PERSON ) {
		echo FIELD_edit_buttons(FIELD_ADD);
	} else {
		echo Field_edit_buttons(FIELD_UPDATE);
		echo "  <br><button type='submit' name='btnPrefs' id='btnPrefs_ID' value='preferences'>Preferences</button>\n";
	}
	if (($_STATE->status != ADD_PERSON) && ($_PERMITS->can_pass("person_edit"))
&&( 1 == 0 ) //for now, no delete; use inactive instead
				&& ($_SESSION["person_id"] != $_STATE->record_id)) { //note: can't delete yourself ?>
  <button type="submit" name="btnDelete" id="btnDelete_ID" value = "delete" onclick="return DeleteBtn()">Remove this person record</button>
</form>
<?php
	}
	break;
//end SELECTED/ADD/UPDATE/DELETE_PERSON status ----END STATUS PROCESSING----

case PREFERENCES: //show preferences and allow update:
	$prefset->set_HTML();

} //end select ($_STATE->status) ----END STATE: EXITING FROM PROCESS----

EX_pageEnd(); //standard end of page stuff
?>
