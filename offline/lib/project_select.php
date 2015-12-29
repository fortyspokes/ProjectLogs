<?php
//copyright 2015 C.D.Price. Licensed under Apache License, Version 2.0
//See license text at http://www.apache.org/licenses/LICENSE-2.0

class PROJECT_SELECT {

public $show_inactive = false;
public $show_new = false; //show 'add new record'
public $selected = false;
public $list_ID = "project_list_ID"; //ID of HTML element containing the list
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

function __construct($restrict_to = array(0), $multiple=false) {
	$this->restrict = $restrict_to;
	if ($this->restrict[0] < 0) { //negative array is a blacklist
		$this->blacklist = true;
		foreach ($this->restrict as $key=>$value) $this->restrict[$key] = abs($this->restrict[$key]);
	}
	$this->multiple = $multiple;
	$this->get_recs();
	if (count($this->records) == 1) {
		$this->select_list = array(key($this->records));
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
		$where = "AND (a10.project_id ".$not." IN (".implode(",", $this->restrict)."))";
	}
	$sql = "SELECT a10.* FROM ".$_DB->prefix."a10_project AS a10
			WHERE (organization_idref=".$_SESSION["organization_id"].")
			".$where."
			ORDER BY timestamp;";
	$stmt = $_DB->query($sql);
	$today = COM_NOW();
	$this->inactives = 0;
	while ($row = $stmt->fetchObject()) {
		$element = array(
			$row->name,
			$row->description,
			'',
			);
		if (!is_null($row->inactive_asof)) {
			$inactive = new DateTime($row->inactive_asof);
			if ($inactive <= $today) {
				$element[self::INACTIVE] = $inactive->format('Y-m-d');
				++$this->inactives;
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
		$HTML[] = "<button type='submit' name='btnSome' value='some' title='use Ctrl/Click to select multiple items'>Use the selected projects</button>";
		$HTML[] = "<button type='submit' name='btnAll' value='all'>Use ALL the projects</button>";
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
	if ($this->inactives > 0) {
		$HTML .= "<script>\n";
		$HTML .= "function project_list() {\n";
		$HTML .= "  server_call('GET','refresh=projects');\n";
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
			$HTML .= "<li style='opacity:.5;'><a href='".$_SERVER['SCRIPT_NAME']."?sheet=".$ID."'>\n";
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

