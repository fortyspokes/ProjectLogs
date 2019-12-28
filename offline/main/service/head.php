<?php
//copyright 2015-2016,2019 C.D.Price. Licensed under Apache License, Version 2.0
//See license text at http://www.apache.org/licenses/LICENSE-2.0
$_TEMP_PERMIT = "_LEGAL_"; //a temp permission for the "are you logged in" gate (in prepend)
require_once "prepend.php";
require_once "lib/common.php";
require_once ("lib/db_".$_SESSION['_SITE_CONF']['DBMANAGER'].".php");

//status: 0=initial login;1=timed out;2=logged in;3=logged out;4=evicted
if (isset($_POST["btnSubmit"])) { //logging out - reload the top
	$_SESSION["_STATUS"] = 3;
	require_once "lib/reload.php";
	reload_top(); //does not return
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
}

$status = $_SESSION["_STATUS"]; //save & reset
$_SESSION["_STATUS"] = 0;
if ($ipos=stripos($status,":")) {
	$greeting = substr($status,$ipos + 1);
	$status = substr($status,$ipos);
} else {
	switch ($status) {
	case 0: //initial login
		$greeting = "Hello, world!";
		break;
	case 1: //timed out
		$greeting = "Inactive session canceled";
		break;
	case 2: //logged in
		$greeting = "You are logged in as ";
		break;
	case 3: //logged out
		$greeting = "Goodbye!";
		break;
	case 4: //evicted
		$greeting = "System problem; notify the administrator";
		break;
	} //end switch
}
?>
<html>
<head>

<title>SR2S Timesheets Head</title>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
<link rel="stylesheet" href="<?php echo $_SESSION["BUTLER"]; ?>?IAm=CG&file=head&ver=<?php echo $_VERSION; ?>" type="text/css">
</head>

<body>
<form method="post" name="frmLogout" action="<?php echo $_SERVER['SCRIPT_NAME']; ?>?IAm=HD">
<table width="100%">
 <tr>
  <td width="110" valign="top"><img src="<?php echo $_SESSION["BUTLER"]; ?>?IAm=IG&file=logo_main.jpg&ver=<?php echo $_VERSION; ?>" height="100" title="Project Logs version <?php echo $_VERSION; ?>"></td>
  <td align="center">
   <table>
    <tr><td colspan="2" align="center"><?php echo $organization; ?></td></tr>
<?php
if ($status == 2) { //logged in
?>
  <tr>
    <td align="right" valign="center"><?php echo $greeting.$person; ?></td>
    <td align="left" valign="center">
        <button type="submit" name="btnSubmit" value="logout">Logout</button>
    </td>
  </tr>
<?php
} else {
?>
  <tr>
    <td align="right" valign="center"><?php echo $greeting; ?></td>
  </tr>
<?php
} //end if
?>
    <tr><td align="center" colspan="2" id="msgHead_ID" class="pagehead"></td></tr>
   </table>
  </td>
  <td width="110" valign="top"><img src="<?php echo $_SESSION["BUTLER"]; ?>?IAm=LG" height="110" width="110" align="right" alt="logo for <?php echo $organization; ?>"></td>
 </tr>
</table>
</form>
</body>
</html>
