<?php
//copyright 2016 C.D.Price. Licensed under Apache License, Version 2.0
//See license text at http://www.apache.org/licenses/LICENSE-2.0
if ($_UPLOAD) {
	if (!$_PERMITS->can_pass("repository_put")) throw_the_bum_out(NULL,"Evicted(".__LINE__."): no permit");
} else {
	if (!$_PERMITS->can_pass("repository_get")) throw_the_bum_out(NULL,"Evicted(".__LINE__."): no permit");
}

require_once "lib/field_edit.php";

//The Main State Gate cases:
define('LIST_DEPOSITS',		STATE::INIT);
define('SELECT_DEPOSIT',		LIST_DEPOSITS + 1);
define('SELECTED_DEPOSIT',		LIST_DEPOSITS + 2);
define('ADD_DEPOSIT',			LIST_DEPOSITS + 3);
define('CHANGE_DEPOSIT',		LIST_DEPOSITS + 4);//initiates all changes, incl. reload
define('UPDATE_DEPOSIT',		LIST_DEPOSITS + 5);
define('DELETE_DEPOSIT',		LIST_DEPOSITS + 6);
define('GET_DEPOSIT', 		STATE::INIT + 10);

//Main State Gate: (the while (1==1) allows a loop back through the switch using a 'break 1')
while (1==1) { switch ($_STATE->status) {
case LIST_DEPOSITS:
	list_setup();
	$_STATE->msgGreet = "Select a deposit";
	if ($_UPLOAD) $_STATE->msgGreet .= " to change";
	$_STATE->status = SELECT_DEPOSIT;
	break 2;
case SELECT_DEPOSIT:
	deposit_select();
	if ($_UPLOAD) {
		$_STATE->status = SELECTED_DEPOSIT; //for possible goback
		$_STATE->replace(); //so loopback() can find it
	} else {
		//we don't get back here and don't return to the executive because the file_put in
		//deposit_download exits; that's why the status can stay unchanged waiting for the
		//user to select another.
		deposit_download($_STATE->record_id);
	}
case SELECTED_DEPOSIT:
	if ($_STATE->record_id == -1) {
		state_fields(false); //false=not disabled
		$_STATE->msgGreet = "Create a new deposit";
		$_STATE->status = ADD_DEPOSIT;
	} else {
		state_fields(false);
		deposit_info();
		$_STATE->msgGreet = "Change this deposit";
		$_STATE->status = CHANGE_DEPOSIT;
	}
	break 2;
case ADD_DEPOSIT:
	state_fields(false);
	$_STATE->msgGreet = "Create a new deposit";
	if (isset($_POST["btnReset"])) {
		break 2;
	}
	if (new_audit()) {
		$record_id = $_STATE->record_id;
		$_STATE = $_STATE->loopback(SELECTED_DEPOSIT);
		$_STATE->record_id = $record_id;
		break 1; //re-switch with new record_id
	}
	break 2;
case CHANGE_DEPOSIT:
	if (isset($_POST["btnUpload"])) {
		$_STATE->status = GET_DEPOSIT;
		$_STATE->msgGreet = "Upload a new deposit";
		break 2;
	}
	//fall thru
case UPDATE_DEPOSIT:
case DELETE_DEPOSIT:
	state_fields();
	$_STATE->msgGreet = "Edit organization record";
	if (isset($_POST["btnReset"])) {
		deposit_info();
		break 2;
	}
	if (isset($_POST["btnSubmit"])) {
		$_STATE->status = UPDATE_DEPOSIT;
		if (update_audit()) {
			$_STATE = $_STATE->loopback(SELECTED_DEPOSIT);
			break 1; //re-switch
		}
	} elseif (isset($_POST["btnDelete"])) {
		$_STATE->status = DELETE_DEPOSIT;
		if (delete_audit()) {
			$_STATE = $_STATE->loopback(LIST_DEPOSITS);
			break 1; //re-switch
		}
	} else {
		throw_the_bum_out(NULL,"Evicted(".__LINE__."): invalid btnSubmit ".$_POST["btnSubmit"]);
	}
	break 2;
case GET_DEPOSIT:
	$_STATE->backup = SELECTED_DEPOSIT; //set goback
	deposit_audit();
	break 2;
default:
	throw_the_bum_out(NULL,"Evicted(".__LINE__."): invalid state=".$_STATE->status);
} } //while & switch

