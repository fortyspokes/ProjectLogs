<?php
//copyright 2015-2016 C.D.Price. Licensed under Apache License, Version 2.0
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
define('GET_LOGO', 				LIST_ORGS + 7);

//Main State Gate: (the while (1==1) allows a loop back through the switch using a 'break 1')
while (1==1) { switch ($_STATE->status) {
case LIST_ORGS:
	list_setup();
	$_STATE->msgGreet = "Select an organization to edit";
	$_STATE->status = SELECT_ORG;
	break 2;
case SELECT_ORG:
	org_select();
	$_STATE->status = SELECTED_ORG; //for possible goback
	$_STATE->replace(); //so loopback() can find it
case SELECTED_ORG:
	state_fields();
	if ($_STATE->record_id == -1) {
		$_STATE->msgGreet = "New organization record";
		$_STATE->status = ADD_ORG;
	} else {
		org_info();
		$_STATE->msgGreet = "Edit organization record";
		$_STATE->status = CHANGE_ORG;
	}
	break 2;
case ADD_ORG:
	state_fields();
	$_STATE->msgGreet = "New organization record";
	if (isset($_POST["btnReset"])) {
		break 2;
	}
	if (new_audit()) {
		$record_id = $_STATE->record_id;
		$_STATE = $_STATE->loopback(SELECTED_ORG);
		$_STATE->record_id = $record_id;
		break 1; //re-switch with new record_id
	}
	break 2;
case CHANGE_ORG:
case UPDATE_ORG:
case DELETE_ORG:
case GET_LOGO:
	state_fields();
	$_STATE->msgGreet = "Edit organization record";
	if (isset($_POST["btnReset"])) {
		org_info();
		break 2;
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
	} elseif ($_POST["btnSubmit"] == "logo") {
		$_STATE->status = GET_LOGO;
		if (logo_audit()) {
			$_STATE = $_STATE->loopback(SELECTED_ORG);
			break 1; //re-switch
		}
	} else {
		throw_the_bum_out(NULL,"Evicted(".__LINE__."): invalid btnSubmit ".$_POST["btnSubmit"]);
	}
	break 2;
default:
	throw_the_bum_out(NULL,"Evicted(".__LINE__."): invalid state=".$_STATE->status);
} } //while & switch

function state_fields() {
	global $_STATE;

	$_STATE->fields = array( //pagename,DBname,load from DB?,write to DB?,required?,maxlength,disabled
			"Name"=>new FIELD("txtName","name",TRUE,TRUE,TRUE,64,TRUE),
			"Description"=>new AREA_FIELD("txtDesc","description",TRUE,TRUE,TRUE,256,TRUE),
			"Time Zone"=>new FIELD("txtTZO","timezone",TRUE,TRUE,TRUE,3,TRUE),
			);
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

	$sql = "SELECT name, description,timezone FROM ".$_DB->prefix."a00_organization
			WHERE organization_id=".$_STATE->record_id.";";
	$stmt = $_DB->query($sql);
	$row = $stmt->fetchObject();
	foreach($_STATE->fields as $field => &$props) { //preset record info on the page
		if ($props->load_from_DB) {
			$props->value($row->{$props->dbname});
		}
	}
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

	org_info(); //set state fields for display

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

	return TRUE;

}

function update_db() {
	global $_DB, $_STATE;

	$sql = "UPDATE ".$_DB->prefix."a00_organization
			SET name=:name, description=:description, timezone=:TZO
			WHERE organization_id=".$_STATE->record_id.";";
	$stmt = $_DB->prepare($sql);
	$stmt->bindValue(':name',$_STATE->fields["Name"]->value(),PDO::PARAM_STR);
	$stmt->bindValue(':description',$_STATE->fields["Description"]->value(),PDO::PARAM_STR);
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

EX_pageStart(); //standard HTML page start stuff - insert scripts here
?>
<script language="JavaScript">
LoaderS.push('init_buttons();');

function init_buttons() {
<?php
switch ($_STATE->status) {
case ADD_ORG:
	echo "  select_hold(true);\n";
	echo "  fields_hold(false);\n";
	break;
case UPDATE_ORG:
	echo "  UpdateBtn();\n";
	break;
case DELETE_ORG:
	echo "  DeleteBtn();\n";
	break;
case CHANGE_ORG:
	echo "  ResetBtn();\n";
	break;
case GET_LOGO:
	echo "  LogoBtn();\n";
	break;
case STATE::DONE:
	echo "  Done();\n";
} //end switch ?>
}

<?php
if ($_STATE->status != ADD_ORG) { ?>
function EnableReset() {
  button = document.getElementById("btnReset_ID");
  button.disabled = false;
  button.innerHTML = "Reset to original choices";
  button.style.visibility = "visible";
  button.value = "reset";
}

function UpdateBtn() {
  fields_hold(false);
  file_hold(true);
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
<?php
	if ($_SESSION["organization_id"] == 1) { ?>
  alert ("You can't delete the default organization!");
}
<?php
	} else { ?>
  fields_hold(true);
  file_hold(true);
  select_hold(true);
  button = document.getElementById("btnSubmit_ID");
  button.disabled = false;
  button.innerHTML = "Confirm Delete";
  button.style.visibility = "visible";
  button.value = "delete";
  document.getElementById("msgGreet_ID").innerHTML = "Do you really want to delete this organization record?";
  EnableReset();
}
<?php
	}
} //end ($_STATE->status != ADD_ORG) ?>

