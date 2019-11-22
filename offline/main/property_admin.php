<?php
//copyright 2016,2019 C.D.Price. Licensed under Apache License, Version 2.0
//See license text at http://www.apache.org/licenses/LICENSE-2.0
if (!$_PERMITS->can_pass("property_admin")) throw_the_bum_out(NULL,"Evicted(".__LINE__."): no permit");

require_once "lib/field_edit.php";

//Define the cases for the Main State Gate that are unique to this module:
define('LIST_PROPERTIES',	STATE::INIT);
define('SELECT_PROPERTY',		LIST_PROPERTIES + 1);
define('SELECTED_PROPERTY',		LIST_PROPERTIES + 2);
define('ADD_PROPERTY',			LIST_PROPERTIES + 4);
define('UPDATE_PROPERTY',		LIST_PROPERTIES + 6);
define('LIST_VALUES',		STATE::INIT + 10);
define('SELECT_VALUE',			LIST_VALUES + 1);
define('SELECTED_VALUE',		LIST_VALUES + 2);
define('ADD_VALUE',				LIST_VALUES + 4);
define('UPDATE_VALUE',			LIST_VALUES + 6);

//Main State Gate: (the while (1==1) allows a loop back through the switch using a 'break 1')
while (1==1) { switch ($_STATE->status) {
case LIST_PROPERTIES:
	list_properties();
	$_STATE->msgGreet = "Select a property to edit";
	Page_out();
	$_STATE->status = SELECT_PROPERTY;
	break 2; //return to executive

case SELECT_PROPERTY:
	select_property();
case SELECTED_PROPERTY:
	$_STATE->set_a_gate(SELECTED_PROPERTY); //for a 'goback' - sets status
	prop_fields();
	if ($_STATE->record_id == -1) {
		$_STATE->msgGreet = "New property";
		$_STATE->status = ADD_PROPERTY;
	} else {
		property_info();
		$_STATE->msgGreet = "Edit property?";
		$_STATE->status = UPDATE_PROPERTY;
	}
	Page_out();
	$_STATE->goback_to(LIST_PROPERTIES);
	break 2; //return to executive

case ADD_PROPERTY:
	if (isset($_POST["btnReset"])) {
		$_STATE = $_STATE->goback_to(SELECTED_PROPERTY, true);
		break 1;
	}
	prop_fields();
	if (new_property_audit()) {
		$record_id = $_STATE->record_id;
		$_STATE = $_STATE->goback_to(SELECTED_PROPERTY, true);
		$_STATE->record_id = $record_id;
		break 1; //re-switch with new record_id
	}
	Page_out(); //errors...
	break 2; //return to executive

case UPDATE_PROPERTY:
	if (isset($_POST["btnReset"])) {
		$_STATE = $_STATE->goback_to(SELECTED_PROJECT, true);
		break 1;
	}
	prop_fields();
	if (isset($_POST["btnDelete"])) {
		delete_property();
		$_STATE = $_STATE->goback_to(LIST_PROPERTIES, true);
		break 1; //re-switch
	}
	if (isset($_POST["btnValues"])) {
		$_STATE->status = LIST_VALUES;
		break 1; //re-switch to show property values
	}
	if (update_property_audit()) {
		$_STATE = $_STATE->goback_to(SELECTED_PROPERTY, true);
		break 1; //re-switch
	}
	Page_out(); //errors...
	break 2; //return to executive

case LIST_VALUES:
	$_STATE->set_a_gate(LIST_VALUES); //for a 'goback' - sets status
	$_STATE->msgGreet_prefix = $_STATE->property_name."<br>";
	$_STATE->property_id = $_STATE->record_id; //save this guy for list_values()
	list_values();
	$_STATE->msgGreet = $_STATE->msgGreet_prefix."Select a value to edit";
	$_STATE->goback_to(SELECTED_PROPERTY);
	Page_out();
	$_STATE->status = SELECT_VALUE;
	break 2; //return to executive

case SELECT_VALUE:
	select_value();
case SELECTED_VALUE:
	$_STATE->set_a_gate(SELECTED_VALUE); //for a 'goback' - sets status
	prop_fields();
	$_STATE->backup = LIST_VALUES; //for goback
	if ($_STATE->record_id == -1) {
		$_STATE->msgGreet = $_STATE->msgGreet_prefix."New property value";
		$_STATE->status = ADD_VALUE;
	} else {
		value_info();
		$_STATE->msgGreet = $_STATE->msgGreet_prefix."Edit property value?";
		$_STATE->status = UPDATE_VALUE;
	}
	Page_out();
	$_STATE->goback_to(LIST_VALUES);
	break 2; //return to executive

case ADD_VALUE:
	if (isset($_POST["btnReset"])) {
		$_STATE = $_STATE->goback_to(SELECTED_VALUE, true);
		break 1;
	}
	prop_fields();
	$_STATE->msgGreet = $_STATE->msgGreet_prefix."New property";
	if (new_value_audit()) {
		$record_id = $_STATE->record_id;
		$_STATE = $_STATE->goback_to(SELECTED_VALUE, true);
		$_STATE->record_id = $record_id;
		break 1; //re-switch with new record_id
	}
	Page_out(); //errors...
	break 2; //return to executive

case UPDATE_VALUE:
	if (isset($_POST["btnReset"])) {
		$_STATE = $_STATE->goback_to(SELECTED_VALUE, true);
		break 1;
	}
	prop_fields();
	$_STATE->msgGreet = $_STATE->msgGreet_prefix."Edit property";
	if (isset($_POST["btnDelete"])) {
		delete_value();
		$_STATE = $_STATE->goback_to(LIST_VALUES, true);
		break 1; //re-switch
	}
	if (update_value_audit()) {
		$_STATE = $_STATE->loopback(SELECTED_VALUE);
		break 1; //re-switch
	}
	Page_out(); //errors...
	break 2; //return to executive

default:
	throw_the_bum_out(NULL,"Evicted(".$_STATE->ID."/".__LINE__."): invalid state=".$_STATE->status);
} } //while & switch
//End Main State Gate & return to executive

