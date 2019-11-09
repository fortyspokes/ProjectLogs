<?php
//copyright 2015-2016,2019 C.D.Price. Licensed under Apache License, Version 2.0
//See license text at http://www.apache.org/licenses/LICENSE-2.0
if (!$_PERMITS->can_pass("org_edit")) throw_the_bum_out(NULL,"Evicted(".__LINE__."): no permit");

require_once "lib/field_edit.php";

//The Main State Gate cases:
define('LIST_ORGS',			STATE::INIT);
define('SELECT_ORG',			LIST_ORGS + 1);
define('SELECTED_ORG',			LIST_ORGS + 2);
define('ADD_ORG',				LIST_ORGS + 3);
define('CHANGE_ORG',			LIST_ORGS + 4);//initiates all changes, incl. logo
define('UPDATE_ORG',			LIST_ORGS + 5);
define('DELETE_ORG',			LIST_ORGS + 6);
define('GET_LOGO', 			STATE::INIT + 10);
define('PREFERENCES',		STATE::INIT + 20);

//Main State Gate: (the while (1==1) allows a loop back through the switch using a 'break 1')
while (1==1) { switch ($_STATE->status) {
case LIST_ORGS:
	$_STATE->currency_id = 0;
	$_STATE->curr_list = array();
	$_STATE->noSleep[] = "curr_list";
	list_setup();
	$_STATE->msgGreet = "Select an organization to edit";
	Page_out();
	$_STATE->status = SELECT_ORG;
	break 2; //return to executive

case SELECT_ORG:
	org_select();
	$_STATE->status = SELECTED_ORG; //for possible goback
	$_STATE->replace(); //so loopback() can find it
case SELECTED_ORG:
	if ($_STATE->record_id == -1) {
		state_fields(false);
		$_STATE->msgGreet = "New organization record";
		$_STATE->status = ADD_ORG;
	} else {
		state_fields();
		org_info();
		$_STATE->msgGreet = "Edit organization record";
		$_STATE->status = CHANGE_ORG;
	}
	Page_out();
	break 2; //return to executive

case ADD_ORG:
	state_fields(false);
	$_STATE->msgGreet = "New organization record";
	if (isset($_POST["btnReset"])) {
		Page_out();
		break 2; //return to executive
	}
	if (new_audit()) {
		$record_id = $_STATE->record_id;
		$_STATE = $_STATE->loopback(SELECTED_ORG);
		$_STATE->record_id = $record_id;
		break 1; //re-switch with new record_id
	}
	Page_out(); //an error
	break 2; //return to executive

case CHANGE_ORG:
	if (isset($_POST["btnLogo"])) {
		$_STATE->status = GET_LOGO;
		$_STATE->msgGreet = "Upload the new organization logo";
		Page_out();
		break 2; //return to executive
	}
	if (isset($_POST["btnPrefs"])) {
		$_STATE->status = PREFERENCES;
		break 1; //re-switch to show preferences
	}
	//fall thru
case UPDATE_ORG:
case DELETE_ORG:
	state_fields();
	$_STATE->msgGreet = "Edit organization record";
	if (isset($_POST["btnReset"])) {
		org_info();
		Page_out();
		break 2; //return to executive
	}
	if ($_POST["btnSubmit"] == "update") {
		$_STATE->status = UPDATE_ORG;
		if (update_audit()) {
			$_STATE = $_STATE->loopback(SELECTED_ORG);
			break 1; //re-switch
		}
	} elseif ($_POST["btnSubmit"] == "delete") {
		$_STATE->status = DELETE_ORG;
		if (delete_audit()) {
			$_STATE = $_STATE->loopback(LIST_ORGS);
			break 1; //re-switch
		}
	} else {
		throw_the_bum_out(NULL,"Evicted(".__LINE__."): invalid btnSubmit ".$_POST["btnSubmit"]);
	}
	//will not get here

case GET_LOGO:
	$_STATE->backup = SELECTED_ORG; //set goback
	logo_audit();
	Page_out();
	break 2; //return to executive

case PREFERENCES:
	require_once "lib/preference_set.php";
	if (!isset($_STATE->prefset)) { //first time thru
		$category = ($_PERMITS->can_pass(PERMITS::_SUPERUSER)) ? PREF_SET::STRUCTURAL : PREF_SET::COSMETIC;
		$_STATE->prefset = serialize(new PREF_SET($_STATE,"a00", $_STATE->record_id, $category, $_STATE->forwho));
	}
	$prefset = unserialize($_STATE->prefset);
	if (!$prefset->state_gate($_STATE)) {
		$_STATE = $_STATE->loopback(SELECTED_ORG);
		break 1;
	}
	$_STATE->prefset = serialize(clone($prefset)); //leave $prefset intact for later services
	$_STATE->replace();
	Page_out();
	break 2; //return to executive

default:
	throw_the_bum_out(NULL,"Evicted(".__LINE__."): invalid state=".$_STATE->status);
} } //while & switch
//End Main State Gate & return to executive

