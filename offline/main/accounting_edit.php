<?php
//copyright 2015 C.D.Price. Licensed under Apache License, Version 2.0
//See license text at http://www.apache.org/licenses/LICENSE-2.0
if (!$_PERMITS->can_pass("accounting_edit")) throw_the_bum_out(NULL,"Evicted(".__LINE__."): no permit");

require_once "field_edit.php";

function state_fields() {
	global $_STATE;

	$_STATE->fields = array( //pagename,DBname,load from DB?,write to DB?,required?,maxlength
			"Name"=>new FIELD("txtName","name",TRUE,TRUE,TRUE,64),
			"Description"=>new AREA_FIELD("txtDesc","description",TRUE,TRUE,TRUE,256)
			);
}

function accounting_list() {
	global $_DB, $_STATE;

	$_STATE->records = array();
	$_STATE->records["-1"] = "--create a new accounting group--";

	$sql = "SELECT * FROM ".$_DB->prefix."a20_accounting
			WHERE organization_idref=".$_SESSION["organization_id"]." ORDER BY timestamp;";
	$stmt = $_DB->query($sql);
	while ($row = $stmt->fetchObject()) {
		$_STATE->records[strval($row->accounting_id)] = substr($row->name.": ".$row->description,0,25);
	}
	$stmt->closeCursor();
}

function accounting_select() {
	global $_STATE;

	accounting_list(); //restore the record list
	if (!array_key_exists(strval($_POST["selAccounting"]), $_STATE->records)) {
		throw_the_bum_out(NULL,"Evicted(".__LINE__."): invalid accounting id ".$_POST["selAccounting"]); //we're being spoofed
	}
	$_STATE->record_id = intval($_POST["selAccounting"]);
}

function accounting_info() {
	global $_DB, $_STATE;

	$sql = "SELECT * FROM ".$_DB->prefix."a20_accounting
			WHERE accounting_id=".$_STATE->record_id.";";
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
		if (($msg = $field->audit()) === true) continue;
		$errors .= "<br>".$name.": ".$msg;
	}
	if ($errors != "") {
		$_STATE->msgStatus = "Error:".$errors;
		return false;
	}

	foreach ($_STATE->fields as $name => $field) {
		$field->disabled = true;
	}

	return TRUE;
}

function update_db() {
	global $_DB, $_STATE;

	$sql = "UPDATE ".$_DB->prefix."a20_accounting
			SET name=:name, description=:description
			WHERE accounting_id=".$_STATE->record_id.";";
	$stmt = $_DB->prepare($sql);
	$stmt->bindValue(':name',$_STATE->fields["Name"]->value(),PDO::PARAM_STR);
	$stmt->bindValue(':description',$_STATE->fields["Description"]->value(),PDO::PARAM_STR);
	$stmt->execute();
}

function update_audit() {
	global $_STATE;

	if (!field_input_audit()) return FALSE;

	update_db();

	$_STATE->msgStatus = "The accounting record has been updated";
	return TRUE;
}

function new_audit() {
	global $_DB, $_STATE;

	if (!field_input_audit()) return FALSE;
	
	$hash = md5($_STATE->fields["Name"]->value().$_STATE->fields["Description"]->value());
	$sql = "INSERT INTO ".$_DB->prefix."a20_accounting (name, organization_idref)
			VALUES (:hash,".$_SESSION["organization_id"].");";
	$stmt = $_DB->prepare($sql);
	$stmt->bindValue(':hash',$hash,PDO::PARAM_STR);
	$stmt->execute();

	$sql = "SELECT accounting_id FROM ".$_DB->prefix."a20_accounting WHERE name=:hash;";
	$stmt = $_DB->prepare($sql);
	$stmt->bindValue(':hash',$hash,PDO::PARAM_STR);
	$stmt->execute();
	$_STATE->record_id = $stmt->fetchObject()->accounting_id;
	$stmt->closeCursor();

	update_db();

	$sql = "INSERT INTO ".$_DB->prefix."a21_account (accounting_idref,name,description)
			VALUES (".$_STATE->record_id.",'seed','initial seed account - please change');";
	$_DB->exec($sql);

	$_STATE->msgStatus = "The accounting record has been added";
	return TRUE;
}

