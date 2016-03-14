<?php
require_once "prepend.php";
require_once "common.php";
require_once ("db_".$_SESSION['_SITE_CONF']['DBMANAGER'].".php");
$db = new db_connect($_SESSION['_SITE_CONF']['DBEDITOR']);
if (($_SESSION["_SITE_CONF"]["RUNLEVEL"] < 1) || (!$_PERMITS->can_pass(PERMITS::_SUPERUSER)))
	throw_the_bum_out(NULL,"Evicted(".__LINE__."): no permit");

echo "<br>ORing...";
$one = 1;
$two = 2;
$result = $one;
$result = (2 | $result);
echo "<br>".$result;

//show the last timelog entry for each person:
function lastlog($db) {
	$sql = "SELECT MIN(logdate) AS mindate FROM ".$db->prefix."b00_timelog;";
	$stmt = $db->query($sql);
	$row = $stmt->fetchObject();
	$logdate = new DateTime($row->mindate);
	echo "Min date = ".$row->mindate."<br>\n";
	$stmt->closeCursor();

	$IDs = array();
	$sql = "SELECT person_id, lastname, firstname FROM ".$db->prefix."c00_person
			ORDER BY lastname;";
	$stmt = $db->query($sql);
	while ($row = $stmt->fetchObject()) {
		$IDs[strval($row->person_id)] = $row->lastname.", ".$row->firstname;
	}
	$stmt->closeCursor();

	$sql = "SELECT MAX(logdate) AS maxdate FROM ".$db->prefix."b00_timelog WHERE person_idref=:ID GROUP BY 				person_idref;";
	$stmt = $db->prepare($sql);

	echo "<table>\n<tr>\n";

	foreach ($IDs as $ID => $value) {
		echo "<tr><td>".$ID."</td><td>".$value."</td><td>";
		$stmt->bindValue(':ID',$ID,PDO::PARAM_INT);
		$stmt->execute();
		if ($row = $stmt->fetchObject()) {
			$logdate = new DateTime($row->maxdate);
			echo $logdate->format("Y-m-d");
		} else {
			echo "no logs";
//			$upd = "UPDATE ".$db->prefix."c10_person_organization
//					SET inactive_asof='2013-06-30' WHERE person_idref=".$ID.";";
//			$db->exec($upd);
		}
		$stmt->closeCursor();
		echo "</td></tr>\n";
	}
	echo "</tr>\n</table\n";
}
//...end 'show last timelog entry'

function list_sub($db) {
	echo "<table>\n";
	echo "<tr><th></th><th>ID</th><th>name</th><th>description<th><th>logdate<th></tr>\n";
/*	$sql = " WHERE a12.task_id IS NULL;";
	$sql = " WHERE a14.name='*';";
	$sql = "SELECT a14.* FROM a14_subtask AS a14
			LEFT OUTER JOIN a12_task AS a12 ON a14.task_idref = a12.task_id".
			$sql;*/
//	$sql = " WHERE a14.name='*';";
//	$sql = " WHERE b00.timelog_id IS NULL;";
	$sql = " WHERE a14.task_idref=0 AND b00.logdate > '2009-12-31'";
//	$sql = "SELECT a14.* FROM a14_subtask AS a14
//			LEFT OUTER JOIN b00_timelog AS b00 ON a14.subtask_id = b00.subtask_idref".
//			$sql;
	$sql = "SELECT a14.subtask_id, a14.name, a14.description, COUNT(*) AS count, MAX(b00.logdate) AS logdate
			FROM a14_subtask AS a14
			LEFT OUTER JOIN b00_timelog AS b00 ON a14.subtask_id = b00.subtask_idref".
			$sql." GROUP BY a14.subtask_id;";
	$stmt = $db->query($sql);
	$ndx = 1;
	while ($row = $stmt->fetchObject()) {
		echo "<tr>\n";
		echo "<td>".$row->count."</td><td>".$row->subtask_id."</td><td>".$row->name."</td><td>".$row->description."</td><td>".$row->logdate."</td>\n";
		echo "</tr>\n";
	}
	echo "</table>\n";
}

function parsing(&$_DB) {

		$menu_prefs = "EL=Class Counts&TP=Download Invoice Logs";
		$sql = "UPDATE d10_preferences SET menu=\"".$menu_prefs."\" WHERE project_idref=1;";
		$_DB->exec($sql);
		$sql = "SELECT menu FROM ".$_DB->prefix."d10_preferences
				WHERE project_idref=1;";
		$stmt = $_DB->query($sql);
		if ($row = $stmt->fetchObject()) {
			$str = str_replace("&","\n",$row->menu);
			$menu_prefs = parse_ini_string($str);
			foreach ($menu_prefs as $key=>$value)
				echo $key."=>".$value."<br>";
		}
		$stmt->closeCursor();

	echo "<br>parse_ini_string:<br>";
	$str = "EL[A]='Class Counts'\nEL[B]=eventlog.php\n";
	$ini = parse_ini_string($str);
	foreach ($ini["EL"] as $key=>$value) {
		echo $key."=>".$value."<br>";
	}

	echo "<br>parse_str:<br>";
	$str = "EL[A]=Class+Counts&EL[B]=eventlog.php";
	parse_str($str);
	echo "EL[A]=".$EL['A']."<br>";
	echo "EL[B]=".$EL['B']."<br>";

	}

/*
echo "No experiments pending\n";
echo "<br>Goodbye!";
$db = null;
*/

/*
$now = NOW();
$gmt = new DateTime();
echo "<br>Server=".$gmt->format("Y-m-d H:i:s");
echo "<br>NOW=".$now->format("Y-m-d H:i:s");
echo "<br>[org_TZO]=".$_SESSION["org_TZO"];
echo "<br>[_SITE_CONF][TZO]=".$_SESSION["_SITE_CONF"]["TZO"];
echo "<br>modify=".($_SESSION["org_TZO"] - $_SESSION["_SITE_CONF"]["TZO"])." hours";
exit;
*/

//echo "This should produce an error: ".$errorLine;

/*
if (PHP_VERSION_ID >= 50500) {
	echo ("Versions:");
	echo ("<br>PHP_VERSION=".PHP_VERSION);
	echo ("<br>PHP_VERSION_ID=".PHP_VERSION_ID);
	echo '<br>Current PHP version: ' . phpversion();
} else {
	echo ("It sure is old");
}
exit;
*/

/*
$ext = array("name" => "class_count");
$_SESSION["_EXTENSION"] = array_merge($ext,parse_ini_file($_SESSION["_SITE_CONF"]["_EXTENSIONS"].$ext["name"]."_conf.php"));
*/

/*
<html>
<head>
<title>Popup test</title>
<meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1">
<script language="JavaScript">
<!--
function extension() {
  var href = '<?php echo($_SESSION["_SITE_CONF"]["_EXTENSION"]."executive.php"); ?>';
  var windowname = '<?php echo($_SESSION["_EXTENSION"]["title"]); ?>';
  window.open(href, windowname, 'width=800,height=400,left=50,top=100,scrollbars=yes');
  return false;
}
//-->
</script>
</head>

<body>
<br><button type="button" onclick="return extension()">Popup</button>
</body>
</html>
*/ ?>

