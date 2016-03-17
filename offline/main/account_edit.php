<?php
//copyright 2015-2016 C.D.Price. Licensed under Apache License, Version 2.0
//See license text at http://www.apache.org/licenses/LICENSE-2.0
if (!$_PERMITS->can_pass("account_edit")) throw_the_bum_out(NULL,"Evicted(".__LINE__."): no permit");

require_once "lib/field_edit.php";

//The Main State Gate cases:
define('LIST_ACCOUNTING',	STATE::INIT);
define('SELECT_ACCOUNTING',		LIST_ACCOUNTING + 1);
define('SELECTED_ACCOUNTING',	LIST_ACCOUNTING + 2);
define('LIST_ACCOUNTS',		STATE::INIT + 10);
define('SELECT_ACCOUNT',		LIST_ACCOUNTS + 1);
define('SELECTED_ACCOUNT',		LIST_ACCOUNTS + 2);
define('ADD_ACCOUNT',			LIST_ACCOUNTS + 4);
define('UPDATE_ACCOUNT',		LIST_ACCOUNTS + 6);
define('PROPERTIES',		STATE::INIT + 20);
define('PROPERTIES_GOBACK',			PROPERTIES + 1);

//Main State Gate: (the while (1==1) allows a loop back through the switch using a 'break 1')
while (1==1) { switch ($_STATE->status) {
case LIST_ACCOUNTING:
	$_STATE->accounting_id = 0;
	accounting_list();
	if (count($_STATE->records) == 1) { //solo group?
		$record = each($_STATE->records);
		accounting_select($record[0]); //select this one
		$_STATE->init = LIST_ACCOUNTS; //no 'goback' at LIST_ACCOUNTS
		$_STATE->status = SELECTED_ACCOUNTING;
		break 1; //re-switch to SELECTED_ACCOUNTING
	}
	$_STATE->msgGreet = "Select the accounting group";
	$_STATE->status = SELECT_ACCOUNTING;
	break 2;
case SELECT_ACCOUNTING:
	accounting_select(); //select from POST
	$_STATE->heading .= "<br>Accounting group: ".$_STATE->records[$_STATE->accounting_id];
case SELECTED_ACCOUNTING:

	$_STATE->status = LIST_ACCOUNTS; //our new starting point for goback
	$_STATE->replace(); //so loopback() can find it
case LIST_ACCOUNTS:
	account_list();
	$_STATE->msgGreet = "Select the ".$_STATE->accounting." record to edit";
	$_STATE->backup = LIST_ACCOUNTING; //set goback
	$_STATE->status = SELECT_ACCOUNT;
	break 2;
case SELECT_ACCOUNT:
	account_select();
	$_STATE->status = SELECTED_ACCOUNT; //for possible goback
	$_STATE->replace(); //so loopback() can find it
case SELECTED_ACCOUNT:
	state_fields();
	$_STATE->backup = LIST_ACCOUNTS; //for goback
	if ($_STATE->record_id == -1) {
		$_STATE->msgGreet = "New ".$_STATE->accounting." record";
		$_STATE->status = ADD_ACCOUNT;
	} else {
		account_info();
		$_STATE->msgGreet = "Edit ".$_STATE->accounting." record?";
		$_STATE->status = UPDATE_ACCOUNT;
	}
	break 2;
case ADD_ACCOUNT:
	$_STATE->msgGreet = "New ".$_STATE->accounting." record";
	if (isset($_POST["btnReset"])) {
		break 2;
	}
	state_fields();
	if (new_audit()) {
		$record_id = $_STATE->record_id;
		$_STATE = $_STATE->loopback(SELECTED_ACCOUNT);
		$_STATE->record_id = $record_id;
		break 1; //re-switch with new record_id
	}
	break 2;
case UPDATE_ACCOUNT:
	$_STATE->msgGreet = "Edit ".$_STATE->accounting." record";
	if (isset($_POST["btnReset"])) {
		record_info();
		break 2; //start over
	}
	state_fields();
	if (isset($_POST["btnProperties"])) {
		$_STATE->status = PROPERTIES;
		$_STATE->element = "a21"; //required by PROPERTIES
		$_STATE->backup = PROPERTIES_GOBACK; //required by PROPERTIES
		break 1; //re-switch to show property values
	}
	if (update_audit()) {
		$_STATE = $_STATE->loopback(SELECTED_ACCOUNT);
		break 1; //re-switch
	}
	break 2;
case PROPERTIES:
	require_once "prop_set.php";
	$propset = PROP_SET_exec($_STATE, false);
	break 2;
case PROPERTIES_GOBACK:
	require_once "prop_set.php";
	PROP_SET_exec($_STATE, true);
	$_STATE = $_STATE->loopback(SELECTED_ACCOUNT);
	break 1;
default:
	throw_the_bum_out(NULL,"Evicted(".__LINE__."): invalid state=".$_STATE->status);
} } //while & switch