function state_fields($disabled=true) {
	global $_DB, $_STATE;

	$_STATE->fields = array( //pagename,DBname,load from DB?,write to DB?,required?,maxlength,disabled
			"Name"=>new FIELD("txtName","name",TRUE,TRUE,TRUE,64,$disabled),
			"Description"=>new AREA_FIELD("txtDesc","description",TRUE,TRUE,TRUE,256,$disabled),
			"Time Zone"=>new FIELD("txtTZO","timezone",TRUE,TRUE,TRUE,3,$disabled),
			);
	$_STATE->curr_list = array();
	$sql = "SELECT * FROM ".$_DB->prefix."d02_currency;";
	$stmt = $_DB->query($sql);
	while ($row = $stmt->fetchObject()) {
		$_STATE->curr_list[strval($row->currency_id)] = $row->name." (".$row->symbol.")";
	}
	$stmt->closeCursor();
}

function list_setup() {
	global $_DB, $_STATE, $_PERMITS;

	$_STATE->records = array();
	if ($_PERMITS->can_pass(PERMITS::_SUPERUSER)) {
		$_STATE->records["-1"] = "--create a new organization record--";
	}

	$sql = "SELECT a00.organization_id, a00.name FROM ".$_DB->prefix."a00_organization AS a00";
	if (!$_PERMITS->can_pass(PERMITS::_SUPERUSER)) {
		$sql .= " INNER JOIN ".$_DB->prefix."c10_person_organization AS c10
				ON(a00.organization_id = c10.organization_idref)
				WHERE c10.person_idref=".$_SESSION["person_id"];
	}
	$sql .= " ORDER BY a00.timestamp";
	$stmt = $_DB->query($sql);
	while ($row = $stmt->fetchObject()) {
		$_STATE->records[strval($row->organization_id)] = $row->name;
	}
	$stmt->closeCursor();
}

function org_info() {
	global $_DB, $_STATE;

	$sql = "SELECT * FROM ".$_DB->prefix."a00_organization
			WHERE organization_id=".$_STATE->record_id.";";
	$stmt = $_DB->query($sql);
	$row = $stmt->fetchObject();
	foreach($_STATE->fields as $field => &$props) { //preset record info on the page
		if ($props->load_from_DB) {
			$props->value($row->{$props->dbname});
		}
	}
	$_STATE->currency_id = $row->currency_idref;
	$_STATE->forwho = $row->name.": ".$row->description; //PREFERENCES wants to see this
	$stmt->closeCursor();
}

function org_select() {
	global $_STATE;

	list_setup(); //restore the org list
	if (!array_key_exists(strval($_POST["selOrg"]), $_STATE->records)) {
		throw_the_bum_out(NULL,"Evicted(".__LINE__."): invalid org id ".$_POST["selOrg"]); //we're being spoofed
	}
	$_STATE->record_id = intval($_POST["selOrg"]);
}

