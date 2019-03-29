<?php
//copyright 2015,2019 C.D.Price. Licensed under Apache License, Version 2.0
//See license text at http://www.apache.org/licenses/LICENSE-2.0

class DATE_SELECT {

	//Captions:
	public $aCap = ""; //all after	'a'
	public $bCap = ""; //all before	'b'
	public $mCap = ""; //month		'm'
	public $pCap = ""; //period		'p' (from/to)
	public $sCap = ""; //single		's'
	public $wCap = ""; //week		'w'
	public $sleepers; //truncate some objects, eg. DateTime
	public $from;
	public $to;
	public $checked; //which range (radio button)
	private $ranges;
	private $uses; //0=neither,1=to,2=from,3=both, ie. binary OR

	const FROM = 2; //binary 0010
	const TO = 1;   //binary 0001
	const BOTH = 3; //binary 0011

function __construct($list, $checked="") {
	global $_STATE;

	$this->ranges = array();
	$this->uses = 0;
	$ranges = array("a"=>2,"b"=>1,"m"=>3,"p"=>3,"s"=>2,"w"=>3);
	for ($i=0; $i < strlen($list); $i++) {
		$range = substr($list,$i,1);
		$this->uses = ($ranges[$range] | $this->uses);
		$this->ranges[] = $range; //save in order received!
	}
	if ($checked == "") $checked = $this->ranges[0];
	$this->checked = $checked;
	$NOW = COM_NOW();
	$yyyy = $NOW->format('Y');
	$mm = $NOW->format('m');
	$dd = $NOW->format('d');
	switch ($this->checked) {		//create From date
	case "a": //from = 1st of last month
	case "m":
		$this->from = new DateTime($yyyy."-".$mm."-01"); //first day of this month
		$this->from->sub(new DateInterval('P1M')); //P=period, 1=number, M=months
		break;
	case "b": //from = genesis
		$this->from = new DateTime('2000-01-01');
		break;
	case "p": //from = 1st (Mon) of last week
	case "w":
		if (isset($_STATE->dateform)) {
			$dateform = $_STATE->dateform;
		} else {
			$dateform = $_SESSION["dateform"];
		}
		$weekday = getdate($NOW->getTimestamp())["wday"]; //Sun=0
		$this->from = clone($NOW);
		if ($weekday < $dateform[0]) {
			$sub = 14 - ($dateform[0] - $weekday);
		} else {
			$sub = 7 + ($weekday - $dateform[0]);
		}
		$this->from->sub(new DateInterval("P".$sub."D")); //P=period, ?=number, D=days
		break;
	case "s": //from = now
		$this->from = clone($NOW);
	}
	switch ($this->checked) {		//create To date
	case "b": //to = last of last month
	case "m":
		$this->to = new DateTime($yyyy."-".$mm."-01"); //first day of this month
		$this->to->sub(new DateInterval('P1D')); //P=period, 1=number, D=days
		break;
	case "p": //to = last of last week
	case "w":
		$this->to = clone($this->from);
		$this->to->add(new DateInterval('P6D')); //P=period, 6=number, D=days
		break;
	case "a": //to = null
	case "s":
		$this->to = clone($NOW);
	}
}

function __sleep() {
	$this->sleepers = COM_sleep($this);
	return array_keys(get_object_vars($this));
}

function __wakeup() {
	COM_wakeup($this);
}

function HTML() {

	$HTML = "";
	foreach ($this->ranges as $range) {
		$HTML .= "  <tr>\n";
		$HTML .= "    <td style='text-align:right'><input type='radio' name='radRange' value='".$range."'";
		if ($range == $this->checked) $HTML .= " checked"; $HTML .= "></td>\n";
		$HTML .= "    <td colspan='2' style='text-align:left'> ";
		$window = "Data window: ";
		switch ($range) {
		case "a": //all after
			if ($this->aCap == "") { $window .= "on or after the From date"; }
			else { $window = $this->aCap; }
			break;
		case "b": //all before
			if ($this->bCap == "") { $window .= "on or before the To date"; }
			else { $window = $this->bCap; }
			break;
		case "m": //month
			if ($this->mCap == "") { $window .= "the month including the From date"; }
			else { $window = $this->mCap; }
			break;
		case "p": //period
			if ($this->pCap == "") { $window .= "between From and To dates (inclusive)"; }
			else { $window = $this->pCap; }
			break;
		case "s": //single date
			if ($this->sCap == "") { $window = "Use the From date"; }
			else { $window = $this->sCap; }
			break;
		case "w": //week
			if ($this->wCap == "") {
				$window .= COM_weekday("the week (<wd0> to <wd6>) including the From date");
			}
			else { $window = $this->wCap; }
			break;
		default:
			$window .="ERROR: invalid range id=".$range;
		}
		$HTML .= $window."</td>\n";
		$HTML .= "  </tr>\n";
	}

	if ($this->uses & DATE_SELECT::FROM) { //From date
							//pagename,DBname,load from DB?,write to DB?,required?,maxlength,disabled,value
		$from = new DATE_FIELD("txtFrom","",FALSE,FALSE,FALSE,0,FALSE,$this->from);
		$HTML .= "  <tr>\n";
		$HTML .= "    <td class='label'>".$from->HTML_label("From Date: ")."</td>\n";
		$HTML .= "    <td style='text-align:left'>\n";
		foreach ($from->HTML_input() as $line) {
			$HTML .= "      ".$line."\n";
		}
		$HTML .= "    </td>\n";
		$HTML .= "    <td>&nbsp</td>\n";
		$HTML .= "  </tr>\n";
	}
	if ($this->uses & DATE_SELECT::TO) { //To date
		$to = new DATE_FIELD("txtTo","",FALSE,FALSE,FALSE,0,FALSE,$this->to);
		$HTML .= "  <tr>\n";
		$HTML .= "    <td class='label'>".$to->HTML_label("To Date: ")."</td>\n";
		$HTML .= "    <td style='text-align:left'>\n";
		foreach ($to->HTML_input() as $line) {
			$HTML .= "      ".$line."\n";
		}
		$HTML .= "    </td>\n";
		$HTML .= "    <td>&nbsp</td>\n";
		$HTML .= "  </tr>\n";
	}

	return $HTML;
}

function POST($chkrecent=3) { //1=to,2=from,3=logical OR
	global $_STATE;

	if (!isset($_POST["radRange"])) { return false; } //can happen on a goback


	if ($this->uses & DATE_SELECT::FROM) { //From date
							//pagename,DBname,load from DB?,write to DB?,required?,maxlength,disabled,value
		$from = new DATE_FIELD("txtFrom","",FALSE,FALSE,FALSE,0,FALSE,$this->from);
		$chk = $chkrecent & DATE_SELECT::FROM;
		if (($msg = $from->audit($chk)) !== true) {
			$_STATE->msgStatus = "From Date error: ".$msg;
			return false;
		}
	}
	if ($this->uses & DATE_SELECT::TO) { //To date
		$to = new DATE_FIELD("txtTo","",FALSE,FALSE,FALSE,0,FALSE,$this->to);
		$chk = $chkrecent & DATE_SELECT::TO;
		if (($msg = $to->audit($chk)) !== true) {
			$_STATE->msgStatus = "To Date error: ".$msg;
			return false;
		}
	}

	$this->checked = $_POST["radRange"];
	switch ($this->checked) {
	case "a":
		break;
	case "b":
		break;
	case "m":
		$from->value->modify("first day of this month");
		$to->value = clone $from->value;
		$to->value->modify("last day of this month");
		break;
	case "p":
		break;
	case "s":
		break;
	case "w":
//		if (($from->format("w") != 0) && ($from->format("N") != 1)) {
//			$from->value->modify("last monday");
			if (isset($_STATE->dateform)) {
				$dateform = $_STATE->dateform;
			} else {
				$dateform = $_SESSION["dateform"];
			}
			$weekday = getdate($from->value->getTimestamp())["wday"]; //Sun=0
			if ($weekday < $dateform[0]) {
				$sub = 7 - ($dateform[0] - $weekday);
			} else {
				$sub = ($weekday - $dateform[0]);
			}
			$from->value->sub(new DateInterval("P".$sub."D")); //P=period, ?=number, D=days
//		}
		$to->value = clone $from->value;
		$to->value->add(new DateInterval('P6D')); //P=period, 6=number, D=days
	}

	if (($this->uses & 3) && ($from->value > $to->value)) {
		$_STATE->msgStatus = "Error: From Date must be less than To Date";
		return false;
	}

	if ($this->uses & DATE_SELECT::TO) {
		$now = COM_NOW();
		$diff = date_diff($to->value, $now, true);
		if ($diff->m > 2) {
			if ($_STATE->msgStatus != "") $_STATE->msgStatus .= "<br>";
			$_STATE->msgStatus .= "These dates are suspect - proceeding anyway";
		}
	}

	$this->from = clone $from->value;
	$this->to = clone $to->value;

	return true;
}
}//end class
?>
