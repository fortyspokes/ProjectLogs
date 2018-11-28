<?php
//copyright 2016 C.D.Price. Licensed under Apache License, Version 2.0
//See license text at http://www.apache.org/licenses/LICENSE-2.0

//Generalized processing to display/set/unset element properties.

//Instructions:
//The calling module should have 2 states:
//	The processing state (define 'PROPERTIES'?) will:
//		include this module,
//		$PROP_SETobject = PROP_SET_exec($_STATE, false);,
//		'break 2'; to fall thru and display the properties
//	The 'goback' state will:
//		include this module,
//		PROP_SET_exec($_STATE, true);,
//		loopback to the caller's state,
//		'break 1'; to go back around to the loopback state
//This module expects to see:
//	$_STATE as a parm to PROP_SET_exec;
//	$_STATE->record_id pointing to the element;
//	$_STATE->element = the 3-char element table prefix, eg. 'a21' for a21_account;
//	$_STATE->forwho = the element name/description for msgGreet purposes.
//	$_STATE->backup = the 'goback' state
//Before calling $EX_pageStart() and if processing state set:
//	get scripts from $PROP_SETobject->set_script();,
//	get msgGreet from $PROP_SETobject->greeting();.
//When creating HTML at processing state:
//	$PROP_SETobject->set_HTML();
//	note: when doing server call-back, $EX_pageStart() does not return to get here
//See account.php for an example.

function PROP_SET_exec(&$state, $done) {
	if (!isset($state->propset)) { //first time thru
		$state->propset = serialize(new PROP_SET($state->element, $state->record_id, $state->forwho));
		$status = $state->status; //save it
		$state->status = $state->backup; //come back here on a goback
		$state->push(); //put it into the stack so loopback() can find it
		$state->status = $status; //restore
		return unserialize($state->propset); //display table of properties
	} elseif ($done) { //it's a 'goback'
		$propset = unserialize($state->propset);
		$propset->goback(); //all done with $propset
		return;
	} else {
		$propset = unserialize($state->propset);
		$propset->state_gate(); //PROP_SET does the heavy lifting
		return $propset;
	}
}

class PROP_SET {

	public $element; //the 3char table id
	public $element_id;
	public $forwho; //element desc for msgGreet

	private $state; //our thread
	public $noSleep = array(); //clear out these user created vars when sleeping (to save memory)
	private $records = array();

