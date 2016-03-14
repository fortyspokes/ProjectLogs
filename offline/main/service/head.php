<?php
//copyright 2015 C.D.Price. Licensed under Apache License, Version 2.0
//See license text at http://www.apache.org/licenses/LICENSE-2.0
$_TEMP_PERMIT = "_LEGAL_"; //a temp permission for the "are you logged in" gate (in prepend)
require_once "prepend.php";
require_once "common.php";
require_once ("db_".$_SESSION['_SITE_CONF']['DBMANAGER'].".php");

if (isset($_POST["btnSubmit"])) { //logging out
	throw_the_bum_out("Goodbye!","Logout: by id=".$_SESSION["person_id"]); //let prepend take care of it (not really a bum)
}

$organization = "";
$person = "";
if (isset($_SESSION["person_id"])) { //logged in

	$db = new db_connect($_SESSION['_SITE_CONF']['DBEDITOR']);

	$sql = "SELECT name FROM ".$db->prefix."a00_organization WHERE organization_id=:org";
	$stmt = $db->prepare($sql);
	$stmt->bindValue(':org', $_SESSION["organization_id"], PDO::PARAM_INT);
	$stmt->execute();
	if (!($row = $stmt->fetchObject())) {
		$organization = "--No Organization--";
	} else {
		$organization = COM_output_edit($row->name);
	}
	$stmt->closeCursor();

	$sql = "SELECT firstname, lastname FROM ".$db->prefix."c00_person WHERE person_id=:person";
	$stmt = $db->prepare($sql);
	$stmt->bindValue(':person', $_SESSION["person_id"], PDO::PARAM_INT);
	$stmt->execute();
	$row = $stmt->fetchObject();
	$person = COM_output_edit($row->firstname." ".$row->lastname);
	$stmt->closeCursor();

	$db = NULL;

} elseif (isset($_SESSION["_EVICTED"])) {
	$organization = $_SESSION["_EVICTED"];
	unset($_SESSION["_EVICTED"]);
}

$redirect = $_SESSION["_SITE_CONF"]["_REDIRECT"];
?>
<html>
<head>
<title>SR2S Timesheets Head</title>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
<link rel="stylesheet" href="<?php echo
	$redirect."/css".$_SESSION["_SITE_CONF"]["CSS"]."/".$_SESSION["_SITE_CONF"]["THEME"]; ?>/head.css" type="text/css">
</head>

<body>
<form method="post" name="frmLogout" action="<?php echo $_SERVER['SCRIPT_NAME']; ?>">
<table width="100%">
 <tr>
  <td width="110" valign="top"><img src="<?php echo $redirect; ?>/images/logo_main.jpg" height="100" alt="Safe Routes to School logo"></td>
  <td align="center">
   <table>
    <tr><td colspan="2" align="center"><?php echo $organization; ?></td></tr>
<?php
if(isset($_SESSION["person_id"])) { ?>
  <tr>
    <td align="right" valign="center">You are logged in as <?php echo $person; ?></td>
    <td align="left" valign="center">
        <button type="submit" name="btnSubmit" value="logout">Logout</button>
    </td>
  </tr>
<?php
} ?>
    <tr><td align="center" colspan="2" id="msgHead_ID" class="pagehead"></td></tr>
   </table>
  </td>
  <td width="110" valign="top"><img src="<?php echo $redirect; ?>/main/org_logo_get.php" height="110" width="110" align="right" alt="logo for <?php echo $organization; ?>"></td>
 </tr>
</table>
</form>
</body>
</html>
