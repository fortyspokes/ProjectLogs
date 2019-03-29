<?php
//copyright 2015,2017,2019 C.D.Price. Licensed under Apache License, Version 2.0
//See license text at http://www.apache.org/licenses/LICENSE-2.0
//Some general field edit/update stuff:
class FIELD {
	public $pagename;
	public $dbname;
	public $value;
	public $load_from_DB;
	public $write_to_DB;
	public $required;
	public $disabled;
	public $maxlength;
	public $pswd = false;

	function __construct($ipagename, $idbname, $iload_from_DB, $iwrite_to_DB, $irequired, $imaxlength, $idisabled=false, $ivalue=NULL) {
		$this->pagename = $ipagename;
		$this->dbname = $idbname;
		$this->value = $ivalue;
		$this->load_from_DB = $iload_from_DB;
		$this->write_to_DB = $iwrite_to_DB;
		$this->required = $irequired;
		$this->disabled = $idisabled;
		$this->maxlength = $imaxlength;
	}

	function value() {
		$old = $this->value;
		if (func_num_args() > 0) $this->value = func_get_arg(0);
		return $old;
	}

	function audit() {
		if (isset($_POST[$this->pagename])) {
			$this->value = COM_input_edit($this->pagename,$this->maxlength);
		} else {
			$this->value = NULL;
		}
		if ($this->required) {
			if ((is_null($this->value)) || ($this->value == "")) {
				return "entry required";
			}
		}
		return true;
	}

	function HTML_label($label) {
		$HTML = "";
		$HTML .= "<label for=\"".$this->pagename."_ID\" class='";
		if($this->required) {
			$HTML .= "required'>*";
		} else {
			$HTML .= "label'>";
		}
		$HTML .= $label."</label>";
		return $HTML;
	}

	function HTML_input($length, $extra=NULL) {
		$HTML = "";
		if (is_null($this->value)) {
			$value = "";
		} else {
			$value = $this->value;
		}
		if ($this->pswd) $type='password'; else $type='text';
		$HTML .= "<input type=\"".$type."\"";
		$HTML .= " name=\"".$this->pagename."\" id=\"".$this->pagename."_ID\"";
		$HTML .= " maxlength=\"".$this->maxlength."\" size=\"".$length."\"";
		$HTML .= " value=\"".COM_output_edit($value)."\"";
		if ($this->disabled) $HTML .= " readonly";
		if (!is_null($extra)) $HTML .= " ".$extra;
		$HTML .= ">";
		return $HTML;
	}
} //end class FIELD

class PSWD_Field extends FIELD { //password

	function __construct($ipagename, $idbname, $iload_from_DB, $iwrite_to_DB, $irequired, $imaxlength, $idisabled=false, $ivalue=NULL) {
		parent::__construct($ipagename, $idbname, $iload_from_DB, $iwrite_to_DB, $irequired, $imaxlength, $idisabled, $ivalue);
		$this->pswd = true;
	}
} //end class PSWD_FIELD

class AREA_FIELD extends FIELD { //for textarea fields

	function __construct($ipagename, $idbname, $iload_from_DB, $iwrite_to_DB, $irequired, $imaxlength, $idisabled=false, $ivalue="") {
		parent::__construct($ipagename, $idbname, $iload_from_DB, $iwrite_to_DB, $irequired, $imaxlength, $idisabled, $ivalue);
	}

	function HTML_input($length, $extra=NULL) {
		$HTML = "";
		$rows = $this->maxlength / $length;
		if (is_null($this->value)) {
			$value = "";
		} else {
			$value = $this->value;
		}
		$HTML .= "<textarea";
		$HTML .= " name=\"".$this->pagename."\" id=\"".$this->pagename."_ID\"";
		$HTML .= " rows=\"".$rows."\" cols=\"".$length."\"";
		if ($this->disabled) $HTML .= " readonly";
		if (!is_null($extra)) $HTML .= " ".$extra;
		$HTML .= ">";
		$HTML .= COM_output_edit($value);
		$HTML .= "</textarea>";
		return $HTML;
	}
} //end class AREA_FIELD

