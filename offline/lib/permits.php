<?php
//copyright 2015-2016 C.D.Price. Licensed under Apache License, Version 2.0
//See license text at http://www.apache.org/licenses/LICENSE-2.0

class PERMITS {

	const _SUPERUSER = "_*_";
	const _TEMP_PERMIT = "_LEGAL_"; //a temp permission for the "are you logged in" gate (in prepend)

	const GR_SYS = 1;	//permit grade levels - powers of 10
	const GR_ORG = 10;
	const GR_PRJ = 100;

function __construct() {
}

function __sleep() { //don't save this stuff - temporary and too long
}

function __wakeup() {
}

public function __set($key, $value) { //set dynamic vars
	$this->$key = $value;
}

public function can_pass($permit) {
	if (in_array($permit,array_keys($_SESSION["UserPermits"]))) return true;

	global $_TEMP_PERMIT; //used by publicly viewable pages (eg. login) to make the user temporarily "legal"
	if ($_TEMP_PERMIT == $permit) return true;

	if (in_array(PERMITS::_SUPERUSER,array_keys($_SESSION["UserPermits"]))) return true;

	return false;
}

public function restrict($permit) { //get the array of restricted grade ids

	if (in_array($permit,array_keys($_SESSION["UserPermits"])))
		return $_SESSION["UserPermits"][$permit][1];
	if (in_array(PERMITS::_SUPERUSER,array_keys($_SESSION["UserPermits"])))
		return array(0); //usually means all ids are available

	//This shouldn't happen because this user doesn't have a permit to get here in the first place;
	//will probably generate an error:
	return false;

}

public function get_permits($person_id) {
	global $_DB;

	//Get the person's permissions for this organization:
	//For each permit, save the permit id and all ids allowed for the grade (org, project)
	$user_permits = array();
	if ($person_id == 0) { //super duper user doesn't join to permits table
		$user_permits[PERMITS::_SUPERUSER] = array(0, array(0));
	} else {
		$id = -1; //create 1st time break
		$sql = "SELECT c20.organization_idref, c20.project_idref, d01.permit_id, d01.name, d01.grade
				FROM ".$_DB->prefix."c20_person_permit AS c20
				INNER JOIN ".$_DB->prefix."d01_permit AS d01
				ON (c20.permit_idref = d01.permit_id)
				WHERE c20.person_idref=".$person_id."
				AND (d01.grade=".PERMITS::GR_SYS." OR c20.organization_idref=".$_SESSION["organization_id"].")
				ORDER BY permit_id, organization_idref, project_idref";
		$stmt = $_DB->query($sql);
		while ($row = $stmt->fetchObject()) {
			if ($row->permit_id != $id) {
				if ($id != -1) { //not first time thru, save and start new array
					$user_permits[$name] = array($id,$grade_ids);
				}
				$id = $row->permit_id;
				$name = $row->name;
				$grade_ids = array();
			}
			switch ($row->grade) {
				case PERMITS::GR_SYS: $grade_ids[] = 0; break;
				case PERMITS::GR_ORG: $grade_ids[] = $row->organization_idref; break;
				case PERMITS::GR_PRJ: $grade_ids[] = $row->project_idref;
			}
		}
		if ($id != -1){ //save last permit
			$user_permits[$name] = array($id,$grade_ids);
		}
		$stmt->closeCursor();
	}

	return $user_permits;
}

} //end class

?>
