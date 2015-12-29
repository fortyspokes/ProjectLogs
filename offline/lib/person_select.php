<?php
//copyright 2015 C.D.Price. Licensed under Apache License, Version 2.0
//See license text at http://www.apache.org/licenses/LICENSE-2.0

class PERSON_SELECT {

public $show_inactive = false;
public $show_new = false; //show 'add new record'
public $selected = false;
public $list_ID = "person_list_ID"; //ID of HTML element containing the list
public $noSleep = array(); //clear out these user created vars when sleeping (to save memory)
public $records = array();
private $select_list = array(-1);
private $person_id = 0;
private $inactives = 0;
private $restrict = array(0); //limit to these projects; $restrict[0] == 0 means get all
private $blacklist = false; //true => don't select those in $restrict list
private $multiple = false; //allow multiple select

const LASTNAME = 0;
const FIRSTNAME = 1;
const INACTIVE = 2;

function __construct($restrict_to = array(0), $multiple=false) {
	$this->restrict = $restrict_to;
	if ($this->restrict[0] < 0) { //negative array is a blacklist
		$this->blacklist = true;
		foreach ($this->restrict as $key=>$value) $this->restrict[$key] = abs($this->restrict[$key]);
	}
	$this->multiple = $multiple;
	$this->get_recs();
	if (count($this->records) == 1) {
//		$this->select_list = array(key($this->records));
		$key = each($this->records);
		$this->set_state($key[0]);
	}
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
	$this->get_recs();
}

private function get_recs() {
	global $_DB;

	$where = "";
	if ($this->restrict[0] != 0) {
		$not = "";
		if ($this->blacklist) $not = " NOT";
		$where = "AND (c00.person_id ".$not." IN (".implode(",", $this->restrict)."))";
	}
	$sql = "SELECT c00.*, c10.inactive_asof FROM ".$_DB->prefix."c00_person AS c00
			INNER JOIN ".$_DB->prefix."c10_person_organization AS c10
			ON (c00.person_id = c10.person_idref)
			WHERE (c10.organization_idref=".$_SESSION["organization_id"].")
			AND (c00.person_id > 0)
			".$where."
			ORDER BY c00.lastname;";
	$stmt = $_DB->query($sql);
	$today = COM_NOW();
	$this->inactives = 0;
	while ($row = $stmt->fetchObject()) {
		$element = array(
			$row->lastname,
			$row->firstname,
			'',
			);
		if (!is_null($row->inactive_asof)) {
			$inactive = new DateTime($row->inactive_asof);
			if ($inactive <= $today) {
				$element[self::INACTIVE] = $inactive->format('Y-m-d');
				++$this->inactives;
			}
		}
		$this->records[strval($row->person_id)] = $element;
	}
	$stmt->closeCursor();
	reset($this->records);
}

function setNoSleep($var) {
	$this->noSleep[] = $var;
}

public function __set($key, $value) { //set dynamic vars
	$this->$key = $value;
}

public function selected() { //return array of selected persons

	$selected = array();
	foreach ($this->select_list as $ID)
		$selected[$ID] = $this->records[$ID];
	return $selected;
}

function show_list() { //get the HTML for the list items (and inactive checkbox)
	$HTML = array();

	$size = count($this->records);
	if ($this->inactives > 0) {
		$checked = "";
		if ($this->show_inactive) {
			$checked = " checked";
		} else {
			$size -= $this->inactives;
		}
		$HTML[] = "  <div><input type='checkbox' name='chkInactive' value='show'".$checked.
					" onclick='return person_list()'>Show inactive records</div>";
		$HTML[] = "  <p>";
	}
	if ($this->multiple) {
		$HTML[] = "<button type='submit' name='btnSome' value='some' title='use Ctrl/Click to select multiple items'>Use the selected persons</button>";
		$HTML[] = "<button type='submit' name='btnAll' value='all'>Use ALL the persons</button>";
		$HTML[] = "<p>";
		$insert = " multiple";
		$title = "use Ctrl/Click to select multiple items";
	} else {
		$insert = " onclick='this.form.submit()'";
		$title = "Click to select";
	}
	if ($this->show_new) ++$size;
	$HTML[] = "  <select name='selPerson[]' size='".$size."'".$insert.">";
	if ($this->show_new)
		$HTML[] = "    <option value='-1' style='opacity:1.0'>--create a new person record--";
	if ($this->multiple) { $insert = " selected"; } else { $insert = ""; }
	foreach ($this->records as $key => $record) {
		$opacity = "1.0"; //opacity value = fully opaque
		$inact = "";
		if ($record[self::INACTIVE] != '') {
			if (!$this->show_inactive) continue;
			$opacity = "0.5";
			$inact = "; inactive as of ".$record[self::INACTIVE];
		}
		$HTML[] = "    <option value='".$key."' title='".$title.$inact."' style='opacity:".$opacity."'".$insert.">".
			$record[self::LASTNAME].", ".$record[self::FIRSTNAME];
		$insert = "";
	}
	$HTML[] = "  </select>";
	$HTML[] = "  </p>";
	return $HTML;
}

function refresh_list() { //re-display the list via Javascript
	ob_clean();
	$this->show_inactive = !$this->show_inactive;
	$list = $this->show_list();
	$HTML = "@var me = document.getElementById('".$this->list_ID."');\n";
	$HTML .= "var myHTML = '';\n";
	foreach ($list as $value) {
		$HTML .= "myHTML += \"".$value."\";\n";
	}	
	$HTML .= "me.innerHTML=myHTML;\n";
	return $HTML;
}

public function set_list() { //set up initial form and select
	$HTML = "";
	if ($this->inactives > 0) {
		$HTML .= "<script>\n";
		$HTML .= "function person_list() {\n";
		$HTML .= "  server_call('GET','refresh=persons');\n";
		$HTML .= "}\n";
		$HTML .= "</script>\n";
	}
	$HTML .= "<form method='post' name='frmAction' id='frmAction_ID' action='".$_SERVER['SCRIPT_NAME']."'>\n";
	$HTML .= "  <div id='".$this->list_ID."'>\n";
	$list = $this->show_list();
	foreach ($list as $line) $HTML .= $line."\n";
	$HTML .= "  </div>\n";
	$HTML .= "</form>\n";
	return $HTML;
}

public function set_state($ID=-1) {
	global $_DB, $_STATE;

	if ($ID > 0) { //either object construct sees only 1 rec or page has chosen another in list
		$this->selected = true;
		if (!array_key_exists($ID, $this->records)) {
			throw_the_bum_out(NULL,"Evicted(".__LINE__."): invalid person id ".$selected);
		}
		$this->person_id = $ID;
		if ($this->select_list[0] == -1) $this->select_list[0] = $ID;
	} elseif (!$this->selected) { //returned POST (or superduper user)
		if (isset($_POST["selPerson"]) || isset($_POST["btnAll"])) {
			if (isset($_POST["btnAll"])) {
				$this->select_list = array();
				foreach ($this->records as $key=>$value) {
					if (($value[self::INACTIVE] != '') && !$this->show_inactive) continue;
					$this->select_list[] = $key;
				}
			} else {
				$this->select_list = $_POST["selPerson"]; //$_POST[""selPerson"] is an array
			}
			$this->selected = true;
			if ($this->select_list[0] == -1) { //adding
				if ($this->multiple)
					$_STATE->person_ids = $this->select_list;
				$this->person_id = -1;
				$_STATE->person_id = $this->person_id;
				return;
			}
			$this->person_id = $this->select_list[0];
		} else { //it's the superduper user
			if ($_SESSION["person_id"] != 0) //or is it
				throw_the_bum_out(NULL,"Evicted(".__LINE__."): invalid person id ".$this->selected);
			if ($this->select_list[0] == -1) $this->select_list[0] = 0;
			$_STATE->person_ids = $this->select_list;
			$_STATE->person_id = 0;
			$_STATE->person_organization_id = 0;
			$sql = "SELECT lastname, firstname FROM ".$_DB->prefix."c00_person WHERE person_id=0;";
			$row = $_DB->query($sql)->fetchObject();
			$_STATE->person_name = $row->firstname." ".$row->lastname;
			return;
		}
		foreach ($this->select_list as $selected) {
			if (!array_key_exists($selected, $this->records)) {
				throw_the_bum_out(NULL,"Evicted(".__LINE__."): invalid person id ".$selected);
			}
		}
	}
	$_STATE->person_id = $this->person_id;
	$_STATE->person_ids = $this->select_list;
	$_STATE->person_name = $this->records[$this->person_id][1]." ".$this->records[$this->person_id][0];
	$sql = "SELECT person_organization_id FROM ".$_DB->prefix."c10_person_organization
			WHERE organization_idref=".$_SESSION["organization_id"]." AND person_idref=".$_STATE->person_id.";";
	$stmt = $_DB->query($sql);
	$row = $stmt->fetchObject();
	$_STATE->person_organization_id = $row->person_organization_id;
	$stmt->closeCursor();
}

} //end class

//inline code catches request for refresh of list:
if (isset($_GET["refresh"]) && ($_GET["refresh"] == "persons")) {
	$persons = unserialize($_STATE->person_select);
	echo $persons->refresh_list();
	$_STATE->person_select = serialize($persons);
	$_STATE->replace();
	exit; //don't go back thru executive, it will add junk to buffer
}

?>