function logo_audit() {
	global $_DB, $_STATE;

	if ($_FILES["txtFile"]["error"] != UPLOAD_ERR_OK) {
		if ($_FILES["txtFile"]["error"] == UPLOAD_ERR_NO_FILE) {
			$_STATE->msgStatus = "Use the Browser to select an image to upload";
		} else {
			$_STATE->msgStatus = "Upload error: error code=".$_FILES["txtFile"]["error"];
		}
		return FALSE;
	}

	$_STATE->msgStatus = "Invalid file: must be jpeg, jpg, gif, or bmp";
	$type = strtolower($_FILES["txtFile"]["type"]);
	if (substr($type,0,6) != "image/") {
		return FALSE;
	}
	$type = substr($type,6);
	$types = array("jpeg","jpg","gif","bmp");
	if (!in_array($type,$types)) {
		return FALSE;
	}
	$ext = explode(".", $_FILES["txtFile"]["name"]);
	$ext = end($ext);
	if (!in_array(strtolower($ext), $types)) {
		return FALSE;
	}
	$types = array(IMAGETYPE_GIF, IMAGETYPE_JPEG, IMAGETYPE_BMP);
	if (!in_array(exif_imagetype($_FILES["txtFile"]["tmp_name"]),$types)) {
		return FALSE;
	}

	$_STATE->msgStatus = "File upload error: internal error; not an uploaded file?";
	if (!is_uploaded_file($_FILES["txtFile"]["tmp_name"])) {
		return FALSE;
	}

	$sql = "SELECT logo FROM ".$_DB->prefix."a00_organization
			WHERE organization_id=".$_STATE->record_id.";";
	$stmt = $_DB->query($sql);
	$stmt->bindColumn('logo', $logo, db_connect::PARAM_LOB);
	$stmt->fetch(PDO::FETCH_BOUND);
	$stmt->closeCursor();
	if (!is_null($logo)) {
		$_DB->delete_BLOB($logo);
	}
	$oid = $_DB->file_to_BLOB($_FILES["txtFile"]["tmp_name"]);
	$sql = "UPDATE ".$_DB->prefix."a00_organization SET logo=:logo, logo_type=:logo_type
			WHERE organization_id=".$_STATE->record_id.";";
	$stmt = $_DB->prepare($sql);
	$stmt->bindValue(':logo',$oid,db_connect::PARAM_LOB);
	$stmt->bindValue(':logo_type',$type,PDO::PARAM_STR);
	$stmt->execute();

	$_STATE->msgStatus = "The logo has been updated";
	return true;
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
	if (!array_key_exists(strval($_POST["selCurrency"]), $_STATE->curr_list)) {
		throw_the_bum_out(NULL,"Evicted(".__LINE__."): invalid currency id ".$_POST["selCurrency"]); //we're being spoofed
	}
	$_STATE->currency_id = intval($_POST["selCurrency"]);

	return TRUE;

}

function update_db() {
	global $_DB, $_STATE;

	$sql = "UPDATE ".$_DB->prefix."a00_organization
			SET name=:name, description=:description, currency_idref=:currency, timezone=:TZO
			WHERE organization_id=".$_STATE->record_id.";";
	$stmt = $_DB->prepare($sql);
	$stmt->bindValue(':name',$_STATE->fields["Name"]->value(),PDO::PARAM_STR);
	$stmt->bindValue(':description',$_STATE->fields["Description"]->value(),PDO::PARAM_STR);
	$stmt->bindValue(':currency', $_STATE->currency_id, PDO::PARAM_INT);
	$stmt->bindValue(':TZO',$_STATE->fields["Time Zone"]->value(),PDO::PARAM_STR);
	$stmt->execute();
}

function update_audit() {
	global $_STATE;

	if (!field_input_audit()) {
		foreach($_STATE->fields as $name => $field) {
			$field->disabled = false;
		}
		return FALSE;
	}

	update_db();

	$_STATE->msgStatus = "The record for \"".$_STATE->fields["Name"]->value()."\" has been updated";
	return TRUE;
}