function LogoBtn() {
  fields_hold(true);
  file_hold(false);
  select_hold(true);
  document.getElementById("frmAction_ID").encoding="multipart/form-data";
  button = document.getElementById("btnSubmit_ID");
  button.disabled = false;
  button.innerHTML = "Upload New Logo";
  button.style.visibility = "visible";
  button.value = "logo";
  document.getElementById("msgGreet_ID").innerHTML = "Upload the new organization logo";
  EnableReset();
}

function ResetBtn() {
<?php
if ($_STATE->status == ADD_ORG) {
	echo "  return true;\n";
} else { ?>
  fields_hold(true);
  file_hold(true);
  select_hold(false);
  action_hold(true);
  document.getElementById("frmAction_ID").encoding="application/x-www-form-urlencoded";
  document.getElementById("msgGreet_ID").innerHTML = "What do you want to do to this organization record?";
  document.getElementById("msgStatus_ID").innerHTML = "";
  return true; //reset to default values
<?php
} ?>
}

function Done() {
  fields_hold(true);
  select_hold(true);
  action_hold(true);
}

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
  updater.disabled = cond;
  deleter.disabled = cond;
  logo.disabled = cond;
  if (cond) {
    updater.style.visibility = "hidden";
    deleter.style.visibility = "hidden";
    logo.style.visibility = "hidden";
  } else {
    updater.style.visibility = "visible";
    deleter.style.visibility = "visible";
    logo.style.visibility = "visible";
  }
}

function fields_hold(cond) {
<?php foreach($_STATE->fields as $field=>&$props) { ?>
  document.getElementById("<?php echo $props->pagename; ?>_ID").readOnly = cond;
<?php } ?>
}

function file_hold(cond) {
  document.getElementById("txtFile_ID").disabled = cond;
}
</script>
<?php
EX_pageHead(); //standard page headings - after any scripts
?>
<form method="post" name="frmAction" id="frmAction_ID" action="<?php echo $_SESSION["IAm"]; ?>">
<?php
//forms and display depend on process state; note, however, that the state was probably changed after entering
//the Main State Gate so this switch will see the next state in the process:
switch ($_STATE->status) {
case SELECT_ORG:
?>
  <select name='selOrg' size="<?php echo count($_STATE->records); ?>" onclick="this.form.submit()">
<?php
	foreach($_STATE->records as $value => $name) {
		echo "    <option value=\"".$value."\">".$name."\n";
	} ?>
  </select>
  </p>
<?php //end SELECT_ORG status ----END STATUS PROCESSING----
	break;
default:
?>
  <button type="button" name="btnUpdate" id="btnUpdate_ID" onclick="UpdateBtn();">Enter changes<br>to this org</button>
  <button type="button" name="btnLogo" id="btnLogo_ID" onclick="LogoBtn();">Change the logo</button>
  <button type="button" name="btnDelete" id="btnDelete_ID" onclick="DeleteBtn();">Remove this org</button>
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
      <td class="label"><?php echo $_STATE->fields['Time Zone']->HTML_label("Time Zone: "); ?></td>
      <td><?php echo $_STATE->fields['Time Zone']->HTML_input(3); ?></td>
    </tr>
  </table>
  <p>
  <input type="hidden" name="MAX_FILE_SIZE" value="1000000" >
  <input type="file" name="txtFile" id="txtFile_ID" accept="image/jpeg image/gif image/bmp">
  </p>
  <p>
<?php
	if ($_STATE->status == ADD_ORG ) {
		echo FIELD_edit_buttons(FIELD_ADD);
	} else {
		echo Field_edit_buttons(FIELD_UPDATE);
	}
//end default status ----END STATUS PROCESSING----
} ?>
</form>
<?php
EX_pageEnd(); //standard end of page stuff
?>
