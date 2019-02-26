<?php
//copyright 2016,2019 C.D.Price. Licensed under Apache License, Version 2.0
//See license text at http://www.apache.org/licenses/LICENSE-2.0

//Generalized processing to display/set/unset preferences.

//Instructions:
//The calling module's processing state (define 'PREFERENCES'?) must:
//	include this module
//	create and save an instance of the PREF_SET class on first time thru
//	call $PREF_SETobject->state_gate()
//	when state_gate() returns false, a Goback was initiated: take appropriate action (loopback?)
//	before leaving the state, save the $PREF_SETobject
//Before calling $EX_pageStart() and if processing state set:
//	get scripts from $PREF_SETobject->set_script();,
//	get msgGreet from $PREF_SETobject->greeting();.
//When creating HTML at processing state:
//	$PREF_SETobject->set_HTML();
//See org_edit.php for an example.

class PREFERENCE {
	public $name;
	public $value; //as assigned
	public $type; //HTML element
	public $default_value;

function __construct($name,$type,$default) {
	$this->name = $name;
	$this->type = $type;
	$this->value = $default;
	$this->default_value = $default; 
}

} //end class

class PREF_SET {

	public $element; //the 3char table id
	public $element_id;
	public $category; //'cosmetic' or 'structural'
	public $forwho; //element desc for msgGreet

	public $pref_name;
	private $status;
	public $noSleep = array(); //clear out these user created vars when sleeping (to save memory)
	private $records = array();

	const COSMETIC = -11;
	const STRUCTURAL = -1;

