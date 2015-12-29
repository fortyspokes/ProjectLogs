<?php
//copyright 2015 C.D.Price. Licensed under Apache License, Version 2.0
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

	//Set theme for organization/person:
	$sql = "SELECT theme FROM ".$_DB->prefix."d10_preferences
			WHERE organization_idref=".$_SESSION["organization_id"].";";
	$stmt = $_DB->query($sql);
	if (($row = $stmt->fetchObject()) && ($row->theme != "")) $_SESSION["_SITE_CONF"]["THEME"] = $row->theme;
	$stmt->closeCursor();
	$sql = "SELECT theme FROM ".$_DB->prefix."d10_preferences
			WHERE person_idref=".$_SESSION["person_id"].";";
	$stmt = $_DB->query($sql);
	if (($row = $stmt->fetchObject()) && ($row->theme != "")) $_SESSION["_SITE_CONF"]["THEME"] = $row->theme;
	$stmt->closeCursor();

	$_STATE->msgStatus = "";

	$_SESSION["UserPermits"] = $_PERMITS->get_permits($_SESSION["person_id"]); //set the users's permissions
	$_SESSION["UserPermits"]["_LEGAL_"] = TRUE; //can now pass the 'logged in' gate

	error_log("Login: by ".$_STATE->fields["txtName"]."; id=".$_SESSION["person_id"]); //not an error but the best place to put it
	return true;
}

