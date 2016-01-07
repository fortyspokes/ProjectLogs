<?php
//copyright 2015-2016 C.D.Price. Licensed under Apache License, Version 2.0
//See license text at http://www.apache.org/licenses/LICENSE-2.0

	$sql = "SELECT COUNT(*) as org_count FROM ";
	if ($_PERMITS->can_pass(PERMITS::_SUPERUSER)) {
		$sql .= $_DB->prefix."a00_organization;";
	} else {
		$sql .= $_DB->prefix."c10_person_organization
				WHERE person_idref=".$_SESSION["person_id"].";";
	}
	$stmt = $_DB->query($sql);
	$org_count = $stmt->fetchObject()->org_count;
	$stmt->closeCursor();

	require_once "staff.php";

	//Set menu preferences:
	//	for organization:
	$sql = "SELECT menu FROM ".$_DB->prefix."d10_preferences
			WHERE organization_idref=".$_SESSION["organization_id"].";";
	$stmt = $_DB->query($sql);
	if ($row = $stmt->fetchObject()) {
		$menu_prefs = parse_ini_string(str_replace("&","\n",$row->menu));
		foreach ($menu_prefs as $key=>$value) $EX_staff[$key][MENU] = $value;
	}
	$stmt->closeCursor();
	//	for project:
	$sql = "SELECT COUNT(*) as count FROM ".$_DB->prefix."a10_project
			WHERE organization_idref=".$_SESSION["organization_id"].";";
	if ($_DB->query($sql)->fetchObject()->count == 1) { //only one project
		$sql = "SELECT d10.menu FROM ".$_DB->prefix."d10_preferences as d10
				INNER JOIN ".$_DB->prefix."a10_project AS a10 ON d10.project_idref = a10.project_ID
				WHERE a10.organization_idref=".$_SESSION["organization_id"].";";
		$stmt = $_DB->query($sql);
		if ($row = $stmt->fetchObject()) {
			$menu_prefs = parse_ini_string(str_replace("&","\n",$row->menu));
			foreach ($menu_prefs as $key=>$value) $EX_staff[$key][MENU] = $value;
		}
		$stmt->closeCursor();
	}

	$MENU_LIST = array(
		"TL", //Time Log Entry
		"PE", //Person Edit
		"EL", //Event Log Entry (Classes)
		"XL", //Expense Log Entry
		"TE", //Edit time Logs (for any person)
		"XE", //Edit expense logs
		"LP", //Download logs
		"TR", //Download Task Report
		"SR", //Set Rates
		"PJ", //Project Edit
		"TK", //Task Edit
		"ST", //Subtask Edit
		"AG", //Accounting group Edit
		"AC", //Account Edit
		"EV", //Event Edit
		"PM", //Grant/revoke permits
		"OE", //Org Edit
		"OS", //Org Select
		"CF", //Site Config
		"LC", //Logs Prune (Cut)
		);
	if ($_SESSION["_SITE_CONF"]["RUNLEVEL"] > 0) { //can't do this stuff in production
		$MENU_LIST = array_merge($MENU_LIST, array(
			"RC", //Refresh CSV
			"SC", //Save CSV
			"UP", //Upyear Timelog
			) );
	}
	foreach ($MENU_LIST as $item) {
		$staff = $EX_staff[$item];
		if (($staff[PERMIT] == "") || $_PERMITS->can_pass($staff[PERMIT])) {
			$add = true;
			$audit = "menu_list_".$item;
			if (function_exists($audit)) {
				$add = $audit($staff);
			}
			if ($add) {
				$menu[$staff[MENU]] = $item;
			}
		}
	}

	if ($_SESSION["_SITE_CONF"]["RUNLEVEL"] > 0) { //can't do this stuff in production
		if ($_PERMITS->can_pass(PERMITS::_SUPERUSER)) $menu["Experiment"] = "!/main/experiment.php?init=EX";
	}

	if ($_PERMITS->can_pass(PERMITS::_SUPERUSER)) {
		if (($_SESSION["person_id"] == 0) || ($_SESSION["_SITE_CONF"]["RUNLEVEL"] == 1))
				$menu['phpInfo'] = "PI";
	}

function menu_list_OS(&$staff) { //Check "OS"
	global $org_count;
	if ($org_count > 1) return true;
	return false;
}

?>