function state_fields($disabled=true) {
	global $_STATE;

	$_STATE->fields = array( //pagename,DBname,load from DB?,write to DB?,required?,maxlength,disabled
			"Name"=>new FIELD("txtName","filename",TRUE,TRUE,TRUE,64,$disabled),
			"Description"=>new AREA_FIELD("txtDesc","description",TRUE,TRUE,TRUE,64,$disabled),
			);
}

function list_setup() {
	global $_DB, $_STATE, $_UPLOAD;

	$_STATE->records = array();
	if ($_UPLOAD) {
		$_STATE->records["-1"] = "--create a new deposit--";
	}

	$sql = "SELECT repository_id, description FROM ".$_DB->prefix."d20_repository
			WHERE organization_idref=".$_SESSION["organization_id"]."
			ORDER BY timestamp";
	$stmt = $_DB->query($sql);
	while ($row = $stmt->fetchObject()) {
		$_STATE->records[strval($row->repository_id)] = $row->description;
	}
	$stmt->closeCursor();
}

function deposit_info() {
	global $_DB, $_STATE;

	$sql = "SELECT filename, description FROM ".$_DB->prefix."d20_repository
			WHERE repository_id=".$_STATE->record_id.";";
	$stmt = $_DB->query($sql);
	$row = $stmt->fetchObject();
	foreach($_STATE->fields as $field => &$props) { //preset record info on the page
		if ($props->load_from_DB) {
			$props->value($row->{$props->dbname});
		}
	}
	$stmt->closeCursor();
}

function deposit_select() {
	global $_STATE;

	list_setup(); //restore the list
	if (!array_key_exists(strval($_POST["selDeposit"]), $_STATE->records)) {
		throw_the_bum_out(NULL,"Evicted(".__LINE__."): invalid org id ".$_POST["selDeposit"]); //we're being spoofed
	}
	$_STATE->record_id = intval($_POST["selDeposit"]);
}

function deposit_download($id) {
	global $_DB;

	$sql = "SELECT * FROM ".$_DB->prefix."d20_repository
			WHERE repository_id=".$id.";";
	$row = $_DB->query($sql)->fetchObject();


	require_once "lib/file_put.php";
	$out = FP_open($row->filename);
	FP_putBLOB($out, $row->deposit);
	FP_close($out); //does not return
}

function deposit_audit() {
	global $_DB, $_STATE;

	if ($_FILES["txtFile"]["error"] != UPLOAD_ERR_OK) {
		if ($_FILES["txtFile"]["error"] == UPLOAD_ERR_NO_FILE) {
			$_STATE->msgStatus = "Use the Browser to select a file to upload";
		} else {
			$_STATE->msgStatus = "Upload error: error code=".$_FILES["txtFile"]["error"];
		}
		return FALSE;
	}

	$_STATE->msgStatus = "File upload error: internal error; not an uploaded file?";
	if (!is_uploaded_file($_FILES["txtFile"]["tmp_name"])) {
		return FALSE;
	}

	$sql = "SELECT deposit FROM ".$_DB->prefix."d20_repository
			WHERE repository_id=".$_STATE->record_id.";";
	$stmt = $_DB->query($sql);
	$stmt->bindColumn('deposit', $deposit, db_connect::PARAM_LOB);
	$stmt->fetch(PDO::FETCH_BOUND);
	$stmt->closeCursor();
	if (!is_null($deposit)) {
		$_DB->delete_BLOB($deposit);
	}
	$oid = $_DB->file_to_BLOB($_FILES["txtFile"]["tmp_name"]);
	$sql = "UPDATE ".$_DB->prefix."d20_repository SET deposit=:deposit
			WHERE repository_id=".$_STATE->record_id.";";
	$stmt = $_DB->prepare($sql);
	$stmt->bindValue(':deposit',$oid,db_connect::PARAM_LOB);
	$stmt->execute();

	$_STATE->msgStatus = "The deposit has been re-loaded";
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

	$sql = "UPDATE ".$_DB->prefix."d20_repository
			SET filename=:name, description=:description
			WHERE repository_id=".$_STATE->record_id.";";
	$stmt = $_DB->prepare($sql);
	$stmt->bindValue(':name',$_STATE->fields["Name"]->value(),PDO::PARAM_STR);
	$stmt->bindValue(':description',$_STATE->fields["Description"]->value(),PDO::PARAM_STR);
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

	$_STATE->msgStatus = "The deposit for \"".$_STATE->fields["Name"]->value()."\" has been updated";
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
	$sql = "INSERT INTO ".$_DB->prefix."d20_repository (organization_idref,filename)
			VALUES (".$_SESSION["organization_id"].",:hash);";
	$stmt = $_DB->prepare($sql);
	$stmt->bindValue(':hash',$hash,PDO::PARAM_STR);
	$stmt->execute();

	$sql = "SELECT repository_id FROM ".$_DB->prefix."d20_repository WHERE filename=:hash;";
	$stmt = $_DB->prepare($sql);
	$stmt->bindValue(':hash', $hash, PDO::PARAM_STR);
	$stmt->execute();
	$_STATE->record_id = $stmt->fetchObject()->repository_id;
	$stmt->closeCursor();

	update_db();

	$_STATE->msgStatus = "The deposit \"".$_STATE->fields["Name"]->value()."\" has been added";
	return TRUE;
}