function prop_fields() {
	global $_STATE;

	$_STATE->fields = array( //pagename,DBname,load from DB?,write to DB?,required?,maxlength
			"Name"=>new FIELD("txtName","name",TRUE,TRUE,TRUE,64),
			"Description"=>new AREA_FIELD("txtDesc","description",TRUE,TRUE,TRUE,256),
			);
}

function list_properties() {
	global $_DB, $_STATE;

	$_STATE->records = array();
	$_STATE->records["-1"] = "--create a new property--";

	$sql = "SELECT * FROM ".$_DB->prefix."e00_property
			WHERE organization_idref=".$_SESSION["organization_id"]." ORDER BY name;";
	$stmt = $_DB->query($sql);
	while ($row = $stmt->fetchObject()) {
		$_STATE->records[strval($row->property_id)] = substr($row->name.": ".$row->description,0,25);
	}
	$stmt->closeCursor();
}

function list_values() {
	global $_DB, $_STATE;

	$_STATE->records = array();
	$_STATE->records["-1"] = "--create a new property value--";

	$sql = "SELECT * FROM ".$_DB->prefix."e02_prop_value
			WHERE property_idref=".$_STATE->property_id." ORDER BY name;";
	$stmt = $_DB->query($sql);
	while ($row = $stmt->fetchObject()) {
		$_STATE->records[strval($row->prop_value_id)] = substr($row->name.": ".$row->description,0,25);
	}
	$stmt->closeCursor();
}

function select_property() {
	global $_STATE;

	list_properties(); //restore the record list
	if (!array_key_exists(strval($_POST["selProp"]), $_STATE->records)) {
		throw_the_bum_out(NULL,"Evicted(".__LINE__."): invalid property id ".$_POST["selProp"]); //we're being spoofed
	}
	$_STATE->record_id = intval($_POST["selProp"]);
}

function select_value() {
	global $_STATE;

	list_values(); //restore the record list
	if (!array_key_exists(strval($_POST["selProp"]), $_STATE->records)) {
		throw_the_bum_out(NULL,"Evicted(".__LINE__."): invalid property id ".$_POST["selProp"]); //we're being spoofed
	}
	$_STATE->record_id = intval($_POST["selProp"]);
}

