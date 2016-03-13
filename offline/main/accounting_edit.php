<?php
//copyright 2015,2016 C.D.Price. Licensed under Apache License, Version 2.0
//See license text at http://www.apache.org/licenses/LICENSE-2.0
if (!$_PERMITS->can_pass("accounting_edit")) throw_the_bum_out(NULL,"Evicted(".__LINE__."): no permit");

require_once "field_edit.php";

//The Main State Gate cases:
define('LIST_ACCOUNTING',	STATE::INIT);
define('SELECT_ACCOUNTING',		LIST_ACCOUNTING + 1);
define('SELECTED_ACCOUNTING',	LIST_ACCOUNTING + 2);
define('ADD_ACCOUNTING',		LIST_ACCOUNTING + 4);
define('UPDATE_ACCOUNTING',		LIST_ACCOUNTING + 6);

//Main State Gate: (the while (1==1) allows a loop back through the switch using a 'break 1')
while (1==1) { switch ($_STATE->status) {
case LIST_ACCOUNTING:
	accounting_list();
	$_STATE->status = SELECT_ACCOUNTING;
	$_STATE->msgGreet = "Select the accounting group to edit";
	break 2;
case SELECT_ACCOUNTING:
	accounting_select();
	$_STATE->status = SELECTED_ACCOUNTING; //set for possible goback
	$_STATE->replace(); //so loopback() can find it
case SELECTED_ACCOUNTING:
	state_fields();
	if ($_STATE->record_id == -1) {
		$_STATE->msgGreet = "New accounting record";
		$_STATE->status = ADD_ACCOUNTING;
	} else {
		accounting_info();
		$_STATE->msgGreet = "Edit accounting record?";
		$_STATE->status = UPDATE_ACCOUNTING;
	}
	break 2;
case ADD_ACCOUNTING:
	$_STATE->msgGreet = "New accounting record";
	if (isset($_POST["btnReset"])) {
		break 2;
	}
	state_fields();
	if (new_audit()) {
		$record_id = $_STATE->record_id;
		$_STATE = $_STATE->loopback(SELECTED_ACCOUNTING);
		$_STATE->record_id = $record_id;
		break 1; //re-switch with new record_id
	}
	break 2;
case UPDATE_ACCOUNTING:
	$_STATE->msgGreet = "Edit accounting record";
	if (isset($_POST["btnReset"])) {
		record_info($_DB, $_STATE);
		break 2;
	}
	state_fields();
	if (update_audit()) {
		$_STATE = $_STATE->loopback(SELECTED_ACCOUNTING);
		break 1; //re-switch
	}
	break 2;
default:
	throw_the_bum_out(NULL,"Evicted(".__LINE__."): invalid state=".$_STATE->status);
} } //while & switch

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

EX_pageStart(); //standard HTML page start stuff - insert scripts here
EX_pageHead(); //standard page headings - after any scripts
?>

<form method="post" name="frmAction" id="frmAction_ID" action="<?php echo $_SESSION["IAm"]; ?>">
<?php
//forms and display depend on process state; note, however, that the state was probably changed after entering
//the Main State Gate so this switch will see the next state in the process:
switch ($_STATE->status) {
case SELECT_ACCOUNTING:
?>
  <p>
  <select name='selAccounting' size="<?php echo count($_STATE->records); ?>" onclick="this.form.submit()">
<?php
	foreach($_STATE->records as $value => $name) {
		echo "    <option value=\"".$value."\">".$name."\n";
	} ?>
  </select>
  </p>
<?php //end SELECT_ACCOUNTING status ----END STATUS PROCESSING----
	break;

case SELECTED_ACCOUNTING:
case ADD_ACCOUNTING:
case UPDATE_ACCOUNTING:
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
	if ($_STATE->status == ADD_ACCOUNTING ) {
		echo FIELD_edit_buttons(FIELD_ADD);
	} else {
		echo Field_edit_buttons(FIELD_UPDATE);
	} ?>
</form>
<?php //end default status ----END STATUS PROCESSING----
}
EX_pageEnd(); //standard end of page stuff
?>