function new_audit() {
	global $_DB, $_STATE;

	if (!field_input_audit()) {
		foreach($_STATE->fields as $name => $field) {
			$field->disabled = false;
		}
		return FALSE;
	}

	$hash = md5($_STATE->fields["Name"]->value().$_STATE->fields["Description"]->value());
	$sql = "INSERT INTO ".$_DB->prefix."a00_organization (name) VALUES (:hash);";
	$stmt = $_DB->prepare($sql);
	$stmt->bindValue(':hash',$hash,PDO::PARAM_STR);
	$stmt->execute();

	$sql = "SELECT organization_id FROM ".$_DB->prefix."a00_organization WHERE name=:hash;";
	$stmt = $_DB->prepare($sql);
	$stmt->bindValue(':hash', $hash, PDO::PARAM_STR);
	$stmt->execute();
	$_STATE->record_id = $stmt->fetchObject()->organization_id;
	$stmt->closeCursor();

	update_db();

	$sql = "INSERT INTO ".$_DB->prefix."a10_project (organization_idref,name,description)
			VALUES (".$_STATE->record_id.",'".$hash."','initial seed project - please change');";
	$_DB->exec($sql);
	$sql = "SELECT project_id FROM ".$_DB->prefix."a10_project WHERE name='".$hash."';";
	$stmt = $_DB->query($sql);
	$ID = $stmt->fetchObject()->project_id;
	$stmt->closeCursor();
	$sql = "UPDATE ".$_DB->prefix."a10_project SET name='seed' WHERE project_id=".$ID.";";
	$_DB->exec($sql);

	$sql = "INSERT INTO ".$_DB->prefix."a12_task (project_idref,name,description)
			VALUES (".$ID.",'".$hash."','initial seed task - please change');";
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

	$_STATE->msgStatus = "The organization \"".$_STATE->fields["Name"]->value()."\" has been added";
	return TRUE;
}

function delete_audit() {
	global $_DB, $_STATE;

	org_info(); //set state fields for display

	if ($_STATE->record_id == 1) {
		$_STATE->msgStatus = "You can't delete the default organization!";
		return FALSE;
	}
	if ($_STATE->record_id == $_SESSON["organization_id"]) {
		$_STATE->msgStatus = "You can't delete your assigned organization!";
		return FALSE;
	}

	$sql = "SELECT COUNT(*) AS person_count FROM ".$_DB->prefix."c10_person_organization
		WHERE organization_idref=".$_STATE->record_id.";";
	$stmt = $_DB->query($sql);
	$count = $stmt->fetchObject()->person_count;
	$stmt->closeCursor();
	if ($count > 0) {
		$_STATE->msgStatus = "You can't delete this organization while it has associated person records";
		return FALSE;
	}

//delete projects, preferences, properties???

	$sql = "DELETE FROM ".$_DB->prefix."a00_organization WHERE organization_id=".$_STATE->record_id.";";
	$stmt = $_DB->prepare($sql);
	$result = $stmt->exec();
	if ($result == 0) {
		$_STATE->msgStatus = "System error: no organization deleted";
		return FALSE;
	}

	$_STATE->msgStatus = "The organization has \"".$_STATE->fields["Name"]->value()."\" been deleted";
	return TRUE;
}

