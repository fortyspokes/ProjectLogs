<?php
//copyright 2015-2017 C.D.Price. Licensed under Apache License, Version 2.0
//See license text at http://www.apache.org/licenses/LICENSE-2.0

$FP_handle;

function FP_headers($filename) {
	// from w-shadow.com/blog/2007/08/12/how-to-force-file-download-with-php/...
	// required for IE, otherwise Content-Disposition may be ignored
	if(ini_get('zlib.output_compression'))
		ini_set('zlib.output_compression', 'Off');

	ob_clean();
	ob_start();

	// The three lines below basically make the download non-cacheable:
	header("Cache-control: private");
	header('Pragma: private');
	header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");
 
	header("Content-Type: application/octet-stream");
	header("Content-Disposition: attachment; filename=\"".$filename."\"");
}

//Need the output handle if writing line-by-line, eg. CSV files:
function FP_open($filename) {
	FP_headers($filename);
	$FP_handle = fopen('php://output', 'w');
	return $FP_handle;
}

//Use of FP_close with $handle is deprecated but is still in use:
function FP_close($handle) {
//fclose causes some kind of error - but isn't needed anyway
//	fclose($FP_handle);
	FP_end();
}

function FP_end() {
	ob_end_flush();
	exit;
}

//Deprecated: For downloading DB BLOBs, use PDO(child)::BLOB_download()
function FP_putBlob(&$handle, &$BLOB) {
	fwrite($handle, $BLOB);
}
