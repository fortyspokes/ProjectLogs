<?php
//copyright 2016,2019 C.D.Price. Licensed under Apache License, Version 2.0
//See license text at http://www.apache.org/licenses/LICENSE-2.0

//Generalized processing to display/set/unset preferences.  Two processing classes:
//	PREF_GET - retrieves the specified preference for the specified element and user;
//	PREF_SET - processing to add/change/delete preferences

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

//Instructions for PREF_GET:
//Create the object then access a specific preference - easy, peasy.
class PREF_GET {
	private $prefs = array(); //all preferences for this element/user as an array

function __construct($element,$user) { //$element is table, $user is element_id
	global $_DB;

	$sql = "SELECT name, prefer, user_idref FROM ".$_DB->prefix."d10_preferences
			WHERE user_table='".$element."' AND (user_idref=".$user." OR user_idref<0)
			ORDER BY name, user_idref;";
	$stmt = $_DB->query($sql);
	while ($row = $stmt->fetchObject()) {
		if ($row->user_idref < 0) { //default
			$default = explode(":",$row->prefer); //separate out descriptor
			switch ($row->name) {
			case "expense":
				$prefer = array();
				$labels = explode("&",$default[1]);
				foreach ($labels as $label) {
					$value = explode("=",$label);
					$prefer[$value[0]] = $value[1];
				}
				break;
			case "date":
				$prefer = explode("&",$default[1]);
				break;
			default:
				$prefer = "";
			} //end switch

		} else { //preference
			switch ($row->name) {
			case "date":
				$prefer = explode("&",$row->prefer);
				break;
			case "label":
				$prefer = array();
				$labels = explode("&",$row->prefer);
				foreach ($labels as $label) {
					$value = explode("=",$label);
					$prefer[$value[0]] = explode("/",$value[1]); //singular and plural
				}
				break;
			case "menu":
				$prefer = array();
				$labels = explode("&",$row->prefer);
				foreach ($labels as $label) {
					$value = explode("=",$label);
					$prefer[$value[0]] = $value[1];
				}
				break;
			case "staff":
				$prefer = explode("&",$row->prefer);
				array_unshift($prefer,"+"); //assume a positive list
				if (substr($prefer[1],0,1) == "-") {
					$prefer[0] = "-";
					$prefer[1] = substr($prefer[1],1);
				}
				break;
			case "theme":
				$prefer = $row->prefer;
				break;
			case "expense":
				$prefer = array();
				$labels = explode("&",$row->prefer);
				foreach ($labels as $label) {
					$value = explode("=",$label);
					$prefer[$value[0]] = $value[1];
				}
				$labels = array_merge($this->prefs["expense"],$prefer); //merge with defaults
				$prefer = array();
				foreach ($labels as $key=>$label) {
					if (trim($label) != "-") { //"-" => remove it
						$prefer[$key] = $label;
					}
				}
				break;
			default:
				$prefer = $row->prefer;
			}//end switch
		}
		$this->prefs[$row->name] = $prefer;
	} //end while
	$stmt->closeCursor();
}

public function preference($name, $item=null) { //a preference can be an array; $item is an element of it
	if (array_key_exists($name, $this->prefs)) {
		if (is_null($item)) return $this->prefs[$name];
		if (is_array($this->prefs[$name]) && array_key_exists($item,$this->prefs[$name])) {
			return $this->prefs[$name][$item];
		}
	}
	return false;
}
} //end class PREF_GET

//Instructions for PREF_SET:
//The calling module must interface in the Main State Gate and in Page_out().

//Main State Gate
//Create a case, typically, "PREFERENCES" that must
// 1. Instantiate the object that creates a child state thread (="PREF_SET").  Then save our new
//    PREF_SET object in the _MAIN state;
// 2. Call the <PREF_SET>->state_gate() that will continue state processing on the child state;
// 3. If state_gate() returns 'false', the processing is done; the caller can then take appropriate action.

//Page_out()
// The PREF_SET scripts must be set;
// Call <PREF_SET>->get_page() that will echo the appropriate HTML then return a pointer to the
// child state object used by PREF_SET.  This state object must then be returned to the executive's
// EX_pageEnd(<state object>) function to implement gobacks.

//See org_edit.php for an example.

class PREF_SET {

	private $element; //the 3char table id
	private $element_id;
	private $category; //'cosmetic' or 'structural'
	private $forwho; //element desc for msgGreet
	private $HTML = "";
	private $records = array();
	private $pref_name;

	const START			= STATE::INIT + 1; //if 0, set_a_gate does not work
	const LIST			= self::START + 1;
	const SELECT		= self::START + 2;
	const CHANGE		= self::START + 3;

	const COSMETIC = -11;
	const STRUCTURAL = -1;
	const THREAD = 'PREF_SET';

//$state=>_MAIN state; $element=>the table prefix, $element_id=>$element's record, $category,
//$forwho=>just a string to be displayed in the header:
function __construct(&$state, $element, $element_id, $category, $forwho) {
	$this->element = $element;
	$this->element_id = $element_id;
	$this->category = $category;
	$this->forwho = $forwho;
	$state->scion_start(self::THREAD, self::START);
}

function __sleep() { //don't save this stuff - temporary and too long
	$this->HTML = "";
	$this->records = array();
   	return array_keys(get_object_vars($this));
}

function state_gate() {

	$state= STATE_pull(self::THREAD);

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
		$state->set_a_gate(self::SELECT);
		$this->pref_name = $_GET["who"];
		$this->get_recs();
		$state->status = self::CHANGE;
		echo $this->display_back(); //prefset.js puts this into a popup
		break 2;

	case self::CHANGE:
		if (isset($_GET["who"])) {
			$state->goback_to(self::SELECT, true);
			break 1;
		}
		echo $this->new_pref();
		break 2;

	default:
		throw_the_bum_out(NULL,"Evicted(".__LINE__."): PREF_SET invalid state=".$state->status);
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

function greeting() {
	return "Preferences for ".$this->forwho.
			"<br>To add or change: click on the preference name";
}

function set_script() {
	return array("call_server.js","prefset.js");
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

private function display_date() {
	return $this->display_textarea();
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

private function display_expense() {
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

function change_date() {
	$new = $_GET["what"];
	$this->update($new);
	return $new;
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

function change_expense() {
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

} //end class PREF_SET
?>
