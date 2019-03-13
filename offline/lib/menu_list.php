<?php
//copyright 2015-2016,2019 C.D.Price. Licensed under Apache License, Version 2.0
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
	require_once "lib/preference_set.php";
	//	for organization:
	$prefs = new PREF_GET("a00",$_SESSION["organization_id"]);
	if ($new_list = $prefs->preference("staff")) { //allowable menu items
		if ($new_list[0] == "-") { //negatives - remove them from $MENU_LIST
			$old_list = $MENU_LIST;
			$MENU_LIST = array();
			foreach ($old_list as $value) {
				if (!in_array($value,$new_list)) $MENU_LIST[] = $value;
			}
		} else { //positives - the new $MENU_LIST
			$MENU_LIST = $new_list;
		}
	}
	if ($new_list = $prefs->preference("menu")) { //new menu tags
		foreach ($new_list as $key=>$value) $EX_staff[$key][MENU] = $value;
	}
	//	for person:
	$prefs = new PREF_GET("c10",$_SESSION["person_organization_id"]);
	if ($new_list = $prefs->preference("menu")) { //new menu tags
		foreach ($new_list as $key=>$value) $EX_staff[$key][MENU] = $value;
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
