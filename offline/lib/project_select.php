<?php
//copyright 2015,2016,2018,2019 C.D.Price. Licensed under Apache License, Version 2.0
//See license text at http://www.apache.org/licenses/LICENSE-2.0

class PROJECT_SELECT {

public $show_inactive = false;
public $show_new = false; //show 'add new record'
public $selected = false;
public $list_ID = "project_list_ID"; //ID of HTML element containing the list
public $label = array("project","projects"); //might be replaced by preferences
public $noSleep = array(); //clear out these user created vars when sleeping (to save memory)
private $records = array();
private $select_list = array(-1);
private $project_id = 0;
private $inactives = 0;
private $restrict = array(0); //limit to these projects; $restrict[0] == 0 means get all
private $blacklist = false; //true => don't select those in $restrict list
private $multiple = false; //allow multiple select

const NAME = 0;
const DESCRIPTION = 1;
const INACTIVE = 2;
const LABELS = 3;

function __construct($restrict_to = array(0), $multiple=false) {
	global $_DB;
	$this->restrict = $restrict_to;
	if ((count($restrict_to) > 0) && ($this->restrict[0] < 0)) { //negative array is a blacklist
		$this->blacklist = true;
		foreach ($this->restrict as $key=>$value) $this->restrict[$key] = abs($this->restrict[$key]);
	}
	$this->multiple = $multiple;
	$this->get_recs();
	if (count($this->records) == 1) {
		$this->select_list = array(key($this->records));
		$this->set_state(key($this->records));
	}
	//Get the "label" -> "project" preference:
	$sql = "SELECT prefer FROM ".$_DB->prefix."d10_preferences
			WHERE user_table = 'a00' AND name = 'label'
				and user_idref=".$_SESSION["organization_id"].";";
	$stmt = $_DB->query($sql);
	if ($row = $stmt->fetchObject()) {
		$labels = explode("&",$row->prefer);
		foreach ($labels as $label) {
			$replace = explode("=",$label);
			if ($replace[0] == "project") {
				$this->label = explode("/",$replace[1]);
				break;
			}
		}
	}
	$stmt->closeCursor();
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

	if (count($this->restrict) == 0) return;

	$where = "";
	if ($this->restrict[0] != 0) {
		$not = "";
		if ($this->blacklist) $not = " NOT";
		$where = "AND (a10.project_id ".$not." IN (".implode(",", $this->restrict)."))";
	}
	$sql = "SELECT a10.*, d10.prefer FROM
			".$_DB->prefix."a10_project AS a10
			LEFT OUTER JOIN
			(SELECT d10.user_idref, d10.prefer FROM ".$_DB->prefix."d10_preferences AS d10
				WHERE d10.user_table = 'a10' and d10.name = 'label') AS d10
			ON a10.project_id = d10.user_idref
			WHERE (a10.organization_idref=".$_SESSION["organization_id"].")
			".$where."
			ORDER BY a10.timestamp;";
	$stmt = $_DB->query($sql);
	$today = COM_NOW();
	$this->inactives = 0;
	while ($row = $stmt->fetchObject()) {
		$element = array(
			$row->name,
			$row->description,
			'',
			array(),
			);
		if (!is_null($row->inactive_asof)) {
			$inactive = new DateTime($row->inactive_asof);
			if ($inactive <= $today) {
				$element[self::INACTIVE] = $inactive->format('Y-m-d');
				++$this->inactives;
			}
		}
		if (!is_null($row->prefer)) {
			$labels = explode("&",$row->prefer);
			foreach ($labels as $label) {
				$replace = explode("=",$label);
				$element[self::LABELS][$replace[0]] = explode("/",$replace[1]);
			}
		}
		$this->records[strval($row->project_id)] = $element;
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

public function show_list() { //get the HTML for the list items (and inactive checkbox)
	$HTML = array();

	if (count($this->restrict) == 0) {
		$HTML[] = "No ".$this->label[1]." available";
		return $HTML;
	}

	$size = count($this->records);
	if ($this->inactives > 0) {
		$checked = "";
		if ($this->show_inactive) {
			$checked = " checked";
		} else {
			$size -= $this->inactives;
		}
		$HTML[] = "  <div><input type='checkbox' name='chkInactive' value='show'".$checked.
					" onclick='project_list();'>Show inactive records</div>";
		$HTML[] = "  <p>";
	}
	if ($this->multiple) {
		$HTML[] = "<button type='submit' name='btnSome' value='some' title='use Ctrl/Click to select multiple items'>Use the selected ".$this->label[0]."</button>";
		$HTML[] = "<button type='submit' name='btnAll' value='all'>Use ALL the ".$this->label[1]."</button>";
		$HTML[] = "<p>";
		$insert = " multiple";
		$title = "use Ctrl/Click to select multiple items";
	} else {
		$insert = " onclick='this.form.submit()'";
		$title = "Click to select";
	}
	if ($this->show_new) ++$size;
	$HTML[] = "  <select name='selProject[]' size='".$size."'".$insert.">";
	if ($this->show_new)
		$HTML[] = "    <option value='-1' style='opacity:1.0'>--create a new project record--";
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
			$record[0].": ".$record[1];
		$insert = "";
	}
	$HTML[] = "  </select>";
	$HTML[] = "  </p>";
	return $HTML;
}

public function refresh_list() { //re-display the list via Javascript
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

	if (count($this->restrict) == 0) {
		$HTML .= "No ".$this->label[1]." available";
		return $HTML;
	}

	if ($this->inactives > 0) {
		$HTML .= "<script>\n";
		$HTML .= "function project_list() {\n";
		$HTML .= "  server_call('GET','refresh=projects');\n";
		$HTML .= "}\n";
		$HTML .= "</script>\n";
	}
	$HTML .= "<form method='post' name='frmAction' id='frmAction_ID' action='".$_SESSION["IAm"]."'>\n";
	$HTML .= "  <div id='".$this->list_ID."'>\n";
	$list = $this->show_list();
	foreach ($list as $line) $HTML .= $line."\n";
	$HTML .= "  </div>\n";
	$HTML .= "</form>\n";
	return $HTML;
}

public function tabs() {
	global $_STATE;

	$HTML = "";
	$select_head = "<div class='pagehead' style='font-size:12pt'>";
	$HTML .= "<br><ul id='tabs' class='tabs'>\n";
	foreach ($this->select_list as $ID) {
		$record = $this->records[$ID];
		$name = substr($record[self::NAME].": ".$record[1],0,25);
		if ($ID == $this->project_id) {
			$select_head .= $record[self::NAME].": ".$record[self::DESCRIPTION]." (close date=".
							$_STATE->close_date->format("Y-m-d").")";
			if (count($this->select_list) > 1) {
				$HTML .= "<li style='background-color:#fff;'>".$name;
				$HTML .= "</li>\n";
			}
		} else {
			$URL_delim = (!strpos($_SESSION["IAm"],"?"))?"?":"&";
			$HTML .= "<li style='opacity:.5;'><a href='".$_SESSION["IAm"].$URL_delim."sheet=".$ID."'>\n";
			$HTML .= $name."</a>";
			$HTML .= "</li>\n";
		}
	}
	$HTML .= "</ul>\n";
	$HTML .= $select_head."</div>\n";
	return $HTML;
}

public function selected_name() {
	return $this->records[$this->project_id][self::NAME].": ".$this->records[$this->project_id][self::DESCRIPTION];
}

public function get_label($label, $plural=false) { //get the label from preferences if it exists
	if ($label == "project") { //we also store the org's label for "project"
		if ($plural) {
			return $this->label[1];
		} else {
			return $this->label[0];
		}
	}
	if (array_key_exists($label, $this->records[$this->project_id][self::LABELS])) {
		if ($plural) {
			return $this->records[$this->project_id][self::LABELS][$label][1];
		} else {
			return $this->records[$this->project_id][self::LABELS][$label][0];
		}
	} else {
		return $label;
	}
}

public function set_state($ID=-1) {
	global $_DB, $_STATE;

	if ($ID > 0) { //either object construct sees only 1 rec or page has chosen another in list
		$this->selected = true;
		if (!array_key_exists($ID, $this->records)) {
			throw_the_bum_out(NULL,"Evicted(".__LINE__."): invalid project id ".$selected);
		}
		$this->project_id = $ID;
		if ($this->select_list[0] == -1) $this->select_list[0] = $ID;
	} elseif (!$this->selected) { //returned POST
		if (isset($_POST["selProject"]) || isset($_POST["btnAll"])) {
			if (isset($_POST["btnAll"])) {
				$this->select_list = array();
				foreach ($this->records as $key=>$value) {
					if (($value[self::INACTIVE] != '') && !$this->show_inactive) continue;
					$this->select_list[] = $key;
				}
			} else {
				$this->select_list = $_POST["selProject"]; //$_POST[""selProject"] is an array
			}
			$this->selected = true;
			if ($this->select_list[0] == -1) { //adding
				if ($this->multiple)
					$_STATE->project_ids = $this->select_list;
				$this->project_id = -1;
				$_STATE->project_id = $this->project_id;
				return;
			}
			$this->project_id = $this->select_list[0];
		}
		foreach ($this->select_list as $selected) {
			if (!array_key_exists($selected, $this->records)) {
				throw_the_bum_out(NULL,"Evicted(".__LINE__."): invalid project id ".$selected);
			}
		}
	}
	$_STATE->project_id = $this->project_id;
	$_STATE->project_ids = $this->select_list;
	$sql = "SELECT a10.close_date, a20.accounting_id, a20.name AS accounting
		FROM ".$_DB->prefix."a10_project AS a10
		LEFT OUTER JOIN ".$_DB->prefix."a20_accounting AS a20
		ON a10.accounting_idref = a20.accounting_id
		WHERE project_id=".$_STATE->project_id.";";
	$stmt = $_DB->query($sql);
	$row = $stmt->fetchObject();
	$_STATE->close_date = new DateTime($row->close_date);
	$_STATE->accounting_id = $row->accounting_id;
	$_STATE->accounting = $row->accounting;
	$stmt->closeCursor();
}

} //end class

//inline code catches request for refresh of list:
if (isset($_GET["refresh"]) && ($_GET["refresh"] == "projects")) {
	$projects = unserialize($_STATE->project_select);
	echo $projects->refresh_list();
	$_STATE->project_select = serialize($projects);
	$_STATE->replace();
	exit; //don't go back thru executive, it will add junk to buffer
}

?>