	const PREF_INIT =	0;
	const PREF_DISP	=	1;
	const PREF_CHANGE =	2;

function __construct(&$state, $element, $element_id, $category, $forwho) {

	$state->PREFSETgoback = "n"; //must pass state_gate test first time thru

	$this->element = $element;
	$this->element_id = $element_id;
	$this->category = $category;
	$this->forwho = $forwho;
	$this->status = PREF_SET::PREF_INIT;
	$this->setNoSleep("records");
}

function __sleep() { //don't save this stuff - temporary and too long
	foreach ($this->noSleep as $temp) {
		if (is_array($this->{$temp})) {
			$this->{$temp} = array();
		} else {
			$this->{$temp} = false;
		}
	}
   	return array_keys(get_object_vars($this));
}

function __wakeup() {
	$this->get_recs();
}

public function __set($key, $value) { //set dynamic vars
	$this->$key = $value;
}

function goback() {
	return true;
}

function greeting() {
	return "Preferences for ".$this->forwho.
			"<br>To add or change: click on the preference name";
}

function set_script() {
	return array("call_server.js","prefset.js");
}

function set_HTML() {
	echo $this->set_list();
}

private function get_recs() {
	global $_DB;

	$sql = "SELECT name, prefer FROM ".$_DB->prefix."d10_preferences
			WHERE user_table='".$this->element."'
			AND user_idref<=".$this->category.";"; //the 'templates'
	$stmt = $_DB->query($sql);
	$this->records = array();
	while ($row = $stmt->fetchObject()) {
		$prefer = explode(":",$row->prefer);
		$this->records[$row->name] = new PREFERENCE($row->name,$prefer[0],$prefer[1]);
	}
	$stmt->closeCursor();
	//overlay the templates' values with any assigned values:
	$sql = "SELECT name, prefer FROM ".$_DB->prefix."d10_preferences
			WHERE user_table='".$this->element."' AND user_idref=".$this->element_id.";";
	$stmt = $_DB->query($sql);
	while ($row = $stmt->fetchObject()) {
		if (isset($this->records[$row->name])) { //not there if only doing cosmetic?
			$this->records[$row->name]->value = $row->prefer;
		}
	}
	$stmt->closeCursor();
}

function setNoSleep($var) {
	$this->noSleep[] = $var;
}

public function show_list() { //get the HTML for the list items
	global $_VERSION;

	$HTML = array();
	$HTML[] = "<div id='divPop_ID' class='popopen'>";
	$HTML[] = "\t<div id=replacePop>";
	$HTML[] = "\t\tHellol, world!";
	$HTML[] = "\t</div><br>";
	$HTML[] = "\t<input type='button' onclick='close_pop(true)' value='Submit'>";
	$HTML[] = "\t<input type='button' id='cancelPop' onclick='close_pop(false)' value='Cancel'>";
	$HTML[] = "</div>";
	$HTML[] = "<table align='center' id='tblLog' cellpadding='4' border='2'>";
	$HTML[] = "\t<tr>";
	$HTML[] = "\t\t<th width='140'>Name</th>";
	$HTML[] = "\t\t<th width='240'>Value</th>";
	$HTML[] = "\t</tr>";
	$row = 1;
	foreach ($this->records as $name =>$prefer) {
		$HTML[] = "\t<tr>";
		$HTML[] = "\t\t<td id='NM_".$row."' onclick='show_pop(\"VA_".$row."\",\"".$name."\")'>".$name."</td>";
		$HTML[] = "\t\t<td id='VA_".$row."'>".$prefer->value."</td>";
		$HTML[] = "\t</tr>";
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

function state_gate(&$state) {

	if ($state->PREFSETgoback == "y") return false; //all done here

	//State Gate: (the while (1==1) allows a loop back through the switch using a 'break 1')
	while (1==1) { switch ($this->status) {
	case PREF_SET::PREF_INIT:
		//All client interaction is via server_call which bypasses state maintenance in executive.php,
		//hence, we must handle the state (fortunately, it's very simple):
		$state->PREFSETgoback = "y";
		$state->replace();
		//put a new SSO on the state stack; we assume that we got here thru normal executive.php
		//channels (ie. not via server_call) which will put yet another SSO on the stack; to get back
		//to the SSO with PREFSETgoback="y", must now backup 2 entries:
		$state->PREFSETgoback = "n";
		$state->backup = -2; //minus => goback 2 entries (positive => goback to status)
		$state->push();
		$state = STATE_pull();

		$this->get_recs();
		$this->status = PREF_SET::PREF_DISP;
		break 2;
	case PREF_SET::PREF_DISP:
		$this->pref_name = $_GET["who"];
		$this->status = PREF_SET::PREF_CHANGE;
		echo $this->display_back();
		break 2;
	case PREF_SET::PREF_CHANGE:
		if (isset($_GET["who"])) {
			$this->status = PREF_SET::PREF_DISP;
			break 1;
		}
		echo $this->new_pref();
		break 2;
	default:
		throw_the_bum_out(NULL,"Evicted(".__LINE__."): PREF_SET error");
	} } //while & switch
	//End Main State Gate

	return true;
}

private function display_back() {
	$displayer = "display_".$this->pref_name;
	return $this->{$displayer}();
}

private function display_textarea() {

	$display = "\nfill = \"Enter the new expression:<br>\"\n";
	$display .= "fill += \"<textarea name='txtText' id='txtText_ID' rows='2' cols='50'>\"\n";
	$display .= "fill += \"".$this->records[$this->pref_name]->value."\"\n";
	$display .= "fill += \"</textarea>\"\n";
	$display .= "where = \"where=document.getElementById('txtText_ID').value\"\n";
	return $display;
}

private function display_staff() {
	return $this->display_textarea();
}

private function display_label() {
	return $this->display_textarea();
}

private function display_menu() {
	return $this->display_textarea();
}

private function display_theme() {

	//append the default value to the beginning of the themes list:
	$themes = array_merge(array(".."=>$this->records["theme"]->default_value),$this->get_themes());

	$ours = $this->records["theme"]->value;
	$display = "\nfill = \"Select the new value:<br>\"\n";
	$display .= "fill += \"<select name='selSelect' id='selSelect_ID' size=".count($themes).">\"\n";
	foreach ($themes as $key=>$value) {
		$display .= "fill += \"<option value='".$key."'";
		if ($value == $ours) $display .= " selected";
		$display .= ">".$value."</option>\"\n";
	}
	$display .= "fill += \"</select>\"\n";
	$display .= "where = \"where=document.getElementById('selSelect_ID').selectedIndex\"\n";
	return $display;
}

private function get_themes() {

	$themes = array();
	//Look for the css directory in the includes then list it to get available 'themes':
	foreach($_SESSION["_SITE_CONF"]["_INCLUDE"] as $dir) {
		$list = opendir($dir);
		while (($name = readdir($list)) !== false) {
			if ($name == "css") {
				$dir .= "/css";
				closedir($list);
				break 2;
			}
		}
		closedir($list);
	}
	$list = opendir($dir);
	while (($name = readdir($list)) !== false) {
		if (($name == "..") || ($name == ".")) continue;
		$themes[$name] = $name;
	}
	closedir($list);
	return $themes;
}

function new_pref() {
	$changer = "change_".$this->pref_name;
	return $this->{$changer}();
}

function change_staff() {
	$new = $_GET["what"];
	$this->update($new);
	return $new;
}

function change_label() {
	$new = $_GET["what"];
	$this->update($new);
	return $new;
}

function change_menu() {
	$new = $_GET["what"];
	$this->update($new);
	return $new;
}

function change_theme() {
	$new = $_GET["what"];
	if ($new == 0) {
		$this->update("");
		return $this->records["theme"]->default_value;
	}
	$themes = array_values($this->get_themes());
	$new = $themes[$new - 1];
	$this->update($new);
	return $new;
}

function update($pref) {
	global $_DB;

	$preferences_id = 0;
	$sql = "SELECT preferences_id FROM ".$_DB->prefix."d10_preferences
			WHERE user_table='".$this->element."' AND name='".$this->pref_name."'
			AND user_idref=".$this->element_id.";";
	if ($row = $_DB->query($sql)->fetchObject()) {
		$preferences_id = $row->preferences_id;
	}
	if ($preferences_id == 0) {
		if ($pref != "") {
			$sql = "INSERT INTO ".$_DB->prefix."d10_preferences (user_table,user_idref,name,prefer)
			VALUES ('".$this->element."',".$this->element_id.",'".$this->pref_name."','".$pref."');";
			$_DB->exec($sql);
		}
	} else {
		if ($pref == "") {
			$sql = "DELETE FROM ".$_DB->prefix."d10_preferences
					WHERE preferences_id=".$preferences_id.";";
			$_DB->exec($sql);
		} else {
			$sql = "UPDATE ".$_DB->prefix."d10_preferences SET prefer='".$pref."'
					WHERE preferences_id=".$preferences_id.";";
			$_DB->exec($sql);

		}
	}
}

} //end class
?>
