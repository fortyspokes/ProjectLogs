<?php
//copyright 2015,2017,2022 C.D.Price. Licensed under Apache License, Version 2.0
//See license text at http://www.apache.org/licenses/LICENSE-2.0

class db_connect extends PDO {

public $prefix;
public $in_trans = FALSE;
//const PARAM_LOB = parent::PARAM_LOB;
const PARAM_DATE = parent::PARAM_STR;

function __construct($userstr,$dbconn="") {
	$this->prefix = $_SESSION['_SITE_CONF']['DBPREFIX'];
	$user = explode(substr($userstr,0,1),substr($userstr,1)); //1st char is delimiter between user & pswd
	if ($dbconn == "") {
		$dbconn = $_SESSION['_SITE_CONF']['DBCONN'];
	}
	parent::__construct('mysql:'.$dbconn,$user[0],$user[1]);
	parent::setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION); //give all errors to the error handler
/*
	mysql_query("SET character_set_results = 'utf8', character_set_client = 'utf8', character_set_connection = 'utf8', character_set_database = 'utf8', character_set_server = 'utf8'", $conn); //from user discussion in PHP doc for mysql_set_charset()
	mysql_query("SET character_set_system='utf8'", $conn); //recommended by /common/programmming/mysql/MakeEverythingUTF8.odt ??
	$sql = "SET sql_mode='NO_AUTO_VALUE_ON_ZERO';";
	$this->exec($sql);
*/
}

function beginTransaction(): bool {
	$this->in_trans = TRUE;
	return parent::beginTransaction();
}

function commit(): bool {
	$this->in_trans = FALSE;
	return parent::commit();
}

function rollBack(): bool {
	$this->in_trans = FALSE;
	return parent::rollBack();
}

function BLOB_to_page(&$blob) {
	//Apparently, MySQL is sending our blob fields back as a hex string;
	// instead of figuring out why, we'll just do this for now:
//	echo pack("H*",$blob);
//	fpassthru($blob);
	echo ($blob);
}

function BLOB_download($filename, &$blob) {
	require_once "lib/file_put.php";
	FP_headers($filename);
	echo $blob;
	FP_end(); //does not return
}

function file_to_BLOB($file) {

	if (is_string($file)) {
		$fileh = fopen($file, 'rb'); //read binary
	} else {
		$fileh = $file;
	}
	return $fileh;
}

function delete_BLOB(&$oid) {
	return NULL;
}

function reset_auto($table, $field, $value=1) { //next insert will get $value, NOT $value + 1

	$sql = "ALTER TABLE ".$table." AUTO_INCREMENT = ".$value.";";

	$this->exec($sql);
}

} // end class db_connect

