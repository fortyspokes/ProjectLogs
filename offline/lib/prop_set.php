<?php
//copyright 2016,2019 C.D.Price. Licensed under Apache License, Version 2.0
//See license text at http://www.apache.org/licenses/LICENSE-2.0

//Generalized processing to display/set/unset element properties.

//Instructions:
//The calling module must interface in the Main State Gate and in Page_out().

//Main State Gate
//Create a case, typically, "PROPERTIES" that must
// 1. Instantiate the object that creates a child state thread (="PROP_SET").  Then save our new
//    PROP_SET object in the _MAIN state;
// 2. Call the <PROP_SET>->state_gate() that will continue state processing on the child state;
// 3. If state_gate() returns 'false', the processing is done; the caller can then take appropriate action.

//Page_out()
// The PROP_SET scripts must be set;
// Call <PROP_SET>->get_page() that will echo the appropriate HTML then return a pointer to the
// child state object used by PROP_SET.  This state object must then be returned to the executive's
// EX_pageEnd(<state object>) function to implement gobacks.

//See subtask_edit.php for an example.

class PROP_SET {

	private $element; //the 3char table id
	private $element_id;
	private $forwho; //element desc for msgGreet
	private $HTML = "";
	private $records = array();

	const START			= STATE::INIT + 1; //if 0, set_a_gate does not work
	const LIST			= self::START + 1;
	const SELECT		= self::START + 2;

	const EDIT			= self::START + 10;
	const NAME_DISP		= self::EDIT + 1;
	const NAME_PICK		= self::EDIT + 2;
	const VALUE_DISP	= self::EDIT + 3;
	const VALUE_PICK	= self::EDIT + 4;
	const BUTTON_DISP	= self::EDIT + 5;
	const CHANGE		= self::EDIT + 6;
	const CHANGED		= self::EDIT + 7;

