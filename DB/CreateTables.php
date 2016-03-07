<?php
//run from a command line: php -f file-name
//  or interactive mode: php -a
//  php > include "Offline.php";

echo("To enter a parameter, type it followed by a newline follwed by ctrl-d\n");

echo("Where is the template?:\n");
exec ("cat",$template_loc);
$template_loc = $template_loc[0];

echo("Where to put the SQL?:\n");
exec ("cat",$SQL_loc);
$SQL_loc = $SQL_loc[0];

echo("DB name? (required for mySQL):\n");
exec ("cat",$DBname);
if (empty($DBname)) {
	$DBname = "";
} else {
	$DBname = $DBname[0];
}

echo("And what prefix?:\n");
exec ("cat",$prefix);
if (empty($prefix)) {
	$prefix = "";
} else {
	$prefix = $prefix[0];
}

//echo("Drop tables? ('y' or 'n'):\n");
//exec ("cat",$drop);
//if (strtolower($drop[0]) == "y") {
//	$drop = true;
//} else {
//	$drop = false;
//}

$config = parse_ini_file($SQL_loc."/CreateTables.ini",FALSE);

$inputname = $template_loc."/CreateTablesTemplate";
$outputname = $SQL_loc."/CreateTables";
if ($DBname != "") $outputname .= "_".$DBname;
if ($prefix == "") {
	$outputname .= "(no prefix).sql";
} else {
	$outputname .= "(prefix ".$prefix.").sql";
}

echo($inputname."->".$outputname." OK? ('y' or 'n'):\n");
exec ("cat",$cont);
if (strtolower($cont[0]) != "y") exit;

$input = fopen($inputname,"r");
$output = fopen($outputname,"w");

while (!feof($input)) {
	$buffer = fgets($input, 1024);
	$buffer = str_replace("<PREFIX>", $prefix, $buffer);
	$buffer = str_replace("<DBNAME>", $DBname, $buffer);
	foreach($config as $key => $value) {
		$buffer = str_replace("<".$key.">", $value, $buffer);
//		if ($drop) {
//			$buffer = str_replace("-- DROP", "DROP", $buffer);
//		}
	}
	fwrite($output, $buffer);
}

fclose($input);
fclose($output);

?>