function Page_out() {
	global $_DB, $_STATE;

	if ($_STATE->status == PREFERENCES) {
		global $prefset;
		$_STATE->msgGreet = $prefset->greeting();
		$scripts = $prefset->set_script();
	} else {
		$scripts = array();
	}
	EX_pageStart($scripts); //standard HTML page start stuff - insert scripts here
?>
<script language="JavaScript">
<?php
	switch ($_STATE->status) {
	case CHANGE_ORG:
	case UPDATE_ORG:
	case DELETE_ORG:
?>
function EnableReset() {
	button = document.getElementById("btnReset_ID");
	button.disabled = false;
	button.innerHTML = "Reset to original choices";
	button.style.visibility = "visible";
	button.value = "reset";
}

function UpdateBtn() {
	fields_hold(false);
	select_hold(true);
	button = document.getElementById("btnSubmit_ID");
	button.disabled = false;
	button.innerHTML = "Submit Changes";
	button.style.visibility = "visible";
	button.value = "update";
	document.getElementById("msgGreet_ID").innerHTML = "Make changes to this organization record";
	EnableReset();
}

function DeleteBtn() {
<?php	if ($_STATE->record_id == 1) { ?>
	alert ("You can't delete the default organization!");
}
<?php	} else { ?>
	fields_hold(true);
	select_hold(true);
	button = document.getElementById("btnSubmit_ID");
	button.disabled = false;
	button.innerHTML = "Confirm Delete";
	button.style.visibility = "visible";
	button.value = "delete";
	document.getElementById("msgGreet_ID").innerHTML = "Do you really want to delete this organization record?";
	EnableReset();
}
<?php	}
		// end case CHANGE_ORG, UPDATE_ORG, DELETE_ORG - fall thru

	case ADD_ORG:
?>

function ResetBtn() {
<?php	if ($_STATE->status == ADD_ORG) { ?>
	return true;
}
<?php	} else { ?>
	fields_hold(true);
	select_hold(false);
	action_hold(true);
//	document.getElementById("frmAction_ID").encoding="application/x-www-form-urlencoded";
	document.getElementById("msgGreet_ID").innerHTML = "What do you want to do to this organization record?";
	document.getElementById("msgStatus_ID").innerHTML = "";
	return true; //reset to default values
}
<?php	} ?>

function action_hold(cond) {
	submiter = document.getElementById("btnSubmit_ID");
	reseter = document.getElementById("btnReset_ID");
	submiter.disabled = cond;
	reseter.disabled = cond;
	if (cond) {
		submiter.style.visibility = "hidden";
		reseter.style.visibility = "hidden";
	} else {
		submiter.style.visibility = "visible";
		reseter.style.visibility = "visible";
	}
}

function select_hold(cond) {
	updater = document.getElementById("btnUpdate_ID");
	deleter = document.getElementById("btnDelete_ID");
	logo = document.getElementById("btnLogo_ID");
	prefs = document.getElementById("btnPrefs_ID");
	updater.disabled = cond;
	deleter.disabled = cond;
	logo.disabled = cond;
	if (cond) {
		updater.style.visibility = "hidden";
		deleter.style.visibility = "hidden";
		logo.style.visibility = "hidden";
		prefs.style.visibility = "hidden";
	} else {
		updater.style.visibility = "visible";
		deleter.style.visibility = "visible";
		logo.style.visibility = "visible";
		prefs.style.visibility = "visible";
	}
}

function fields_hold(cond) {
<?php	foreach($_STATE->fields as $field=>&$props) { ?>
	document.getElementById("<?php echo $props->pagename; ?>_ID").readOnly = cond;
<?php	} ?>
}
<?php
	} //end switch ($_STATE->status)
?>

