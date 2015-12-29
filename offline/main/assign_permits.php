<?php
//copyright 2015 C.D.Price. Licensed under Apache License, Version 2.0
//See license text at http://www.apache.org/licenses/LICENSE-2.0
if (!$_PERMITS->can_pass("assign_permits")) throw_the_bum_out(NULL,"Evicted(".__LINE__."): no permit");

//Define the cases for the Main State Gate that are unique to this module:
define('SELECT_PERSON', STATE::SELECT);
define('SELECTED_PERSON', STATE::SELECTED);
define('SELECT_PROJECT', STATE::SELECT + 1);
define('SELECTED_PROJECT', STATE::SELECTED + 1);

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
case STATE::INIT:
	$_STATE->person_id = 0;
	require_once "person_select.php";
	$persons = new PERSON_SELECT(array(-$_SESSION["person_id"])); //blacklist: user can't change own permits
	if ($persons->selected != 0) { //solo person?
		$persons->set_state();
		$_STATE->status = SELECTED_PERSON;
		break 1; //re-switch
	}
	$_STATE->person_select = serialize(clone($persons));
	$_STATE->msgGreet = "Select a person to assign permissions";
	$_STATE->status = SELECT_PERSON;
	break 2;
case SELECT_PERSON:
	require_once "person_select.php"; //catches $_GET list refresh
	$persons = unserialize($_STATE->person_select);
	$persons->set_state();
	$_STATE->status = SELECTED_PERSON;
//	break 1; //re-switch
case SELECTED_PERSON:
	require_once "project_select.php";
	$projects = new PROJECT_SELECT();
	$_STATE->project_select = serialize(clone($projects));
	if ($projects->selected) {
		$_STATE->status = SELECTED_PROJECT;
		break 1; //re-switch to SELECTED_PROJECT
	}
	$_STATE->msgGreet = "Select the project";
	$_STATE->status = SELECT_PROJECT;
	break 2;
case SELECT_PROJECT:
	require_once "project_select.php"; //catches $_GET list refresh
	$projects = unserialize($_STATE->project_select);
	$projects->set_state();
	$_STATE->project_select = serialize(clone($projects));
	$_STATE->status = SELECTED_PROJECT; //for possible goback
	$_STATE->replace();
//	break 1; //re_switch
case SELECTED_PROJECT:
	require_once "project_select.php"; //in case of goback
	$projects = unserialize($_STATE->project_select);
	$_STATE->project_name = $projects->selected_name();
	$_STATE->msgGreet = $_STATE->project_name."<br>Assign (check) or Un-assign permissions for <br>".
						$_STATE->person_name;
	permit_list($_PERMITS);
	$_STATE->status = STATE::ENTRY;
	break 2;
case STATE::ENTRY:
	if (entry_audit($_PERMITS)) {
		$_STATE->msgGreet = "New permissions for ".$_STATE->person_name;
		$_STATE->status = STATE::DONE;
	}
	$_STATE->goback(1); //sets up goback to STATE::INIT
	break 2;
default:
	throw_the_bum_out(NULL,"Evicted(".__LINE__."): invalid state=".$_STATE->status);
} } //while & switch
//End Main State Gate

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

//-------end function code; begin HTML------------

EX_pageStart(); //standard HTML page start stuff - insert SCRIPTS here

if ($_STATE->status == SELECT_PROJECT)
	echo "<script type='text/javascript' src='".$EX_SCRIPTS."/call_server.js'></script>\n";
?>
<script language="JavaScript">
LoaderS.push('load_status();');

function load_status() {
  document.getElementById("msgStatus_ID").innerHTML = "<?php echo $_STATE->msgStatus ?>";
}
</script>
<?php
if ($_STATE->status == STATE::SELECT) {
	echo "<script type='text/javascript' src='".$EX_SCRIPTS."/call_server.js'></script>\n";
}
EX_pageHead(); //standard page headings - after any scripts

//forms and display depend on process state; note, however, that the state was probably changed after entering
//the Main State Gate so this switch will see the next state in the process:
switch ($_STATE->status) {
case SELECT_PERSON:

	echo $persons->set_list();

	break; //end SELECT_PERSON status ----END STATUS PROCESSING----

case SELECT_PROJECT:

	echo $projects->set_list();

	break; //end SELECT_PROJECT status ----END STATE: EXITING FROM PROCESS----

//case STATE::ENTRY:
//case STATE::DONE:
default:
?>
<form method="post" name="frmAction" id="frmAction_ID" action="<?php echo $_SERVER['SCRIPT_NAME']; ?>">
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
	} ?>
</table>
<?php
	if ($_STATE->status != STATE::DONE) { ?>
  <button type="submit">Assign these permissions</button>
<?php
	} ?>
</form>
<?php
//end default status ----END STATUS PROCESSING----
}

EX_pageEnd(); //standard end of page stuff
?>

