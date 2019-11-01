<?php
//copyright 2015-2016,2019 C.D.Price. Licensed under Apache License, Version 2.0
//See license text at http://www.apache.org/licenses/LICENSE-2.0
if (!$_PERMITS->can_pass("assign_permits")) throw_the_bum_out(NULL,"Evicted(".__LINE__."): no permit");

//The Main State Gate cases:
define('LIST_PERSONS',		STATE::INIT);
define('SELECT_PERSON',			LIST_PERSONS + 1);
define('SELECTED_PERSON',		LIST_PERSONS + 2);
define('LIST_PROJECTS',		STATE::INIT + 10);
define('SELECT_PROJECT',		LIST_PROJECTS + 1);
define('SELECTED_PROJECT',		LIST_PROJECTS + 2);
define('LIST_PERMITS',		STATE::INIT + 20);
define('UPDATE_PERMIT',			LIST_PERMITS + 1);

class A_PERMIT {
	public $grade;		//d01.grade (1=system, 10=org, 100=proj)
	public $ID;			//d01.permit_id
	public $name;		//d01.name
	public $desc;		//d01.description
	public $person_permit = 0; //c20.person_permit_id
	public $assigned = false; //true: existing assignment
	public $checked = false; //true: checked on page
	public $disabled = false;

	function __construct($ID, $name, $desc, $grade) {
		$this->ID = $ID;
		$this->name = $name;
		$this->desc = $desc;
		$this->grade = $grade;
	}
}

//Main State Gate: (the while (1==1) allows a loop back through the switch using a 'break 1')
while (1==1) { switch ($_STATE->status) {
case LIST_PERSONS:
	$_STATE->person_id = 0;
	$_STATE->backup = LIST_PERSONS; //set 'goback'
	require_once "lib/person_select.php";
	$persons = new PERSON_SELECT(array(-$_SESSION["person_id"])); //blacklist: user can't change own permits
	if ($persons->selected) {
		$persons->set_state();
		$_STATE->init = LIST_PROJECTS;
		$_STATE->status = SELECTED_PERSON;
		break 1; //re-switch to SELECTED_PERSON
	}
	$_STATE->person_select = serialize(clone($persons));
	$_STATE->msgGreet = "Select a person to assign permissions";
	Page_out();
	$_STATE->status = SELECT_PERSON;
	break 2; //return to executive

case SELECT_PERSON:
	require_once "lib/person_select.php"; //catches $_GET list refresh
	$persons = unserialize($_STATE->person_select);
	$persons->set_state();
	$_STATE->status = SELECTED_PERSON;
	$_STATE->person_select = serialize($persons);
case SELECTED_PERSON:
	$_STATE->status = LIST_PROJECTS; //our new starting point for goback
	$_STATE->replace(); //so loopback() can find it
case LIST_PROJECTS:
	require_once "lib/project_select.php";
	$projects = new PROJECT_SELECT();
	$_STATE->project_select = serialize(clone($projects));
	if ($projects->selected) {
		$_STATE->init = LIST_PERMITS;
		$_STATE->status = SELECTED_PROJECT;
		break 1; //re-switch to SELECTED_PROJECT
	}
	$_STATE->msgGreet = "Select the ".ucfirst($projects->get_label("project"));
	$_STATE->backup = LIST_PERSONS;
	Page_out();
	$_STATE->status = SELECT_PROJECT;
	break 2; //return to executive

case SELECT_PROJECT:
	require_once "lib/project_select.php"; //catches $_GET list refresh
	$projects = unserialize($_STATE->project_select);
	$projects->set_state();
	$_STATE->project_select = serialize(clone($projects));
case SELECTED_PROJECT:
	$_STATE->project_name = $projects->selected_name();

	$_STATE->status = LIST_PERMITS; //our new starting point for goback
	$_STATE->replace(); //so loopback() can find it
case LIST_PERMITS:
	$_STATE->msgGreet = $_STATE->project_name."<br>Assign (check) or Un-assign permissions for <br>".
						$_STATE->person_name;
	permit_list($_PERMITS);
	$_STATE->backup = LIST_PROJECTS; //set goback
	Page_out();
	$_STATE->status = UPDATE_PERMIT;
	break 2; //return to executive

case UPDATE_PERMIT:
	if (entry_audit($_PERMITS)) {
		$_STATE->msgGreet = "New permissions for ".$_STATE->person_name;
		$_STATE = $_STATE->loopback(LIST_PERMITS);
		break 1; //re-switch
	}
	Page_out();
	break 2; //return to executive

default:
	throw_the_bum_out(NULL,"Evicted(".__LINE__."): invalid state=".$_STATE->status);
} } //while & switch
//End Main State Gate & return to executive

