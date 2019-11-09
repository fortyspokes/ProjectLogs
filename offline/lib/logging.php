<?php
//copyright 2015-2016,2019 C.D.Price. Licensed under Apache License, Version 2.0
//See license text at http://www.apache.org/licenses/LICENSE-2.0

function entry_audit() {
	global $_DB, $_STATE, $_PERMITS;

	$_STATE->fields["txtName"] = COM_input_edit("txtName",32);
	$_STATE->fields["txtPswd"] = $_POST["txtPswd"];
//	Note: "txtPswd" does not need input_edit since it is never used in SQL nor is it displayed in
//	HTML; and it should NOT be subjected to input_edit since that function limits the chars used.

	$sql = "SELECT c00.*, c10.*, a00.organization_id, a00.timezone
			FROM ".$_DB->prefix."c00_person AS c00
			LEFT OUTER JOIN ".$_DB->prefix."c10_person_organization AS c10
			ON (c00.person_id = c10.person_idref)
			LEFT OUTER JOIN ".$_DB->prefix."a00_organization AS a00
			ON (c10.organization_idref = a00.organization_id)
			WHERE c00.loginname=:user;";
	$stmt = $_DB->prepare($sql);
	$stmt->bindValue(':user', $_STATE->fields["txtName"], PDO::PARAM_STR);
	$stmt->execute();

	$_STATE->msgStatus = "Invalid login";
	if(!($row = $stmt->fetchObject())) {
		$_STATE->msgStatus .= " x";
		return false; //nobody there
	}
	//only super-duper user has no organization (even other superusers must have one)
	if ((is_null($row->person_idref)) && ($row->person_id != 0)) {
		$_STATE->msgStatus .= " 0";
		return false;
	}

	if (!password_verify($_STATE->fields["txtPswd"], $row->password)) {
		$_STATE->msgStatus .= " -";
		return false;
	}

	$_SESSION["person_id"] = $row->person_id;
	$_SESSION["user"] = $_STATE->fields["txtName"];
	if (is_null($row->organization_idref)) { //should be the super-duper user
		$_SESSION["person_organization_id"] = 0;
		$_SESSION["organization_id"] = 1; //better be a record there
		$stmt->closeCursor();
		$sql = "SELECT timezone FROM ".$_DB->prefix."a00_organization WHERE organization_id=1;";
		$stmt = $_DB->query($sql);
		$row = $stmt->fetchObject();
	} else {
		$today = new DateTime(); //can't do TZO offset until org set - may be a few hours off
		while (1 == 1) {
			if (new DateTime($row->inactive_asof) >= $today) break;
			if(!($row = $stmt->fetchObject())) {
				$_STATE->msgStatus .= " +";
				return false;
			}
		}
		$_SESSION["person_organization_id"] = $row->person_organization_id;
		$_SESSION["organization_id"] = $row->organization_id;
	}
	$_SESSION["org_TZO"] = $row->timezone;
	$stmt->closeCursor();

	//Set preferences for organization/person:
	require_once "lib/preference_set.php";
	$prefs = new PREF_GET("a00",$_SESSION["organization_id"]);
	if ($pref = $prefs->preference("theme")) $_SESSION["THEME"] = $pref;
	$_STATE->dateform = $prefs->preference("date");
	$prefs = new PREF_GET("c10",$_SESSION["person_organization_id"]);
	if ($pref = $prefs->preference("theme")) $_SESSION["THEME"] = $pref;
	$_STATE->msgStatus = "";

	$_SESSION["UserPermits"] = $_PERMITS->get_permits($_SESSION["person_id"]); //set the users's permissions
	$_SESSION["UserPermits"]["_LEGAL_"] = TRUE; //can now pass the 'logged in' gate
	error_log("Login: by ".$_STATE->fields["txtName"]."; id=".$_SESSION["person_id"]); //not an error but the best place to put it
	return true;
}