	const THREAD = 'PROP_SET';

//$state=>_MAIN state; $element=>the table prefix, $element_id=>$element's record,
//$forwho=>just a string to be displayed in the header:
function __construct(&$state, $element, $element_id, $forwho) {
	$this->element = $element;
	$this->element_id = $element_id;
	$this->forwho = $forwho;
	$state->scion_start(self::THREAD, self::START);
}

function __sleep() { //don't save this stuff - temporary and too long
	$this->HTML = "";
	$this->records = array();
   	return array_keys(get_object_vars($this));
}

public function state_gate() {

	$state = STATE_pull(self::THREAD);

	if (isset($_GET["reset"])) {
		$state = $state->goback_to(self::LIST, true);
	}
	if (isset($_GET["getdesc"])) { //asking for the description of a cell
		$this->cell_desc();;
		return;
	}

	$response = "@"; //initialized to do an eval if servercall
	while (1==1) { switch ($state->status) {

	case self::START:
		if ($state->set_a_gate(self::START)) {
			return false; //all done here
		}
		$state->push(); //get it on the stack
		$state = STATE_pull(self::THREAD); //stay up-to-date
		$state->status = self::LIST;
		break 1;

	case self::LIST:
		$state->set_a_gate(self::LIST);
		$state->goback_to(self::START);
		$this->get_recs();
		$this->Page_out($state);
		$state->status = self::SELECT;
		break 2;

	case self::SELECT:
		if (!(isset($_GET["row"]) || isset($_POST["row"])))
			throw_the_bum_out(NULL,"Evicted(".__LINE__."): PROP_SET GET/POST row not supplied");
		$state->goback_to(self::LIST);
		$this->get_recs();
		$state->agent = $_GET["agent"];
		$state->row = $_GET["row"]; //working on this displayed row
		$state->record_id = $_GET["id"];
		if ($state->row != 0)
			$state->prop_id = $this->records[$state->record_id]["name_id"];
		$state->path = array();
		switch ($state->agent) {
		case "BN": //button => adding/updating
			if ($state->row == 0) { //adding
				$state->path = array(self::NAME_DISP,);
			}
			$state->path[] = self::VALUE_DISP;
			break;
		case "VA": //value
			$state->path[] = self::VALUE_DISP;
			break;
		default:
			throw_the_bum_out(NULL,"Evicted(".__LINE__."): PROP_SET invalid agent ".$state->agent,true);
		}
		$state->path[] = self::BUTTON_DISP;
		$response .= "document.getElementById('BN_".$state->row."')";
		$response .= ".innerHTML = \"<button type='button' name='btnReset' onclick='Reset()'>Cancel</button>\";\n";
		$state->status = array_shift($state->path);
		break 1; //go back around to start down state->path[]

	case self::NAME_DISP:
		if ($this->prop_send($state, $response) == 1) {
			$this->prop_select($state, $response, $state->prop_id);
			$state->status = array_shift($state->path);
			break 1; //go back around
		}
		$state->status = self::NAME_PICK;
		echo $response;
		break 2; //done

	case self::NAME_PICK:
		$this->prop_select($state, $response);
		$state->status = array_shift($state->path);
		break 1; //go back around

	case self::VALUE_DISP:
		if ($this->value_send($state, $response) == 1) {
			$this->value_select($state, $response, $state->value_id);
			$state->status = array_shift($state->path);
			break 1; //go back around
		}
		$state->status = self::VALUE_PICK;
		echo $response;
		break 2; //done

	case self::VALUE_PICK:
		$this->value_select($state, $response);
		$state->status = array_shift($state->path);
		break 1; //go back around

	case self::BUTTON_DISP:
		include_once "callback/buttons.php";
		$this->button_send($state, $response);
		echo $response;
		$state->status = self::CHANGE;
		break 2; //break out

	case self::CHANGE:
		$this->changes($state, $response); //DO IT!
		echo $response;
		$this->state->status = self::CHANGED;
		break 2; //break out

	case self::CHANGED:
		$state = $state->goback_to(self::LIST, true);
		break 1;

	default:
		throw_the_bum_out(NULL,"Evicted(".__LINE__."): PROP_SET invalid state=".$state->status);
	} }
	$state->push();

	return true;
}

private function Page_out(&$state) {

	switch ($state->status) {

	case self::LIST:
		$this->HTML = $this->set_list();
		break;

	default:
		throw_the_bum_out(NULL,"Evicted(".__LINE__."): PROP_SET invalid state=".$state->status);
	}
}

public function get_page() {
	echo $this->HTML;
	return STATE_pull(self::THREAD);
}

public function greeting() {
	return "Properties for ".$this->forwho.
			"<br>To add or change: click on the lefthand column";
}

public function set_script() {
	return array("call_server.js","propset.js");
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
private function prop_list(&$state) {
	global $_DB;

	$state->records = array();

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
		$state->records[strval($row->property_id)] = substr($row->name.": ".$row->description,0,25);
	}
	$stmt->closeCursor();
}

private function prop_send(&$state, &$HTML) {

	$this->prop_list($state);

	$HTML .= "//Properties...\n"; //for debug display
	if (count($state->records) == 1) {
		reset($state->records);
		$state->prop_id = key($state->records); //prop_select wants to see this

	} else {
    	$HTML .= "document.getElementById('msgGreet_ID').innerHTML = 'Properties for ".$this->forwho.
				"<br>Select the property';\n";
		$HTML .= "fill = \"<select name='selName' id='selName' size='1' onchange='proceed(this.parentNode,this.options[this.selectedIndex].value)'>\";\n";
		foreach($state->records as $value => $name) {
			$HTML .= "fill += \"<option value='".$value."'>".$name."\";\n";
		}
		$HTML .= "fill += \"</select>\";\n";
		$HTML .= "cell = document.getElementById('NM_".$state->row."');\n";
		$HTML .= "cell.innerHTML = fill;\n";
		$HTML .= "document.getElementById('selName').selectedIndex=-1;\n";
	}

	return count($state->records);
}

private function prop_select(&$state, &$HTML, $rec=-1) {

	if ($rec < 0) { //checking returned
		if (!isset($_GET["row"])) return;
		$rec = $_GET["row"]; //get row number
	}

	$this->prop_list($state); //restore the record list
	if (!array_key_exists($rec, $state->records)) {
		throw_the_bum_out(NULL,"Evicted(".__LINE__."): PROP_SET invalid property id ".$rec,true);
	}
	$record = $state->records[$rec];
	$state->prop_id = $rec;
	$state->msgStatus = "";
	$HTML .= "cell = document.getElementById('NM_".$state->row."');\n";
	$HTML .= "cell.innerHTML = '".$record."';\n";
	$HTML .= "cell.setAttribute('data-recid',".$rec.");\n";
}

//Populate the property value pulldown selection list then collect the response via server call-back:
private function value_list(&$state) {
	global $_DB;

	$state->records = array();
	if (($state->agent == "BN") && ($state->row != 0)) { //allow a delete
		$state->records[0] = "--delete this property--";
	}

	$sql = "SELECT * FROM ".$_DB->prefix."e02_prop_value
			WHERE property_idref=".$state->prop_id."
			ORDER BY name;";
	$stmt = $_DB->query($sql);
	while ($row = $stmt->fetchObject()) {
		$state->records[strval($row->prop_value_id)] = substr($row->name.": ".$row->description,0,25);
	}
	$stmt->closeCursor();
}

private function value_send(&$state, &$HTML) {

	$this->value_list($state);

	$HTML .= "//Property values...\n"; //for debug display
	if (count($state->records) == 1) {
		reset($state->records);
		$state->value_id = key($state->records); //value_select wants to see this

	} else {
    	$HTML .= "document.getElementById('msgGreet_ID').innerHTML = 'Properties for ".$this->forwho.
				"<br>Select the value';\n";
		$HTML .= "fill = \"<select name='selValue' id='selValue' size='1' onchange='proceed(this.parentNode,this.options[this.selectedIndex].value)'>\";\n";
		foreach($state->records as $value => $name) {
			$HTML .= "fill += \"<option value='".$value."'>".$name."\";\n";
		}
		$HTML .= "fill += \"</select>\";\n";
		$HTML .= "cell = document.getElementById('VA_".$state->row."');\n";
		$HTML .= "cell.innerHTML = fill;\n";
		$HTML .= "document.getElementById('selValue').selectedIndex=-1;\n";
	}

	return count($state->records);
}

private function value_select(&$state, &$HTML, $rec=-1) {

	if ($rec < 0) { //checking returned
		if (!isset($_GET["row"])) return;
		$rec = $_GET["row"]; //get row number
	}

	$this->value_list($state); //restore the record list
	if (!array_key_exists($rec, $state->records)) {
		throw_the_bum_out(NULL,"Evicted(".__LINE__."): PROP_SET invalid value id ".$rec,true);
	}
	$record = $state->records[$rec];
	$state->value_id = $rec;
	$state->msgStatus = "";
	$HTML .= "cell = document.getElementById('VA_".$state->row."');\n";
	$HTML .= "cell.innerHTML = '".$record."';\n";
	$HTML .= "cell.setAttribute('data-recid',".$rec.");\n";
}

//Send the enter/cancel buttons via server call-back:
private function button_send(&$state, &$HTML) {
	$HTML .= "//Buttons...\n";
	$HTML .= "cellID = 'BN_".$state->row."';\n";
	$HTML .= "cell = document.getElementById(cellID);\n";
	$HTML .= "cell.title = '';\n";
   	$HTML .= "document.getElementById('msgGreet_ID').innerHTML = 'Properties for ".$this->forwho.
			"<br>Select this property/value';\n";
	//onclick=onmousedown + onmouseup; if audit_count() caused by onblur of numbers issues confirm(),
	//onmouseup will not happen; in that case, mouseDown() will assure new_info() gets executed:
	$HTML .= "fill = \"<button type='button' onclick='changes(".$state->row.")' onmousedown='mouseDown(".$state->row.")'>";
	$HTML .= "Submit</button>";
	$HTML .= "<br><button type='button' name='btnReset' onclick='Reset()'>Cancel</button>";
	$HTML .= "\";\n";
	$HTML .= "cell.innerHTML = fill;\n";
}
//End CALL BACK SECTION

private function changes(&$state, &$response) {
	global $_DB;

	$response = "-"; //initialize to reset page

	//check that POSTed values = previously set in $this->state
	if (!isset($_POST["row"]) || $_POST["row"] != $state->row ||
		!isset($_POST["id"]) || $_POST["id"] != $state->record_id ||
		!isset($_POST["name"]) || $_POST["name"] != $state->prop_id ||
		!isset($_POST["value"]) || $_POST["value"] != $state->value_id) {
		throw_the_bum_out(NULL,"Evicted(".__LINE__."): PROP_SET invalid POST",true);
	}

	if ($state->row == 0) {
		$sql = "INSERT INTO ".$_DB->prefix."e04_prop_element
				(prop_value_idref, element_table, element_idref)
				VALUES(".$state->value_id.",'".$this->element."',".$this->element_id.");";
		$_DB->exec($sql);
		return;
	}

	if ($state->value_id == 0) {
		$sql = "DELETE FROM ".$_DB->prefix."e04_prop_element
				WHERE prop_element_id = ".$state->record_id.";";
		$_DB->exec($sql);
		return;
	}

	$sql = "UPDATE ".$_DB->prefix."e04_prop_element
			SET prop_value_idref = ".$state->value_id."
			WHERE prop_element_id = ".$state->record_id.";";
	$_DB->exec($sql);
	return;
}
} //end class
?>
