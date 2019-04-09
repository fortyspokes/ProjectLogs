<?php
//copyright 2015,2019 C.D.Price. Licensed under Apache License, Version 2.0
//See license text at http://www.apache.org/licenses/LICENSE-2.0
//some stuff that has wide applicability...

//Here, we will do entity de-code (the opposite of htmlentities()), allow digits (think addresses),
//trim spaces, and, optionally, truncate to length (see the system doc for "Input Integrity"):
function COM_string_decode($string,$length=-1) {
	$value = trim(preg_replace('/[^\w\d\.\,\-\& ]/', '', html_entity_decode($string)));
	if ($length > 0) {
		$value = substr($value,0,$length);
	}
	return $value;
}
function COM_input_edit($fldname,$length=-1) {
	return COM_string_decode($_POST[$fldname],$length);
}

function COM_output_edit($field,$length=-1) {
	if ($length > 0) {
		$value = substr($value,0,$length);
	}
	$value = htmlentities($field);
	return $value;
}

function COM_NOW() { //adjust server time zone to org's TZO
	$now = new DateTime();
	$offset = $_SESSION["org_TZO"] - $_SESSION["_SITE_CONF"]["TZO"]; //offset from server to organization
	if ($offset == 0) return $now;
	$invert = 0;
	if ($offset < 0) {
		$offset = abs($offset);
		$invert = 1;
	}
	$format = "PT".$offset."H";
	$interval = new DateInterval($format);
	$interval->invert = $invert;
	$now->add($interval);
	return $now;
}

function COM_weekday($line, $days=array("Sun","Mon","Tue","Wed","Thu","Fri","Sat")) {
	global $_STATE;

	$start = $_STATE->dateform[0];
	$days = array_merge($days, $days);
	$days = array_slice($days, $start, 7); //now have array of names starting with week start
	$ndx = 0;
	$out = "";
	while (1==1) {
		if ($ndx = strpos($line, "<wd")) { //look for <wdn> (n is a digit)
			$day = substr($line, $ndx + 3, 1);
			$out .= substr($line, 0, $ndx).$days[$day];
			$line = substr($line, $ndx + 5);
		} else {
			break;
		}
	}
	$out .= $line;

	return $out;

}

function COM_sleep($who) { //called by obects when sleeping
	$vars = get_object_vars($who);
	$date = array();
	foreach ($vars as $name=>$value) {
		if (is_a($value, "DateTime")) {
			$date[$name] = $value->format("Y-m-d");
			$who->{$name} = $date[$name];
		}
	}
	return array("DateTime"=>$date);
}

function COM_wakeup($who, $where="sleepers") { //called by objects when waking
	foreach ($who->{$where} as $class=>$objects) {
		foreach ($objects as $name=>$value) {
			$who->{$name} = new $class($value);
		}
	}
}

function COM_JSdate(&$date) { //build a javascript date create string
	//note: the "intval" removes the leading zero which JS interprets as octal number;
	//	"-1" needed because JS expects month rel to 0 (but not day) and same as "intval")
	return $date->format("Y").",".($date->format("m")-1).",".intval($date->format("d"));
}
?>
