<?php
//run from a command line: php -f file-name
//  or interactive mode: php -a
//  php > include "Offline.php";

echo("To enter a parameter, type it followed by a newline follwed by ctrl-d\n");

echo("Where to put the soundex?:\n");
exec ("cat",$soundex_loc);
$soundex_loc = $soundex_loc[0];

$outputname = $soundex_loc."/Soundex.txt";
$output = fopen($outputname,"w");
while (1==1) {

	echo("Please enter the source:\n");
	exec ("cat",$soundex);
	$soundex = $soundex[0];
	if ($soundex == "") break;

	fwrite($output, $soundex."=>".soundex($soundex)."\n");

}
fclose($output);
?>