function property_info() {
	global $_DB, $_STATE;

	$sql = "SELECT * FROM ".$_DB->prefix."e00_property WHERE property_id=".$_STATE->record_id.";";
	$stmt = $_DB->query($sql);
	$row = $stmt->fetchObject();
	foreach($_STATE->fields as $field=>&$props) { //preset record info on the page
		if ($props->load_from_DB) {
			$props->value($row->{$props->dbname});
		}
	}
	$stmt->closeCursor();
	$_STATE->property_name = $_STATE->fields["Name"]->value();
}

function value_info() {
	global $_DB, $_STATE;

	$sql = "SELECT * FROM ".$_DB->prefix."e02_prop_value WHERE prop_value_id=".$_STATE->record_id.";";
	$stmt = $_DB->query($sql);
	$row = $stmt->fetchObject();
	foreach($_STATE->fields as $field=>&$props) { //preset record info on the page
		if ($props->load_from_DB) {
			$props->value($row->{$props->dbname});
		}
	}
	$stmt->closeCursor();
	$_STATE->prop_value_name = $_STATE->fields["Name"]->value();
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

	foreach ($_STATE->fields as $name => $field) {
		$field->disabled = true;
	}

	return TRUE;
}

function delete_property() {
	global $_DB, $_STATE;

	$sql = "DELETE FROM ".$_DB->prefix."e04_prop_element
			WHERE prop_value_idref IN (
				SELECT prop_value_id FROM ".$_DB->prefix."e02_prop_value
				WHERE property_idref=".$_STATE->record_id."
			);";
	$_DB->exec($sql);
	
	$sql = "DELETE FROM ".$_DB->prefix."e02_prop_value
			WHERE property_idref=".$_STATE->record_id.";";
	$_DB->exec($sql);

	$sql = "DELETE FROM ".$_DB->prefix."e00_property
			WHERE property_id=".$_STATE->record_id.";";
	$_DB->exec($sql);
}

function delete_value() {
	global $_DB, $_STATE;

	$sql = "DELETE FROM ".$_DB->prefix."e04_prop_element
			WHERE prop_value_idref=".$_STATE->record_id.";";
	$_DB->exec($sql);
	
	$sql = "DELETE FROM ".$_DB->prefix."e02_prop_value
			WHERE prop_value_id=".$_STATE->record_id.";";
	$_DB->exec($sql);
}

function update_property() {
	global $_DB, $_STATE;

	$sql = "UPDATE ".$_DB->prefix."e00_property
			SET name=:name, description=:description
			WHERE property_id=".$_STATE->record_id.";";
	$stmt = $_DB->prepare($sql);
	$stmt->bindValue(':name',$_STATE->fields["Name"]->value(),PDO::PARAM_STR);
	$stmt->bindValue(':description',$_STATE->fields["Description"]->value(),PDO::PARAM_STR);
	$stmt->execute();
}

function update_value() {
	global $_DB, $_STATE;

	$sql = "UPDATE ".$_DB->prefix."e02_prop_value
			SET name=:name, description=:description
			WHERE prop_value_id=".$_STATE->record_id.";";
	$stmt = $_DB->prepare($sql);
	$stmt->bindValue(':name',$_STATE->fields["Name"]->value(),PDO::PARAM_STR);
	$stmt->bindValue(':description',$_STATE->fields["Description"]->value(),PDO::PARAM_STR);
	$stmt->execute();
}

function update_property_audit() {
	global $_DB, $_STATE;

	if (!field_input_audit()) return FALSE;

	update_property();

	$_STATE->msgStatus = "The property \"".$_STATE->fields["Name"]->value()."\" has been updated";
	return TRUE;
}

function update_value_audit() {
	global $_DB, $_STATE;

	if (!field_input_audit()) return FALSE;

	update_value();

	$_STATE->msgStatus = "The value \"".$_STATE->fields["Name"]->value()."\" has been updated";
	return TRUE;
}

