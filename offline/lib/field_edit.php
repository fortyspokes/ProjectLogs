<?php
//copyright 2015,2017 C.D.Price. Licensed under Apache License, Version 2.0
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
}

class PSWD_Field extends FIELD { //password

	function __construct($ipagename, $idbname, $iload_from_DB, $iwrite_to_DB, $irequired, $imaxlength, $idisabled=false, $ivalue=NULL) {
		parent::__construct($ipagename, $idbname, $iload_from_DB, $iwrite_to_DB, $irequired, $imaxlength, $idisabled, $ivalue);
		$this->pswd = true;
	}
}

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
}

class DATE_FIELD extends FIELD {
	private $YYYY;
	private $MM;
	private $DD;

	function __construct($ipagename, $idbname, $iload_from_DB, $iwrite_to_DB, $irequired, $imaxlength, $idisabled=false, $ivalue="") {
		parent::__construct($ipagename, $idbname, $iload_from_DB, $iwrite_to_DB, $irequired, $imaxlength, $idisabled);

		$this->set_value($ivalue);
	}

	private function set_value($new) {
		if (is_object($new)) {
			$this->value = clone $new;
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

	function format($format) {
		return $this->value->format($format);
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
		if ($chkrecent && !$_PERMITS->can_pass(PERMITS::_SUPERUSER)) {
			$now = COM_NOW();
			if (($this->YYYY < ($now->format('Y')-1)) || ($this->YYYY > ($now->format('Y')+1))) {
				return "date must be recent";
			}
		}
		if (($this->MM < 1) || ($this->MM > 12)) {
			return "invalid month";
		}
		$m = array(0,31,28,31,30,31,30,31,31,30,31,30,31);
		if (($this->YYYY % 4) == 0) $m[2] = 29;
		if (($this->DD < 1) || ($this->DD > $m[intval($this->MM)])) {
			return "invalid day of month";
		}
		$this->value = new DateTime($this->YYYY."-".$this->MM."-".$this->DD);
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

	function HTML_input($length, $extra=NULL) {
		$HTML = "";
		$HTML .= "<span ID=\"".$this->pagename."_ID\""; if (!is_null($extra)) $HTML .= " ".$extra; $HTML .= ">\n";

		$HTML .= "<input type=\"text\" name=\"".$this->pagename."YYYY\" id=\"".$this->pagename."YYYY_ID\"";
		$HTML .= " maxlength=\"4\" size=\"4\" value=\"".$this->YYYY."\"";
		if ($this->disabled) $HTML .= " readonly";
		if (!is_null($extra)) $HTML .= " ".$extra;
		$HTML .= ">\n";

		$HTML .= "<input type=\"text\" name=\"".$this->pagename."MM\" id=\"".$this->pagename."MM_ID\"";
		$HTML .= " maxlength=\"2\" size=\"2\" value=\"".$this->MM."\"";
		if ($this->disabled) $HTML .= " readonly";
		if (!is_null($extra)) $HTML .= " ".$extra;
		$HTML .= ">\n";

		$HTML .= "<input type=\"text\" name=\"".$this->pagename."DD\" id=\"".$this->pagename."DD_ID\"";
		$HTML .= " maxlength=\"2\" size=\"2\" value=\"".$this->DD."\"";
		if ($this->disabled) $HTML .= " readonly";
		if (!is_null($extra)) $HTML .= " ".$extra;
		$HTML .= ">\n";

		$HTML .= "</span>";
		return $HTML;
	}
}

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
