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

	require_once "staff.php"; //sets $MENU_LIST array

	//Set menu preferences:
	//	for organization:
	$sql = "SELECT prefer FROM ".$_DB->prefix."d10_preferences
			WHERE user_idref=".$_SESSION["organization_id"]."
			AND user_table='a00' AND name='staff';";
	if ($row = $_DB->query($sql)->fetchObject()) {
		$new_list = explode("&",$row->prefer);
		if (substr($new_list[0],0,1) == "-") { //negatives
			$new_list[0] = substr($new_list[0],1); //strip the "-"
			$old_list = $MENU_LIST;
			$MENU_LIST = array();
			foreach ($old_list as $value) {
				if (!in_array($value,$new_list)) $MENU_LIST[] = $value;
			}
		} else { //positives
			$MENU_LIST = $new_list;
		}
	}
	$sql = "SELECT prefer FROM ".$_DB->prefix."d10_preferences
			WHERE user_idref=".$_SESSION["organization_id"]."
			AND user_table='a00' AND name='menu';";
	if ($row = $_DB->query($sql)->fetchObject()) {
		$new_labels = parse_ini_string(str_replace("&","\n",$row->prefer));
		foreach ($new_labels as $key=>$value) $EX_staff[$key][MENU] = $value;
	}
	//	for project:
	$sql = "SELECT COUNT(*) as count FROM ".$_DB->prefix."a10_project
			WHERE organization_idref=".$_SESSION["organization_id"].";";
	if ($_DB->query($sql)->fetchObject()->count == 1) { //only one project
		$sql = "SELECT d10.prefer FROM ".$_DB->prefix."d10_preferences as d10
				INNER JOIN ".$_DB->prefix."a10_project AS a10 ON d10.user_idref = a10.project_ID
				WHERE a10.organization_idref=".$_SESSION["organization_id"]."
				AND d10.user_table='a1' AND d10.name='menu';";
		if ($row = $_DB->query($sql)->fetchObject()) {
			$menu_prefs = parse_ini_string(str_replace("&","\n",$row->prefer));
			foreach ($menu_prefs as $key=>$value) $EX_staff[$key][MENU] = $value;
		}
	}
	//	for person:
	$sql = "SELECT prefer FROM ".$_DB->prefix."d10_preferences
			WHERE user_idref=".$_SESSION["person_organization_id"]."
			AND user_table='c10' AND name='menu';";
	if ($row = $_DB->query($sql)->fetchObject()) {
		$new_labels = parse_ini_string(str_replace("&","\n",$row->prefer));
		foreach ($new_labels as $key=>$value) $EX_staff[$key][MENU] = $value;
	}
	//...now, add the sys admin, etc. stuff:
	$MENU_LIST = array_merge($MENU_LIST, $ADMIN_LIST);

	//Create the actual menu after checking permits & audits:
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
?>