function new_property_audit() {
	global $_DB, $_STATE;

	if (!field_input_audit()) return FALSE;
	
	$hash = md5($_STATE->fields["Name"]->value().$_STATE->fields["Description"]->value());
	$sql = "INSERT INTO ".$_DB->prefix."e00_property (name, organization_idref)
			VALUES (:hash, ".$_SESSION["organization_id"].");";
	$stmt = $_DB->prepare($sql);
	$stmt->bindValue(':hash',$hash,PDO::PARAM_STR);
	$stmt->execute();

	$sql = "SELECT property_id FROM ".$_DB->prefix."e00_property WHERE name=:hash;";
	$stmt = $_DB->prepare($sql);
	$stmt->bindValue(':hash',$hash,PDO::PARAM_STR);
	$stmt->execute();
	$_STATE->record_id = $stmt->fetchObject()->property_id;
	$stmt->closeCursor();

	update_property();

	$_STATE->msgStatus = "The property \"".$_STATE->fields["Name"]->value()."\" has been added to the project";
	return TRUE;
}

function new_value_audit() {
	global $_DB, $_STATE;

	if (!field_input_audit()) return FALSE;
	
	$hash = md5($_STATE->fields["Name"]->value().$_STATE->fields["Description"]->value());
	$sql = "INSERT INTO ".$_DB->prefix."e02_prop_value (name, property_idref)
			VALUES (:hash, ".$_STATE->property_id.");";
	$stmt = $_DB->prepare($sql);
	$stmt->bindValue(':hash',$hash,PDO::PARAM_STR);
	$stmt->execute();

	$sql = "SELECT prop_value_id FROM ".$_DB->prefix."e02_prop_value WHERE name=:hash;";
	$stmt = $_DB->prepare($sql);
	$stmt->bindValue(':hash',$hash,PDO::PARAM_STR);
	$stmt->execute();
	$_STATE->record_id = $stmt->fetchObject()->prop_value_id;
	$stmt->closeCursor();

	update_value();

	$_STATE->msgStatus = "The value \"".$_STATE->fields["Name"]->value()."\" has been added to the property";
	return TRUE;
}

function Page_out() {
	global $_DB, $_STATE;

	EX_pageStart(); //standard HTML page start stuff - insert SCRIPTS here

	switch ($_STATE->status) {
	case UPDATE_PROPERTY:
	case UPDATE_VALUE:
?>
<script type='text/javascript'>

function check_delete() {
	if (!confirm("Are you sure you want to delete the record?")) return false;
	return true;
}

</script>
<?php
		break;
	} //end switch ($_STATE->status)

	EX_pageHead(); //standard page headings - after any scripts

	switch ($_STATE->status) {

	case LIST_PROPERTIES:
	case LIST_VALUES:
?>
  <p>
<form method="post" name="frmAction" id="frmAction_ID" action="<?php echo $_SESSION["IAm"]; ?>">
  <select name='selProp' size="<?php echo count($_STATE->records); ?>" onclick="this.form.submit()">
<?php
		foreach($_STATE->records as $value => $name) {
	  		echo "    <option value=\"".$value."\">".$name."\n";
		}
?>
  </select>
</form>
  </p>
<?php
		break; //end LIST_PROPERTIES/VALUES status ----END STATUS PROCESSING----

	case ADD_PROPERTY:
	case UPDATE_PROPERTY:
	case ADD_VALUE:
	case UPDATE_VALUE:
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
  </table>
  <p>
<?php
		if ($_STATE->status == STATE::ADD ) {
			echo FIELD_edit_buttons(FIELD_ADD);
		} else {
			echo Field_edit_buttons(FIELD_UPDATE);
		}
		if ($_STATE->status == UPDATE_PROPERTY) {
?>
  <br><button type='submit' name='btnValues' id='btnValues_ID' value='values'>Show Property Values</button><br>
<?php
		}
		if (($_STATE->status == UPDATE_PROPERTY) || ($_STATE->status == UPDATE_VALUE)) {
?>
  <br><button type='submit' name='btnDelete' id='btnDelete_ID' value='delete' onclick="return check_delete()">Delete</button>
<?php
		}
?>
</form>
<?php
		break; //end ADD/UPDATE_PROPERTY/VALUE status ----END STATUS PROCESSING----

	default:
		throw_the_bum_out(NULL,"Evicted(".$_STATE->ID."/".__LINE__."): invalid state=".$_STATE->status);

	} //end select ($_STATE->status) ----END STATE: EXITING FROM PROCESS----

	EX_pageEnd(); //standard end of page stuff

} //end Page_out()
?>