function delete_audit() {
	global $_DB, $_STATE;

	deposit_info(); //set state fields for display

	$sql = "DELETE FROM ".$_DB->prefix."d20_repository WHERE repository_id=".$_STATE->record_id.";";
	$_DB->exec($sql);

	$_STATE->msgStatus = "The deposit has \"".$_STATE->fields["Name"]->value()."\" been deleted";
	return TRUE;
}

EX_pageStart(); //standard HTML page start stuff - insert scripts here
EX_pageHead(); //standard page headings - after any scripts

//forms and display depend on process state; note, however, that the state was probably changed after entering
//the Main State Gate so this switch will see the next state in the process:
switch ($_STATE->status) {
case SELECT_DEPOSIT:
?>
  <p>
<form method="post" name="frmAction" id="frmAction_ID" action="<?php echo $_SESSION["IAm"]; ?>">
  <select name='selDeposit' size="<?php echo count($_STATE->records); ?>" onclick="this.form.submit()">
<?php
	foreach($_STATE->records as $value => $name) {
		echo "    <option value=\"".$value."\">".$name."\n";
	} ?>
  </select>
</form>
  </p>
<?php //end SELECT_DEPOSIT status ----END STATUS PROCESSING----
	break;
case GET_DEPOSIT:
?>
  <p>
<form method="post" name="frmAction" id="frmAction_ID" action="<?php echo $_SESSION["IAm"]; ?>">
<script language="JavaScript">
  document.getElementById("frmAction_ID").encoding="multipart/form-data";
</script>
  <p>
  <table align="center">
    <tr><td>
	  <input type="hidden" name="MAX_FILE_SIZE" value="2000000" >
	  <input type="file" name="txtFile" id="txtFile_ID">
	  <button type="submit" name="btnUpload" id="btnUpload_ID" value="upload">Upload the file</button>
	</td></td>
  </table>
  </p>
</form>
  </p>
<?php
//end GET_DEPOSIT status ----END STATUS PROCESSING----
	break;
case CHANGE_DEPOSIT:
?>
  <p>
<form method="post" name="frmAction" id="frmAction_ID" action="<?php echo $_SESSION["IAm"]; ?>">
  <button type="submit" name="btnUpload" id="btnUpload_ID" value="upload">Upload a new file for deposit</button>
  <button type="submit" name="btnDelete" id="btnDelete_ID" value="delete">Remove this deposit</button>
</form>
  </p>
<?php //end CHANGE_DEPOSIT status ----END STATUS PROCESSING----
	//no break - falls thru
case SELECTED_DEPOSIT:
case ADD_DEPOSIT:
case UPDATE_DEPOSIT:
case DELETE_DEPOSIT:
?>
  <p>
<form method="post" name="frmAction" id="frmAction_ID" action="<?php echo $_SESSION["IAm"]; ?>">
  <p>
  <table align="center">
    <tr>
      <td class="label"><?php echo $_STATE->fields['Name']->HTML_label("Filename: "); ?></td>
      <td><?php echo $_STATE->fields['Name']->HTML_input(20) ?></td>
    </tr>
    <tr>
      <td class="label"><?php echo $_STATE->fields['Description']->HTML_label("Description: "); ?></td>
      <td><?php echo $_STATE->fields['Description']->HTML_input(32); ?></td>
    </tr>
  </table>
  </p>
  <p>
<?php
	if ($_STATE->status == ADD_DEPOSIT ) {
		echo FIELD_edit_buttons(FIELD_ADD);
	} else {
		echo Field_edit_buttons(FIELD_UPDATE);
	} ?>
</form>
  </p>
<?php
//end SELECTED/ADD/UPDATE/DELETE_DEPOSIT status ----END STATUS PROCESSING----
	break;

} //end select ($_STATE->status) ----END STATE: EXITING FROM PROCESS----

EX_pageEnd(); //standard end of page stuff
?>