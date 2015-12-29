<?php //copyright 2010,2014-2015 C.D.Price
//copyright 2015 C.D.Price. Licensed under Apache License, Version 2.0
//See license text at http://www.apache.org/licenses/LICENSE-2.0

function init_setup() {
	global $_DB, $_STATE;

	$state= $GLOBALS["_STATE"];

	$sql = "SELECT a00.organization_id, a00.name, a00.timezone
			FROM ".$_DB->prefix."a00_organization AS a00";
	if (!$GLOBALS["_PERMITS"]->can_pass(PERMITS::_SUPERUSER)) {
		$sql .= " INNER JOIN ".$_DB->prefix."c10_person_organization AS c10
				ON (a00.organization_id = c10.organization_idref)
				WHERE c10.person_idref=".$_SESSION["person_id"].";";
	}
	$sql .= " ORDER BY a00.timestamp";
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
	//Set theme for organization:
	$sql = "SELECT theme FROM ".$_DB->prefix."d10_preferences
			WHERE organization_idref=".$_SESSION["organization_id"].";";
	$stmt = $_DB->query($sql);
	if ($row = $stmt->fetchObject()) $_SESSION["_SITE_CONF"]["THEME"] = $row->theme;
	$stmt->closeCursor();
	$_SESSION["org_TZO"] = $_STATE->records[$_POST["selOrgs"]][1];
	$_SESSION["UserPermits"] = $GLOBALS["_PERMITS"]->get_permits($_SESSION["person_id"]); //set the users's permissions
	$_STATE->msgStatus = "Your organization has been changed";

	return true;
}

$reload = FALSE;
//Main State Gate: (the while (1==1) allows a loop back through the switch using a 'break 1')
while (1==1) { switch ($_STATE->status) {
case STATE::INIT:
	$_STATE->msgGreet = "Select an organization";
	$_STATE->msgStatus = "";
	$_STATE->status = STATE::SELECT; //prepare a 'goback'
//	break 1; //do a re-switch
case STATE::SELECT:
	init_setup();
	$_STATE->status = STATE::ENTRY;
	break 2;
case STATE::ENTRY:
	if (entry_audit()) {
		$reload = TRUE; //reload the header to reflect these changes
		$_STATE->msgGreet = "OK? If not, select again...";
	}
	$_STATE->goback(1); //to STATE::SELECT
	break 2;
default:
	throw_the_bum_out(NULL,"Evicted(".__LINE__."): invalid state=".$_STATE->status);
} } //while & switch

EX_pageStart(); //standard HTML page start stuff - insert scripts here

if ($reload) { ?>
<script language='JavaScript'>
LoaderS.push('top.reload_head(); top.reload_menu();');
</script>
<?php
}
EX_pageHead(); //standard page headings - after any scripts
?>

<form method="post" name="frmOrgs" action="<?php echo $_SERVER['SCRIPT_NAME']; ?>">
<p>
<select name='selOrgs' size="<?php echo count($_STATE->records); ?>" onclick="javascript: this.form.submit();">
<?php
foreach($_STATE->records as $value => $org) {
	echo "<option value=\"".$value."\">".$org[0]."\n";
} ?>
</select>
</p>
</form>

<?php
EX_pageEnd(); //standard end of page stuff
?>

