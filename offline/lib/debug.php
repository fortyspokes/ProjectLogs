<?php
//copyright 2019 C.D.Price. Licensed under Apache License, Version 2.0
//See license text at http://www.apache.org/licenses/LICENSE-2.0
//some stuff useful for debugging; typically loaded when runlevel = 1 (development)


function debug_area() { //display an area for debug messages
	$HTML = "<br><textarea id='msgDebug' cols='100' rows='1' ondblclick='this.rows=this.rows+10;'></textarea>\n";
	return $HTML;
}

function debug_session() { //display the session and state
	global $_STATE;

	$HTML = "<table cellpadding=2>\n";
	$HTML .= "<tr><td align='right' valign='top'>Session: </td><td align='left'>\n";
	foreach ($_SESSION as $key=>$value) $HTML .= $key.";"; $HTML .= "</td></tr>\n";
	$HTML .= "<tr><td align='right' valign='top'>Permits: </td>\n";
	$HTML .= "<td align='left'>\n";
	$HTML .= serialize($_SESSION["UserPermits"])."\n";
	$HTML .= "</td></tr>\n";
	$HTML .= "<tr><td colspan='2' align='left'>_STATE:</td></tr>\n";
	if (isset($_STATE)) {
		$value = serialize(clone($_STATE))."\n";
	} else {
		$value = "STATE not set...";
	}
	$HTML .= "<tr valign='top'><td></td><td align='left'>".$value."</td></tr>\n";
	foreach ($_SESSION["STATE"] as $name=>$thread) {
		$HTML .= "<tr><td colspan='2' align='left'>_SESSION[STATE][".$name."]:</td></tr>\n";
		foreach ($thread as $key=>$value) {
			$HTML .= ("<tr valign='top'><td align='right'>".$key."</td><td align='left'>".$value."</td></tr>\n");
		}
	}
	$HTML .= "</table>\n";
	return $HTML;
}
?>