function permit_list(&$permits) {
	global $_DB, $_STATE;

	//Organization, person, and project have been selected and are unique
	$grades = array(PERMITS::GR_SYS=>"",
					PERMITS::GR_ORG=>" AND c20.organization_idref=".$_SESSION["organization_id"],
					PERMITS::GR_PRJ=>" AND c20.project_idref=".$_STATE->project_id);
	foreach ($grades as $grade=>$grade_idref) {

	//This subquery limits assignable permits to those of USER (NOT selected person - superuser gets 'em all):
	$subsqluser = "SELECT d01.permit_id, d01.name, d01.description, d01.grade
				FROM ".$_DB->prefix."d01_permit AS d01";
	$where = " WHERE d01.grade=".$grade;
	if (!$permits->can_pass(PERMITS::_SUPERUSER)) {
		$subsqluser .= " INNER JOIN ".$_DB->prefix."c20_person_permit AS c20
					ON (d01.permit_id = c20.permit_idref)";
		$where .= " AND c20.person_idref=".$_SESSION["person_id"].$grade_idref;
	}
	$subsqluser .= $where;
	//This subquery limits the outer joined permits to those previously assigned to the TARGET person:
	$sqltarget = "SELECT c20.person_permit_id, c20.permit_idref, d01.grade
				FROM ".$_DB->prefix."d01_permit AS d01
				INNER JOIN ".$_DB->prefix."c20_person_permit AS c20
				ON (d01.permit_id = c20.permit_idref)
				WHERE d01.grade=".$grade." AND c20.person_idref=".$_STATE->person_id.$grade_idref;
	$sql = "SELECT U.permit_id, U.name, U.description, U.grade, T.person_permit_id
			FROM (".$subsqluser.") AS U
			LEFT OUTER JOIN (".$sqltarget.") AS T ON (U.permit_id = T.permit_idref)
			ORDER BY U.grade, U.name;";
	$stmt = $_DB->query($sql);
	while ($row = $stmt->fetchObject()) {
		$permit = new A_PERMIT($row->permit_id, $row->name, $row->description, $row->grade);
		if (!is_null($row->person_permit_id)) {
			$permit->person_permit = $row->person_permit_id;
			$permit->assigned = TRUE;
		}
		$_STATE->fields[strval($row->permit_id)] = $permit;
	}
	$stmt->closeCursor();

	} //end foreach

}

function entry_audit(&$permits) {
	global $_DB, $_STATE;

	permit_list($permits); //the allowable permits

	if (isset($_POST["chkPermit"])) {
		foreach ($_POST["chkPermit"] as $ID => $value) {
			if (!array_key_exists($ID, $_STATE->fields)) {
				throw_the_bum_out(NULL,"Evicted(".__LINE__."): invalid permit id ".$_POST["chkPermit"]);
			}
			if ($value == "on") {
				$_STATE->fields[strval($ID)]->checked = TRUE;
			}
		}
	}

	foreach ($_STATE->fields as $ID=>&$permit) {
		$permit->disabled = true;
		$sqlinsert = "INSERT INTO ".$_DB->prefix."c20_person_permit (person_idref, permit_idref";
		$sqlvalues = "VALUES (".$_STATE->person_id.", ".$ID;
		if (!$permit->assigned && $permit->checked) { //add permit
			switch ($permit->grade) {
			case PERMITS::GR_PRJ:
				$sqlinsert .= ", project_idref";
				$sqlvalues .= ", ".$_STATE->project_id;
				//fall thru to also set org
			case PERMITS::GR_ORG:
				$sqlinsert .= ", organization_idref";
				$sqlvalues .= ", ".$_SESSION["organization_id"];
			//case PERMITS::GR_SYS doesn't set org or project idrefs
			}
			$sqlinsert .= ") ";
			$sqlvalues .= ");";
			$_DB->exec($sqlinsert.$sqlvalues);
			$permit->assigned = true;
		} else if ($permit->assigned && !$permit->checked) { //delete permit
			$sql = "DELETE FROM ".$_DB->prefix."c20_person_permit
					WHERE person_permit_id=".$permit->person_permit.";";
			$_DB->exec($sql);
			$permit->assigned = false;
		}
	}

	return TRUE;
}

function Page_out() {
	global $_DB, $_STATE;

	EX_pageStart(array("call_server.js")); //standard HTML page start stuff - insert SCRIPTS here
?>
<script language="JavaScript">
LoaderS.push('load_status();');

function load_status() {
  document.getElementById("msgStatus_ID").innerHTML = "<?php echo $_STATE->msgStatus ?>";
}
</script>
<?php
	EX_pageHead(); //standard page headings - after any scripts

	switch ($_STATE->status) {
	case LIST_PERSONS:
		global $persons;
		echo $persons->set_list();
		break; //end LIST_PERSONS status ----END STATUS PROCESSING----

	case LIST_PROJECTS:
		global $projects;
		echo $projects->set_list();
		break; //end LIST_PROJECTS status ----END STATE: EXITING FROM PROCESS----

	case LIST_PERMITS:
	case UPDATE_PERMIT:
?>
<form method="post" name="frmAction" id="frmAction_ID" action="<?php echo $_SESSION["IAm"]; ?>">
<table style='margin:auto;'>
<?php
		$grade = -1;
		foreach($_STATE->fields as $permit_id => $field) {
			if ($grade != $field->grade) {
				$grade = $field->grade;
				echo "  <tr><td></td><td class='group'>";
				switch ($grade) {
					case PERMITS::GR_SYS: echo "System:"; break;
					case PERMITS::GR_ORG: echo "Organization:"; break;
					case PERMITS::GR_PRJ: echo "Project:"; break;
					default: echo "Unknown(".$grade."):";
				}
				echo "</td></tr>\n";
			}
			echo "  <tr><td colspan='2'>";
		  	echo "<input type='checkbox' name='chkPermit[".strval($permit_id)."]'";
			if ($field->disabled) echo " disabled";
			if ($field->assigned) echo " checked";
			echo ">".$field->desc."";
			echo "</td></tr>\n";
		}
?>
</table>
<?php
		if ($_STATE->status != STATE::DONE) {
?>
  <button type="submit">Assign these permissions</button>
<?php
		}
?>
</form>
<?php
		break; //end LIST/UPDATE_PERMIT status ----END STATUS PROCESSING----

	default:
		throw_the_bum_out(NULL,"Evicted(".__LINE__."): invalid state=".$_STATE->status);

	} //end select ($_STATE->status) ----END STATE: EXITING FROM PROCESS----

	EX_pageEnd(); //standard end of page stuff

} //end function Page_out()
?>