class DATE_FIELD extends FIELD {
	private $YYYY;
	private $MM;
	private $DD;

function __construct($ipagename, $idbname="", $iload_from_DB=false, $iwrite_to_DB=false, $irequired=false, $imaxlength=-1, $idisabled=false, $ivalue="") {
	if ($imaxlength < 0) {  //using object only for formatting the date, ie. only first parm present
		$this->set_value($ipagename);
	} else {
		parent::__construct($ipagename, $idbname, $iload_from_DB, $iwrite_to_DB, $irequired, $imaxlength, $idisabled);
		$this->set_value($ivalue);
	}
}

function __clone() {
	if (is_null($this->value)) return;
	$this->value = clone($this->value);
}

private function set_value($new) {
	if (is_null($new)) {
		$this->value = NULL;
	} elseif (is_object($new)) {
		if (get_class($new) == "DATE_FIELD") {
			$this->value = clone $new->value;
		} else {
			$this->value = clone $new;
		}
	} elseif ($new == "now") {
		$this->value = COM_NOW();
	} elseif ($new == "") {
		$this->value = NULL;
	} else {
		$this->value = new DateTime($new);
	}
	if (is_null($this->value)) {
		$this->YYYY = "";
		$this->MM = "";
		$this->DD = "";
	} else {
		$this->YYYY = $this->value->format("Y");
		$this->MM = $this->value->format("m");
		$this->DD = $this->value->format("d");
	}
}

function format($format="") {
	if (is_null($this->value)) return "";
	if ($format != "") return $this->value->format($format);
	if (isset($_STATE->dateform)) return $this->value->format($_STATE->dateform[1]);
	return $this->value->format($_SESSION["dateform"][1]);
}

function audit($chkrecent=true) {
	global $_PERMITS;

	if (isset($_POST[$this->pagename."YYYY"])) $this->YYYY = COM_input_edit($this->pagename."YYYY",4);
	if (isset($_POST[$this->pagename."MM"])) $this->MM = COM_input_edit($this->pagename."MM",2);
	if (isset($_POST[$this->pagename."DD"])) $this->DD = COM_input_edit($this->pagename."DD",2);
	if ($this->YYYY.$this->MM.$this->DD == "") {
		if ($this->required) {
			return "entry required";
		}
		$this->value = NULL;
		return true;
	}
	if (($this->YYYY == "") || ($this->MM == "") || ($this->DD == "")) {
		return "incomplete date";
	}
	if (!is_numeric($this->YYYY) || !is_numeric($this->MM) || !is_numeric($this->DD)) {
		return "dates must be all numeric";
	}
	if (($this->MM < 1) || ($this->MM > 12)) {
		return "invalid month";
	}
	$m = array(0,31,28,31,30,31,30,31,31,30,31,30,31);
	if (($this->YYYY % 4) == 0) $m[2] = 29;
	if (($this->DD < 1) || ($this->DD > $m[intval($this->MM)])) {
		return "invalid day of month";
	}
	$value = new DateTime($this->YYYY."-".$this->MM."-".$this->DD);
	if ($chkrecent && !$_PERMITS->can_pass(PERMITS::_SUPERUSER)) {
		$now = COM_NOW();
		$gap = $_SESSION["dateform"][2]; //days before date considered suspicious
		if (isset($_STATE->dateform)) $gap = $_STATE->dateform[2];
		$diff = $now->diff($value,true)->days;
		if ($diff > $gap) {
			return "date must be recent".", allowed days gap =".$gap.", this gap =".$diff;
		}
	}
	$this->value = $value;
	return true;
}

function value() {
	if (is_null($this->value)) {
		$old = "";
	} else {
		$old = $this->value->format("Y-m-d");
	}
	if (func_num_args() > 0) {
		$this->set_value(func_get_arg(0));
	}
	return $old;
}

function HTML_label($label) {
	$HTML = "";
	$HTML .= "<label for=\"".$this->pagename."YYYY_ID\" class='";
	if($this->required) {
		$HTML .= "required'>*";
	} else {
		$HTML .= "label'>";
	}
	$HTML .= $label."</label>";
	return $HTML;
}

function HTML_input($length=0, $extra=NULL) {
	global $_STATE;

	$form = $_SESSION["dateform"][1]; //'Y-m-d' or 'm-d-Y'
	if (isset($_STATE->dateform)) $form = $_STATE->dateform[1];
	$form = substr($form, 0, 1);

	$HTML = array();

	$line = "<span ID='".$this->pagename."_ID'"; if (!is_null($extra)) $line .= " ".$extra; $line .= ">";
	$HTML[] = $line;

	for ($ndx = 1; $ndx <= 3; $ndx++) {
		if ((($ndx==1) && ($form=="Y")) || (($ndx==3) && ($form=="m"))) {
			$line = "<input type='text' name='".$this->pagename."YYYY' id='".$this->pagename."YYYY_ID'";
			$line .= " maxlength='4' size='4' value='".$this->YYYY."'";
			$line .= " placeholder='YYYY' title='YYYY'";
			if ($this->disabled) $line .= " readonly";
			if (!is_null($extra)) $line .= " ".$extra;
			$line .= ">";
			$HTML[] = $line;
		}
		if ((($ndx==1) && ($form=="m")) || (($ndx==2) && ($form=="Y"))) {
			$line = "<input type='text' name='".$this->pagename."MM' id='".$this->pagename."MM_ID'";
			$line .= " maxlength='2' size='2' value='".$this->MM."'";
			$line .= " placeholder='MM' title='MM'";
			if ($this->disabled) $line .= " readonly";
			if (!is_null($extra)) $line .= " ".$extra;
			$line .= ">";
			$HTML[] = $line;
		}
		if ((($ndx==3) && ($form=="Y")) || (($ndx==2) && ($form=="m"))) {
			$line = "<input type='text' name='".$this->pagename."DD' id='".$this->pagename."DD_ID'";
			$line .= " maxlength='2' size='2' value='".$this->DD."'";
			$line .= " placeholder='DD' title='DD'";
			if ($this->disabled) $line .= " readonly";
			if (!is_null($extra)) $line .= " ".$extra;
			$line .= ">";
			$HTML[] = $line;
		}
	}

	$line = "</span>";
	$HTML[] = $line;

	return $HTML;
}
} //end class DATE_FIELD

define('FIELD_ADD', 1);
define('FIELD_UPDATE', 2);
function FIELD_edit_buttons($process) {
	if ($process == FIELD_ADD) {
		$value = "add";
		$text = "Submit the new fields";
	} else {
		$value = "update";
		$text = "Submit changes";
	}
	$buttons = "<button type='submit' name='btnSubmit' id='btnSubmit_ID' value='".$value."'>".$text."</button>\n";
	$buttons .= "<button type='submit' name='btnReset' id='btnReset_ID' value='reset'>Reset fields</button><br>\n";
	return $buttons;
}
?>