function state_fields() {
	global $_STATE;

	$_STATE->fields = array( //pagename,DBname,load from DB?,write to DB?,required?,maxlength
			"Name"=>new FIELD("txtName","name",TRUE,TRUE,TRUE,64),
			"Description"=>new AREA_FIELD("txtDesc","description",TRUE,TRUE,TRUE,256),
			"Inactive As Of"=>new DATE_FIELD("txtInactive","inactive_asof",TRUE,TRUE,FALSE,0),
			);
}

function accounting_list() {
	global $_DB, $_STATE;

	$_STATE->records = array();

	$sql = "SELECT * FROM ".$_DB->prefix."a20_accounting
			WHERE organization_idref=".$_SESSION["organization_id"]." ORDER BY timestamp;";
	$stmt = $_DB->query($sql);
	while ($row = $stmt->fetchObject()) {
		$_STATE->records[strval($row->accounting_id)] = substr($row->name.": ".$row->description,0,25);
	}
	$stmt->closeCursor();
}

function accounting_select($ID=-1) {
	global $_DB, $_STATE;

	if ($ID < 0) { //not yet selected
		accounting_list(); //restore the record list
		if (!array_key_exists(strval($_POST["selAccounting"]), $_STATE->records)) {
			throw_the_bum_out(NULL,"Evicted(".__LINE__."): invalid accounting id ".$_POST["selAccounting"]); //we're being spoofed
		}
		$ID = intval($_POST["selAccounting"]);
	}
	$_STATE->accounting_id = $ID;
	$sql = "SELECT name FROM ".$_DB->prefix."a20_accounting
			WHERE accounting_id=".$_STATE->accounting_id.";";
	$_STATE->accounting = $_DB->query($sql)->fetchObject()->name;
}

function account_list() {
	global $_DB, $_STATE;

	$_STATE->records = array();
	$_STATE->records["-1"] = "--create a new ".$_STATE->accounting." record--";

	$sql = "SELECT * FROM ".$_DB->prefix."a21_account
			WHERE accounting_idref=".$_STATE->accounting_id." ORDER BY name;";
	$stmt = $_DB->query($sql);
	while ($row = $stmt->fetchObject()) {
		$_STATE->records[strval($row->account_id)] = substr($row->name.": ".$row->description,0,40);
	}
	$stmt->closeCursor();
}

function account_select($ID=-1) {
	global $_DB, $_STATE;

	if ($ID < 0) { //not yet selected
		account_list(); //restore the record list
		if (!array_key_exists(strval($_POST["selAccount"]), $_STATE->records)) {
			throw_the_bum_out(NULL,"Evicted(".__LINE__."): invalid account id ".$_POST["selAccount"]); //we're being spoofed
		}
		$ID = intval($_POST["selAccount"]);
	}
	$_STATE->record_id = $ID;
	$sql = "SELECT name, description FROM ".$_DB->prefix."a21_account
			WHERE account_id=".$_STATE->record_id.";";
	$row = $_DB->query($sql)->fetchObject();
	$_STATE->forwho = $row->name.": ".$row->description; //PROPERTIES wants to see this
}

function account_info() {
	global $_DB, $_STATE;

	$sql = "SELECT * FROM ".$_DB->prefix."a21_account WHERE account_id=".$_STATE->record_id.";";
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
		//allow an "*" for the name field:
		if (($name == "Name") && ($_POST[$field->pagename] == "*")) {
			$field->value = "*";
			continue;
		}
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

	return TRUE;
}

function update_db() {
	global $_DB, $_STATE;

	$sql = "UPDATE ".$_DB->prefix."a21_account
			SET name=:name, description=:description, inactive_asof=:inactive
			WHERE account_id=".$_STATE->record_id.";";
	$stmt = $_DB->prepare($sql);
	$stmt->bindValue(':name',$_STATE->fields["Name"]->value(),PDO::PARAM_STR);
	$stmt->bindValue(':description',$_STATE->fields["Description"]->value(),PDO::PARAM_STR);
	if ($_STATE->fields["Inactive As Of"]->value() == "") {
		$stmt->bindValue(':inactive', NULL, db_connect::PARAM_DATE);
	} else {
		$stmt->bindValue(':inactive',$_STATE->fields["Inactive As Of"]->value(),db_connect::PARAM_DATE);
	}
	$stmt->execute();
}