	const NAME_DISP		= STATE::INIT + 1;
	const NAME_PICK		= STATE::INIT + 2;
	const VALUE_DISP	= STATE::INIT + 3;
	const VALUE_PICK	= STATE::INIT + 4;
	const BUTTON_DISP	= STATE::INIT + 5;
	const CHANGE		= STATE::CHANGE;
	const CHANGED		= STATE::CHANGE + 1;

function __construct($element, $element_id, $forwho) {
	$this->element = $element;
	$this->element_id = $element_id;
	$this->forwho = $forwho;
	$this->state = new STATE("PS", "PROP_SET");
	$this->setNoSleep("state");
	$this->get_recs();
}

function __sleep() { //don't save this stuff - temporary and too long
	foreach ($this->noSleep as $temp) {
		if (is_array($this->{$temp})) {
			$this->{$temp} = array();
		} else {
			$this->{$temp} = false;
		}
	}
	$this->records = array();
   	return array_keys(get_object_vars($this));
}

function __wakeup() {
	$this->state = STATE_pull("PROP_SET");
	$this->get_recs();
}

public function __set($key, $value) { //set dynamic vars
	$this->$key = $value;
}

function goback() {
	$this->state->cleanup($this->state->thread);
	return true;
}

function greeting() {
	return "Properties for ".$this->forwho.
			"<br>To add or change: click on the lefthand column";
}

function set_script() {
	return array("call_server.js","propset.js");
}

function set_HTML() {
	echo $this->set_list();
}

private function get_recs() {
	global $_DB;

	$sql = "SELECT e00.name, e00.property_id AS name_id,
				e02.name AS value, e02.prop_value_id AS value_id,
				e04.prop_element_id
			FROM (
				SELECT property_id, name FROM ".$_DB->prefix."e00_property
				WHERE organization_idref = ".$_SESSION["organization_id"]."
			) AS e00
			JOIN ".$_DB->prefix."e02_prop_value AS e02 ON e02.property_idref = e00.property_id
			JOIN (
				SELECT prop_element_id, prop_value_idref FROM ".$_DB->prefix."e04_prop_element
				WHERE element_table = '".$this->element."' AND element_idref = ".$this->element_id."
			) AS e04 ON e04.prop_value_idref = e02.prop_value_id
			ORDER BY name, value;";
	$stmt = $_DB->query($sql);
	while ($row = $stmt->fetchObject()) {
		$record = array(
				"name" =>		$row->name,
				"name_id" =>	$row->name_id,
				"value" =>		$row->value,
				"value_id" =>	$row->value_id,
				"ID" =>			$row->prop_element_id,
		);
		$this->records[$row->prop_element_id] = $record;
	}
	$stmt->closeCursor();
}

function setNoSleep($var) {
	$this->noSleep[] = $var;
}

public function show_list() { //get the HTML for the list items
	global $_VERSION;

	$HTML = array();

	$HTML[] = "<table align='center' id='tblLog' cellpadding='4' border='2'>";
	$HTML[] = "  <tr>\n";
	$HTML[] = "    <th width='100'>&nbsp;</th>";
	$HTML[] = "    <th width='140'>Name</th>";
	$HTML[] = "    <th width='140'>Value</th>";
	$HTML[] = "  </tr>";
	$HTML[] = "  <tr id='add'>";
	$HTML[] = "    <td id='BN_0' data-recid='0' title='Click to add property values'><img src='".$_SESSION["BUTLER"]."?IAm=IG&file=add.png&ver=".$_VERSION."'></td>";
	$HTML[] = "    <td id='NM_0' data-recid='0'></td>";
	$HTML[] = "    <td id='VA_0' data-recid='0'></td>";
	$HTML[] = "  </tr>";
	$row = 1;
	foreach ($this->records as $record) {
		$HTML[] = "  <tr>";
		$HTML[] = "    <td id='BN_".$row."' data-recid='".$record["ID"]."' class=seq>".$row."</td>";
		$HTML[] = "    <td id='NM_".$row."' data-recid='".$record["name_id"]."'>".$record["name"]."</td>";
		$HTML[] = "    <td id='VA_".$row."' data-recid='".$record["value_id"]."'>".$record["value"]."</td>";
		$HTML[] = "  </tr>";
		++$row;
	}
	$HTML[] = "</table>";
	return $HTML;
}

public function set_list() { //set up initial form and select
	$HTML = "";
	$list = $this->show_list();
	foreach ($list as $line) $HTML .= $line."\n";
	return $HTML;
}

function state_gate() {

	if (($this->state->status == PROP_SET::CHANGED) || (isset($_GET["reset"]))) {
		$this->state = $this->state->loopback(STATE::INIT);
		return;
	}
	if (isset($_GET["getdesc"])) { //asking for the description of a cell
		$this->cell_desc();;
		return;
	}

	if (!(isset($_GET["row"]) || isset($_POST["row"])))
		throw_the_bum_out(NULL,"Evicted(".__LINE__."): PROP_SET GET/POST row not supplied");

	$response = "@"; //initialized to do an eval
	//State Gate: (the while (1==1) allows a loop back through the switch using a 'break 1')
	while (1==1) { switch ($this->state->status) {
	case STATE::INIT:
		$this->state->agent = $_GET["agent"];
		$this->state->row = $_GET["row"]; //working on this displayed row
		$this->state->record_id = $_GET["id"];
		if ($this->state->row != 0)
			$this->state->prop_id = $this->records[$this->state->record_id]["name_id"];
		$this->state->path = array();
		switch ($this->state->agent) {
		case "BN": //button => adding/updating
			if ($this->state->row == 0) { //adding
				$this->state->path = array(PROP_SET::NAME_DISP,);
			}
			$this->state->path[] = PROP_SET::VALUE_DISP;
			break;
		case "VA": //value
			$this->state->path[] = PROP_SET::VALUE_DISP;
			break;
		default:
			throw_the_bum_out(NULL,"Evicted(".__LINE__."): PROP_SET invalid agent ".$this->state->agent,true);
		}
		$this->state->path[] = PROP_SET::BUTTON_DISP;
		$response .= "document.getElementById('BN_".$this->state->row."')";
		$response .= ".innerHTML = \"<button type='button' name='btnReset' onclick='Reset()'>Cancel</button>\";\n";
		$this->state->status = array_shift($this->state->path);
		break 1; //go back around to start down state->path[]
	case PROP_SET::NAME_DISP:
		if ($this->prop_send($response) == 1) {
			$this->prop_select($response, $this->state->prop_id);
			$this->state->status = array_shift($this->state->path);
			break 1; //go back around
		}
		$this->state->status = PROP_SET::NAME_PICK;
		echo $response;
		break 2; //done
	case PROP_SET::NAME_PICK:
		$this->prop_select($response);
		$this->state->status = array_shift($this->state->path);
		break 1; //go back around
	case PROP_SET::VALUE_DISP:
		if ($this->value_send($response) == 1) {
			$this->value_select($response, $this->state->value_id);
			$this->state->status = array_shift($this->state->path);
			break 1; //go back around
		}
		$this->state->status = PROP_SET::VALUE_PICK;
		echo $response;
		break 2; //done
	case PROP_SET::VALUE_PICK:
		$this->value_select($response);
		$this->state->status = array_shift($this->state->path);
		break 1; //go back around
	case PROP_SET::BUTTON_DISP:
		include_once "callback/buttons.php";
		$this->button_send($response);
		echo $response;
		$this->state->status = PROP_SET::CHANGE;
		break 2; //break out
	case PROP_SET::CHANGE:
		$this->changes($response); //DO IT!
		echo $response;
		$this->state->status = PROP_SET::CHANGED;
		break 2; //break out
	default:
		throw_the_bum_out(NULL,"Evicted(".__LINE__."): PROP_SET error");
	} } //while & switch
	//End Main State Gate
	$this->state->push();

	return;
}

//	CALL BACK SECTION
//These routines handle the various server 'call-backs' not included from lib/callback.
//A 'call-back' leaves the page intact while a request is sent back to the server and the response then handled via script.

private function cell_desc() {
	global $_DB;

	$field = "description";
	switch ($_GET["getdesc"]) {
	case "NM":
		$table = "e00_property";
		$id = "property_id";
		break;
	case "VA":
		$table = "e02_prop_value";
		$id = "prop_value_id";
		break;
	default:
		throw_the_bum_out(NULL,"Evicted(".__LINE__."): PROP_SET invalid cell ID ".$_GET["getdesc"], true);
	}
	$key = $_GET["ID"];
	$sql = "SELECT ".$field." FROM ".$_DB->prefix.$table." WHERE ".$id."=:key;";
	$stmt = $_DB->prepare($sql);
	$stmt->bindValue(":key", $key, PDO::PARAM_INT);
	$stmt->execute();
	$row = $stmt->fetchObject();
	echo "@got_desc('".$row->{$field}."');\n";
}

//Populate the property name pulldown selection list then collect the response via server call-back:
private function prop_list() {
	global $_DB;

	$this->state->records = array();

	//show only those properties not already assigned:
	$sql = "SELECT * FROM ".$_DB->prefix."e00_property
			WHERE organization_idref=".$_SESSION["organization_id"]."
			AND property_id NOT IN (
				SELECT property_idref FROM ".$_DB->prefix."e02_prop_value AS e02
				JOIN ".$_DB->prefix."e04_prop_element AS e04
					ON e02.prop_value_id = e04.prop_value_idref
				WHERE e04.element_table = '".$this->element."'
				AND e04.element_idref = ".$this->element_id."
			)
			ORDER BY name;";
	$stmt = $_DB->query($sql);
	while ($row = $stmt->fetchObject()) {
		$this->state->records[strval($row->property_id)] = substr($row->name.": ".$row->description,0,25);
	}
	$stmt->closeCursor();
}

private function prop_send(&$HTML) {

	$this->prop_list();

	$HTML .= "//Properties...\n"; //for debug display
	if (count($this->state->records) == 1) {
		reset($this->state->records);
		$this->state->prop_id = key($this->state->records); //prop_select wants to see this

	} else {
    	$HTML .= "document.getElementById('msgGreet_ID').innerHTML = 'Properties for ".$this->forwho.
				"<br>Select the property';\n";
		$HTML .= "fill = \"<select name='selName' id='selName' size='1' onchange='proceed(this.parentNode,this.options[this.selectedIndex].value)'>\";\n";
		foreach($this->state->records as $value => $name) {
			$HTML .= "fill += \"<option value='".$value."'>".$name."\";\n";
		}
		$HTML .= "fill += \"</select>\";\n";
		$HTML .= "cell = document.getElementById('NM_".$this->state->row."');\n";
		$HTML .= "cell.innerHTML = fill;\n";
		$HTML .= "document.getElementById('selName').selectedIndex=-1;\n";
	}

	return count($this->state->records);
}

private function prop_select(&$HTML, $rec=-1) {

	if ($rec < 0) { //checking returned
		if (!isset($_GET["row"])) return;
		$rec = $_GET["row"]; //get row number
	}

	$this->prop_list(); //restore the record list
	if (!array_key_exists($rec, $this->state->records)) {
		throw_the_bum_out(NULL,"Evicted(".__LINE__."): PROP_SET invalid property id ".$rec,true);
	}
	$record = $this->state->records[$rec];
	$this->state->prop_id = $rec;
	$this->state->msgStatus = "";
	$HTML .= "cell = document.getElementById('NM_".$this->state->row."');\n";
	$HTML .= "cell.innerHTML = '".$record."';\n";
	$HTML .= "cell.setAttribute('data-recid',".$rec.");\n";
}

//Populate the property value pulldown selection list then collect the response via server call-back:
private function value_list() {
	global $_DB;

	$this->state->records = array();
	if (($this->state->agent == "BN") && ($this->state->row != 0)) { //allow a delete
		$this->state->records[0] = "--delete this property--";
	}

	$sql = "SELECT * FROM ".$_DB->prefix."e02_prop_value
			WHERE property_idref=".$this->state->prop_id."
			ORDER BY name;";
	$stmt = $_DB->query($sql);
	while ($row = $stmt->fetchObject()) {
		$this->state->records[strval($row->prop_value_id)] = substr($row->name.": ".$row->description,0,25);
	}
	$stmt->closeCursor();
}

private function value_send(&$HTML) {

	$this->value_list();

	$HTML .= "//Property values...\n"; //for debug display
	if (count($this->state->records) == 1) {
		reset($this->state->records);
		$this->state->value_id = key($this->state->records); //value_select wants to see this

	} else {
    	$HTML .= "document.getElementById('msgGreet_ID').innerHTML = 'Properties for ".$this->forwho.
				"<br>Select the value';\n";
		$HTML .= "fill = \"<select name='selValue' id='selValue' size='1' onchange='proceed(this.parentNode,this.options[this.selectedIndex].value)'>\";\n";
		foreach($this->state->records as $value => $name) {
			$HTML .= "fill += \"<option value='".$value."'>".$name."\";\n";
		}
		$HTML .= "fill += \"</select>\";\n";
		$HTML .= "cell = document.getElementById('VA_".$this->state->row."');\n";
		$HTML .= "cell.innerHTML = fill;\n";
		$HTML .= "document.getElementById('selValue').selectedIndex=-1;\n";
	}

	return count($this->state->records);
}

private function value_select(&$HTML, $rec=-1) {

	if ($rec < 0) { //checking returned
		if (!isset($_GET["row"])) return;
		$rec = $_GET["row"]; //get row number
	}

	$this->value_list(); //restore the record list
	if (!array_key_exists($rec, $this->state->records)) {
		throw_the_bum_out(NULL,"Evicted(".__LINE__."): PROP_SET invalid value id ".$rec,true);
	}
	$record = $this->state->records[$rec];
	$this->state->value_id = $rec;
	$this->state->msgStatus = "";
	$HTML .= "cell = document.getElementById('VA_".$this->state->row."');\n";
	$HTML .= "cell.innerHTML = '".$record."';\n";
	$HTML .= "cell.setAttribute('data-recid',".$rec.");\n";
}

//Send the enter/cancel buttons via server call-back:
private function button_send( &$HTML) {
	$HTML .= "//Buttons...\n";
	$HTML .= "cellID = 'BN_".$this->state->row."';\n";
	$HTML .= "cell = document.getElementById(cellID);\n";
	$HTML .= "cell.title = '';\n";
   	$HTML .= "document.getElementById('msgGreet_ID').innerHTML = 'Properties for ".$this->forwho.
			"<br>Select this property/value';\n";
	//onclick=onmousedown + onmouseup; if audit_count() caused by onblur of numbers issues confirm(),
	//onmouseup will not happen; in that case, mouseDown() will assure new_info() gets executed:
	$HTML .= "fill = \"<button type='button' onclick='changes(".$this->state->row.")' onmousedown='mouseDown(".$this->state->row.")'>";
	$HTML .= "Submit</button>";
	$HTML .= "<br><button type='button' name='btnReset' onclick='Reset()'>Cancel</button>";
	$HTML .= "\";\n";
	$HTML .= "cell.innerHTML = fill;\n";
}
//End CALL BACK SECTION

private function changes(&$response) {
	global $_DB;

	$response = "-"; //initialize to reset page

	//check that POSTed values = previously set in $this->state
	if (!isset($_POST["row"]) || $_POST["row"] != $this->state->row ||
		!isset($_POST["id"]) || $_POST["id"] != $this->state->record_id ||
		!isset($_POST["name"]) || $_POST["name"] != $this->state->prop_id ||
		!isset($_POST["value"]) || $_POST["value"] != $this->state->value_id) {
		throw_the_bum_out(NULL,"Evicted(".__LINE__."): PROP_SET invalid POST",true);
	}

	if ($this->state->row == 0) {
		$sql = "INSERT INTO ".$_DB->prefix."e04_prop_element
				(prop_value_idref, element_table, element_idref)
				VALUES(".$this->state->value_id.",'".$this->element."',".$this->element_id.");";
		$_DB->exec($sql);
		return;
	}

	if ($this->state->value_id == 0) {
		$sql = "DELETE FROM ".$_DB->prefix."e04_prop_element
				WHERE prop_element_id = ".$this->state->record_id.";";
		$_DB->exec($sql);
		return;
	}

	$sql = "UPDATE ".$_DB->prefix."e04_prop_element
			SET prop_value_idref = ".$this->state->value_id."
			WHERE prop_element_id = ".$this->state->record_id.";";
	$_DB->exec($sql);
	return;
}

} //end class
?>