</script>
<?php
	EX_pageHead(); //standard page headings - after any scripts

	switch ($_STATE->status) {
	case LIST_ORGS:
?>
  <p>
<form method="post" name="frmAction" id="frmAction_ID" action="<?php echo $_SESSION["IAm"]; ?>">
  <select name='selOrg' size="<?php echo count($_STATE->records); ?>" onclick="this.form.submit()">
<?php
		foreach($_STATE->records as $value => $name) {
			echo "    <option value=\"".$value."\">".$name."\n";
		}
?>
  </select>
</form>
  </p>
<?php
		break; //end LIST_ORGS status ----END STATUS PROCESSING----

	case CHANGE_ORG:
?>
  <p>
<form method="post" name="frmAction" id="frmAction_ID" action="<?php echo $_SESSION["IAm"]; ?>">
  <button type="button" name="btnUpdate" id="btnUpdate_ID" onclick="UpdateBtn();">Enter changes<br>to this org</button>
  <button type="submit" name="btnLogo" id="btnLogo_ID" value="logo">Change the logo</button>
  <button type="submit" name="btnPrefs" id="btnPrefs_ID" value="preferences">Preferences</button>
  <button type="button" name="btnDelete" id="btnDelete_ID" onclick="DeleteBtn();">Remove this org</button>
</form>
  </p>
<?php
		//no break - falls thru; end CHANGE_ORG status ----END STATUS PROCESSING----

	case ADD_ORG:
	case UPDATE_ORG:
	case DELETE_ORG:
?>
  <p>
<form method="post" name="frmAction" id="frmAction_ID" action="<?php echo $_SESSION["IAm"]; ?>">
  <p>
  <table align="center">
    <tr>
      <td class="label"><?php echo $_STATE->fields['Name']->HTML_label("Name: "); ?></td>
      <td><?php echo $_STATE->fields['Name']->HTML_input(20) ?></td>
    </tr>
    <tr>
      <td class="label"><?php echo $_STATE->fields['Description']->HTML_label("Description: "); ?></td>
      <td><?php echo $_STATE->fields['Description']->HTML_input(32); ?></td>
    </tr>
    <tr>
      <td class="label"><label for="selCurrency_ID" class='required'>*Currency:</label></td>
      <td>
        <select name='selCurrency' id='selCurrency_ID' size=1>
<?php
		foreach($_STATE->curr_list as $value => $name) {
	  		echo "        <option value=\"".$value."\"";
			if ($_STATE->currency_id == $value) echo " selected";
			echo ">".$name."\n";
		}
?>
        </select>
      </td>
      <td>&nbsp</td>
    </tr>
    <tr>
      <td class="label"><?php echo $_STATE->fields['Time Zone']->HTML_label("Time Zone: "); ?></td>
      <td><?php echo $_STATE->fields['Time Zone']->HTML_input(3); ?></td>
    </tr>
  </table>
  </p>
  <p>
<?php
		if ($_STATE->status == ADD_ORG ) {
			echo FIELD_edit_buttons(FIELD_ADD);
		} else {
			echo Field_edit_buttons(FIELD_UPDATE);
		}
?>
</form>
  </p>
<?php

		break; //end ADD/UPDATE/DELETE_ORG status ----END STATUS PROCESSING----

	case GET_LOGO:
?>
  <p>
<form method="post" name="frmAction" id="frmAction_ID" action="<?php echo $_SESSION["IAm"]; ?>">
<script language="JavaScript">
  document.getElementById("frmAction_ID").encoding="multipart/form-data";
</script>
  <p>
  <table align="center">
    <tr><td>
	  <input type="hidden" name="MAX_FILE_SIZE" value="1000000" >
	  <input type="file" name="txtFile" id="txtFile_ID" accept="image/jpeg image/gif image/bmp">
	  <button type="submit" name="btnLogo" id="btnLogo_ID" value="logo">Upload the logo</button>
	</td></td>
	<tr><td>
	  <img src="<?php echo $_SESSION["BUTLER"]; ?>?IAm=LG&ID=<?php echo $_STATE->record_id; ?>" height="110" width="110">
	</td></tr>
  </table>
  </p>
</form>
  </p>
<?php
		break; //end GET_LOGO status ----END STATUS PROCESSING----

	case PREFERENCES: //show preferences and allow update:
		$prefset->set_HTML();
		break;

	default:
		throw_the_bum_out(NULL,"Evicted(".__LINE__."): invalid state=".$_STATE->status);

	} //end select ($_STATE->status) ----END STATE: EXITING FROM PROCESS----

	EX_pageEnd(); //standard end of page stuff

} //end Page_out()
?>