function update_audit() {
	global $_STATE;

	if (!field_input_audit()) return FALSE;

	update_db();

	$_STATE->msgStatus = "The ".$_STATE->accounting." record for \"".$_STATE->fields["Name"]->value()."\" has been updated";
	return TRUE;
}

function new_audit() {
	global $_DB, $_STATE;

	if (!field_input_audit()) return FALSE;
	
	$hash = md5($_STATE->fields["Name"]->value().$_STATE->fields["Description"]->value());
	$sql = "INSERT INTO ".$_DB->prefix."a21_account (name, accounting_idref)
			VALUES (:hash, ".$_STATE->accounting_id.");";
	$stmt = $_DB->prepare($sql);
	$stmt->bindValue(':hash',$hash,PDO::PARAM_STR);
	$stmt->execute();

	$sql = "SELECT account_id FROM ".$_DB->prefix."a21_account WHERE name=:hash;";
	$stmt = $_DB->prepare($sql);
	$stmt->bindValue(':hash',$hash,PDO::PARAM_STR);
	$stmt->execute();
	$_STATE->record_id = $stmt->fetchObject()->account_id;
	$stmt->closeCursor();

	update_db();

	$_STATE->msgStatus = "The ".$_STATE->accounting." record for \"".$_STATE->fields["Name"]->value()."\" has been added to the accounting group";
	return TRUE;
}

if ($_STATE->status == PROPERTIES) {
	$_STATE->msgGreet = $propset->greeting();
	$scripts = $propset->set_script();
} else {
	$scripts = array();
}
EX_pageStart($scripts); //standard HTML page start stuff - insert scripts here
EX_pageHead(); //standard page headings - after any scripts

//forms and display depend on process state; note, however, that the state was probably changed after entering
//the Main State Gate so this switch will see the next state in the process:
switch ($_STATE->status) {
case SELECT_ACCOUNTING:
?>
  <p>
<form method="post" name="frmAction" id="frmAction_ID" action="<?php echo $_SESSION["IAm"]; ?>">
  <select name='selAccounting' size="<?php echo count($_STATE->records); ?>" onclick="this.form.submit()">
<?php
	foreach($_STATE->records as $value => $name) {
		echo "    <option value=\"".$value."\">".$name."\n";
	} ?>
  </select>
</form>
  </p>
<?php //end SELECT_ACCOUNTING status ----END STATUS PROCESSING----
	break;
case SELECT_ACCOUNT:
?>
  <p>
<form method="post" name="frmAction" id="frmAction_ID" action="<?php echo $_SESSION["IAm"]; ?>">
  <select name='selAccount' size="<?php echo count($_STATE->records); ?>" onclick="this.form.submit()">
<?php
	foreach($_STATE->records as $value => $name) {
		echo "    <option value=\"".$value."\">".$name."\n";
	} ?>
  </select>
</form>
  </p>
<?php //end SELECT_ACCOUNT status ----END STATUS PROCESSING----
	break;

case SELECTED_ACCOUNT:
case ADD_ACCOUNT:
case UPDATE_ACCOUNT:
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
      <td class="label"><?php echo $_STATE->fields['Inactive As Of']->HTML_label("Inactive As Of(yyyy-mm-dd): "); ?></td>
      <td><?php echo $_STATE->fields['Inactive As Of']->HTML_input(10) ?></td>
      <td>&nbsp</td>
    </tr>
  </table>
  <p>
<?php
	if ($_STATE->status == ADD_ACCOUNT ) {
		echo FIELD_edit_buttons(FIELD_ADD);
	} else {
		echo Field_edit_buttons(FIELD_UPDATE); ?>
  <br><button type='submit' name='btnProperties' id='btnProperties_ID' value='values'>Show Properties</button><br>
<?php
	} ?>
</form>
<?php //end SELECTED/ADD/UPDATE_ACCOUNT status ----END STATUS PROCESSING----
	break;

case PROPERTIES: //list properties and allow new entry:
	$propset->set_HTML();
?>

<?php //end select ($_STATE->status) ----END STATE: EXITING FROM PROCESS----
}
EX_pageEnd(); //standard end of page stuff
?>