//Main State Gate: (the while (1==1) allows a loop back through the switch using a 'break 1')
while (1==1) { switch ($_STATE->status) {
case STATE::INIT:
	accounting_list();
	$_STATE->status = STATE::SELECT;
	$_STATE->msgGreet = "Select the accounting group to edit";
	break 2;
case STATE::SELECT:
	accounting_select();
	$_STATE->status = STATE::SELECTED; //set for possible goback
//	break 1; //re_switch
case STATE::SELECTED:
	state_fields();
	if ($_STATE->record_id == -1) {
		$_STATE->msgGreet = "New accounting record";
		$_STATE->status = STATE::ADD;
	} else {
		accounting_info();
		$_STATE->msgGreet = "Edit accounting record?";
		$_STATE->status = STATE::UPDATE;
	}
	break 2;
case STATE::ADD:
	$_STATE->msgGreet = "New accounting record";
	if (isset($_POST["btnReset"])) {
		break 2;
	}
//	if ($_POST["btnSubmit"] != "add") { //IE < v8 submits name/InnerText NOT name/value
//		throw_the_bum_out(NULL,"Evicted(".__LINE__."): invalid btnSubmit ".$_POST["btnSubmit"]);
//	}
	state_fields();
	if (new_audit()) {
		$_STATE->status = STATE::DONE;
	}
	$_STATE->goback(1); //sets up goback to STATE::INIT
	break 2;
case STATE::UPDATE:
	$_STATE->msgGreet = "Edit accounting record";
	if (isset($_POST["btnReset"])) {
		record_info($_DB, $_STATE);
		break 2;
	}
//	if ($_POST["btnSubmit"] != "update") {
//		throw_the_bum_out(NULL,"Evicted(".__LINE__."): invalid btnSubmit ".$_POST["btnSubmit"]);
//	}
	state_fields();
	if (update_audit()) {
		$_STATE->status = STATE::DONE;
	}
	$_STATE->goback(1); //sets up goback to STATE::INIT
	break 2;
default:
	throw_the_bum_out(NULL,"Evicted(".__LINE__."): invalid state=".$_STATE->status);
} } //while & switch

EX_pageStart(); //standard HTML page start stuff - insert scripts here
EX_pageHead(); //standard page headings - after any scripts
?>

<form method="post" name="frmAction" id="frmAction_ID" action="<?php echo $_SERVER['SCRIPT_NAME']; ?>">
<?php
//forms and display depend on process state; note, however, that the state was probably changed after entering
//the Main State Gate so this switch will see the next state in the process:
switch ($_STATE->status) {
case STATE::SELECT:
?>
  <p>
  <select name='selAccounting' size="<?php echo count($_STATE->records); ?>" onclick="this.form.submit()">
<?php
	foreach($_STATE->records as $value => $name) {
		echo "    <option value=\"".$value."\">".$name."\n";
	} ?>
  </select>
  </p>
<?php //end STATE::SELECT status ----END STATUS PROCESSING----
	break;
default:
?>
  <table align="center">
    <tr>
      <td class="label"><?php echo $_STATE->fields['Name']->HTML_label("Name: "); ?></td>
      <td colspan="2"><?php echo $_STATE->fields['Name']->HTML_input(20) ?></td>
    </tr>
    <tr>
      <td class="label"><?php echo $_STATE->fields['Description']->HTML_label("Description: "); ?></td>
      <td colspan="2"><?php echo $_STATE->fields['Description']->HTML_input(32); ?></td>
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
}
EX_pageEnd(); //standard end of page stuff
?>

