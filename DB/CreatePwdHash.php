<?php
//run from a command line: php -f file-name
//  or interactive mode: php -a
//  php > include "Offline.php";

echo("To enter a parameter, type it followed by a newline follwed by ctrl-d\n");

$outputname = "HashedPWD.txt";
$output = fopen($outputname,"w");
while (1==1) {

	echo("Please enter the password:\n");
	exec ("cat",$password);
	$password = $password[0];
	if ($password == "") break;

	fwrite($output, $password."=>".password_hash($password, PASSWORD_DEFAULT)."\n");

}
fclose($output);
?>

