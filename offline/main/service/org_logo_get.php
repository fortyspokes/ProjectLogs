<?php
//copyright 2015,2016 C.D.Price. Licensed under Apache License, Version 2.0
//See license text at http://www.apache.org/licenses/LICENSE-2.0
if (isset($_GET["ID"])) {
	$org_id = $_GET["ID"];
} elseif (isset($_SESSION["organization_id"])) {
	$org_id = $_SESSION["organization_id"];
} else {
	$org_id = 1; //not logged in so take default
	$_TEMP_PERMIT = "_LEGAL_"; //a temp permission for the "are you logged in" gate
}
require_once "prepend.php";
require_once "lib/common.php";
require_once ("lib/db_".$_SESSION['_SITE_CONF']['DBMANAGER'].".php");

$db = new db_connect($_SESSION['_SITE_CONF']['DBEDITOR']);

$sql = "SELECT logo, logo_type FROM ".$db->prefix."a00_organization WHERE organization_id=:org;";
$stmt = $db->prepare($sql);
$stmt->bindParam(':org', $org_id, PDO::PARAM_INT);
$stmt->execute();
$stmt->bindColumn("logo", $logo, db_connect::PARAM_LOB);
$stmt->bindColumn("logo_type", $type, PDO::PARAM_STR);
$stmt->fetch(PDO::FETCH_BOUND);
if (is_null($logo)) { //if no logo for this org, use default
	$stmt->closeCursor();
	$org_id = 1;
	$stmt->execute();
	$stmt->bindColumn("logo", $logo, db_connect::PARAM_LOB);
	$stmt->bindColumn("logo_type", $type, PDO::PARAM_STR);
	$stmt->fetch(PDO::FETCH_BOUND);
}
$stmt->closeCursor();

ob_end_clean(); //clean out any headers the includes might have put in (prepend started the buffer)
header("Content-Type: image/".$type);
$db->BLOB_to_page($logo);

$db = NULL;
?>

