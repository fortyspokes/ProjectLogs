<?php
//copyright 2015,2017,2019 C.D.Price. Licensed under Apache License, Version 2.0
//See license text at http://www.apache.org/licenses/LICENSE-2.0

class db_connect extends PDO {

public $prefix;
public $in_trans = FALSE;
const PARAM_LOB = parent::PARAM_INT;
const PARAM_DATE = parent::PARAM_STR;

function __construct($userstr,$dbconn="") {
	$this->prefix = $_SESSION['_SITE_CONF']['DBPREFIX'];
	$user = explode(substr($userstr,0,1),substr($userstr,1)); //1st char is delimiter between user & pswd
	if ($dbconn == "") {
		$dbconn = $_SESSION['_SITE_CONF']['DBCONN'];
	}
	parent::__construct('pgsql:'.$dbconn,$user[0],$user[1]);
	parent::setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION); //give all errors to the error handler
}

function beginTransaction() {
	$this->in_trans = TRUE;
	parent::beginTransaction();
}

function commit() {
	$this->in_trans = FALSE;
	parent::commit();
}

function rollBack() {
	$this->in_trans = FALSE;
	parent::rollBack();
}

function BLOB_to_page($oid) {
	if ($oid == 0) return;
	if (!$this->in_trans) {
		parent::beginTransaction();
	}
	$data = $this->pgsqlLOBOpen($oid, 'r');
	fpassthru($data);
	if (!$this->in_trans) {
		parent::commit();
	}
}

function BLOB_download($filename, $oid) {
	require_once "lib/file_put.php";
	FP_headers($filename);
	if (!$this->in_trans) {
		parent::beginTransaction();
	}
	$data = $this->pgsqlLOBOpen($oid, 'r');
	fpassthru($data);
	if (!$this->in_trans) {
		parent::commit();
	}
	FP_end(); //does not return
}

function file_to_BLOB($file) {
	if (is_string($file)) {
		$fileh = fopen($file, 'rb'); //read binary
	} else {
		$fileh = $file;
	}
	if (!$this->in_trans) {
		parent::beginTransaction();
	}
	$oid = $this->pgsqlLOBCreate();
	$lobh = $this->pgsqlLOBOpen($oid, 'w');
	stream_copy_to_stream($fileh, $lobh);
	$fileh = null;
	$lobh = null;
	if (!$this->in_trans) {
		parent::commit();
	}
	return $oid;
}

function delete_BLOB($oid) {
	if ($oid == 0) return NULL;
	if (!$this->in_trans) {
		parent::beginTransaction();
	}
	$this->pgsqlLOBUnlink($oid);
	if (!$this->in_trans) {
		parent::commit();
	}
	return NULL;
}

function reset_auto($table, $field, $value=1) { //next insert will get $value, NOT $value + 1

	$sql = "SELECT setval(pg_get_serial_sequence('".$table."', '".$field."'),".$value.", false);";
	$this->exec($sql);
}

} // end class db_connect

