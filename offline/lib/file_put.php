<?php
//copyright 2015-2017 C.D.Price. Licensed under Apache License, Version 2.0
//See license text at http://www.apache.org/licenses/LICENSE-2.0

function FP_open($filename, $openout = true) {
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
 
	header("Content-Type: application/force-download");
	header("Content-Type: application/octet-stream");
	header("Content-Type: application/download");
	//header("Content-Disposition: inline; filename=\"".$filename."\"");
	header("Content-Disposition: attachment; filename=\"".$filename."\"");

	if ($openout) return fopen('php://output', 'w');
}

//Deprecated: For downloading DB BLOBs, use PDO(child)::BLOB_download()
function FP_putBlob(&$handle, &$BLOB) {
	fwrite($handle, $BLOB);
}

function FP_close(&$handle) {
	fclose($handle);
	ob_end_flush();
	exit();
}
