<?php
//copyright 2010,2014-2016,2019 C.D.Price. Licensed under Apache License, Version 2.0
//See license text at http://www.apache.org/licenses/LICENSE-2.0

//The Main State Gate cases:
define('LIST_ORGS',			STATE::INIT);
define('SELECT_ORG',			LIST_ORGS + 1);
define('SELECTED_ORG',			LIST_ORGS + 2);

$reload = FALSE;
//Main State Gate: (the while (1==1) allows a loop back through the switch using a 'break 1')
while (1==1) { switch ($_STATE->status) {
case LIST_ORGS:
	$_STATE->msgGreet = "Select an organization";
	$_STATE->msgStatus = "";
case SELECT_ORG:
	init_setup();
	Page_out();
	$_STATE->status = SELECTED_ORG;
	break 2; //return to executive

case SELECTED_ORG:
	if (entry_audit()) {
		$reload = TRUE; //reload the header to reflect these changes
		$_STATE->msgGreet = "OK? If not, select again...";
	}
	$_STATE = $_STATE->goback_to(LIST_ORGS, true);
	break 1;

default:
	throw_the_bum_out(NULL,"Evicted(".$_STATE->ID."/".__LINE__."): invalid state=".$_STATE->status);
} } //while & switch
//End Main State Gate & return to executive

function init_setup() {
	global $_DB, $_STATE;

	$sql = "SELECT a00.organization_id, a00.name, a00.timezone
			FROM ".$_DB->prefix."a00_organization AS a00";
	if (!$GLOBALS["_PERMITS"]->can_pass(PERMITS::_SUPERUSER)) {
		$sql .= " INNER JOIN ".$_DB->prefix."c10_person_organization AS c10
				ON (a00.organization_id = c10.organization_idref)
				WHERE c10.person_idref=".$_SESSION["person_id"];
	}
	$sql .= " ORDER BY a00.timestamp;";
	$stmt = $_DB->query($sql);
	$_STATE->records = array();
	while ($row = $stmt->fetchObject()) {
		$_STATE->records[strval($row->organization_id)] = array($row->name,$row->timezone);
	}
	$stmt->closeCursor();

	if (count($_STATE->records) < 2) {
		throw_the_bum_out(NULL,"Evicted(".__LINE__."): no menu item"); //menu does not list this case so user is spoofing us!
	}
}

function entry_audit() {
	global $_DB, $_STATE;

	init_setup(); //restore the list
	if (!array_key_exists($_POST["selOrgs"], $_STATE->records)) {
		throw_the_bum_out(NULL,"Evicted(".__LINE__."): invalid org id ".$_POST["selOrgs"]); //we're being spoofed
	}
	init_setup(); //re-display the list
	$_SESSION["organization_id"] = intval($_POST["selOrgs"]);
	//Set preferences for organization:
	require_once "lib/preference_set.php";
	$prefs = new PREF_GET("a00",$_SESSION["organization_id"]);
	if ($pref = $prefs->preference("theme")) {
		$_SESSION["THEME"] = $pref;
	} else {
		$_SESSION["THEME"] = $_SESSION["_SITE_CONF"]["THEME"]; //go back to default
	}
	$back = STATE_pull($_STATE->thread,1); //a pref change must be in prior SSO
	$back->dateform = $prefs->preference("date");
	$back->replace();
	$_SESSION["org_TZO"] = $_STATE->records[$_POST["selOrgs"]][1];
	$_SESSION["UserPermits"] = $GLOBALS["_PERMITS"]->get_permits($_SESSION["person_id"]); //set the users's permissions
	$_SESSION["UserPermits"]["_LEGAL_"] = TRUE; //continue to pass the 'logged in' gate
	$_STATE->msgStatus = "Your organization has been changed";

	return true;
}

function Page_out() {
	global $_DB, $_STATE;

	EX_pageStart(); //standard HTML page start stuff - insert scripts here

	global $reload;
	if ($reload) {
?>
<script language='JavaScript'>
LoaderS.push('top.reload_head(); top.reload_menu();');
</script>
<?php
	}
	EX_pageHead(); //standard page headings - after any scripts
?>

<form method="post" name="frmOrgs" action="<?php echo $_SESSION["IAm"]; ?>">
<p>
<select name='selOrgs' size="<?php echo count($_STATE->records); ?>" onclick="javascript: this.form.submit();">
<?php
	foreach($_STATE->records as $value => $org) {
		echo "<option value=\"".$value."\">".$org[0]."\n";
	}
?>
</select>
</p>
</form>

<?php
	EX_pageEnd(); //standard end of page stuff
} //end Page_out()
?>
