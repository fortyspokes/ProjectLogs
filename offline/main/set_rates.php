<?php
//copyright 2015 C.D.Price. Licensed under Apache License, Version 2.0
//See license text at http://www.apache.org/licenses/LICENSE-2.0
if (!$_PERMITS->can_pass("set_rates")) throw_the_bum_out(NULL,"Evicted(".__LINE__."): no permit");

define('SELECT_PROJECT', STATE::SELECT);
define('SELECTED_PROJECT', STATE::SELECTED);
define('SELECT_PERSON', STATE::SELECT + 1);
define('SELECTED_PERSON', STATE::SELECTED + 1);

function person_list() {
	global $_DB, $_STATE;

	//get each person in this org, then get their rate records; if no rate, return NULLs
	$sql = "SELECT c00.person_id, c00.lastname, c00.firstname,
					c02.rate_id, c02.rate, c02.effective_asof, c02.expire_after,
					c10.inactive_asof
			FROM ".$_DB->prefix."c00_person AS c00
			INNER JOIN ".$_DB->prefix."c10_person_organization AS c10
				ON c10.person_idref = c00.person_id
			LEFT OUTER JOIN
				(SELECT * FROM ".$_DB->prefix."c02_rate WHERE project_idref = ".$_STATE->project_id.") AS c02
				ON c10.person_idref = c02.person_idref
			ORDER BY c00.lastname, c02.effective_asof DESC;";
	$stmt = $_DB->query($sql);
	$_STATE->records = array();
	$rates = array();
	$EOF = -1;
	while ($EOF < 1) {
		if (!$row = $stmt->fetchObject()) { //EOF
			if ($EOF == -1) break; //no people!!??
			$EOF = 1;
		} else {
			if ($EOF == -1) $row_sav = $row; //first record
			$EOF = 0;
		}
		if (($EOF == 1) || ($row_sav->person_id != $row->person_id)) {
			$record = array(
				"ID" => $row_sav->person_id,
				"name" => $row_sav->lastname.", ".$row_sav->firstname,
				"inactive_asof" => $row_sav->inactive_asof,
				"rates" => $rates,
				);
			$_STATE->records[strval($row_sav->person_id)] = $record;
			if ($EOF == 1) break; //all done
			$rates = array();
			$row_sav = $row;
		}
		$rates[] = array(
			"ID" => $row->rate_id,
			"rate" => $row->rate,
			"eff" => $row->effective_asof,
			"exp" => $row->expire_after,
			);
	}
	$stmt->closeCursor();
}

function record_select() {
	global $_STATE;

	person_list(); //restore the record list
	if (!array_key_exists(strval($_POST["txtPerson"]), $_STATE->records)) {
		throw_the_bum_out(NULL,"Evicted(".__LINE__."): invalid person id ".$_POST["txtPerson"]); //we're being spoofed
	}
	$_STATE->record_id = intval($_POST["txtPerson"]);
}

function add_rate() {
	global $_DB, $_STATE;

	if ($_STATE->new_rate["eff"] == 0) {
		$_STATE->msgStatus = "!**Adding a new rate with 0 effective date makes no sense**";
		return false;
	}

	$eff = DateTime::createFromFormat('Y-m-d', $_STATE->new_rate["eff"]);
	if (!is_null($_STATE->rates[1]["ID"])) { //have an existing rate
		if ($_STATE->rates[1]["exp"] != "") { //rates[0] is the new rate
			$prior = DateTime::createFromFormat('Y-m-d', $_STATE->rates[1]["exp"]);
			if (($eff <= $prior) && !isset($_STATE->replies["AP"])) {
				$_STATE->msgStatus = "?APEffective date precedes prior expiration, adjust the prior date?";
				return false;
			}
		}
		$prior = DateTime::createFromFormat('Y-m-d', $_STATE->rates[1]["eff"]);
		if ($eff <= $prior) {
			$_STATE->msgStatus = "!**New effective date must follow prior effective date**";
			return false;
		}
		$prior = $eff;
		$prior->sub(new DateInterval('P1D'));
		$sql = "UPDATE ".$_DB->prefix."c02_rate SET expire_after='".$prior->format("Y-m-d")."'
				WHERE rate_id=".$_STATE->rates[1]["ID"].";";
		$_DB->exec($sql);
	}

	if (($_STATE->new_rate["exp"] == 0) || ($_STATE->new_rate["exp"] == ""))
		$_STATE->new_rate["exp"] = null;
	$sql = "INSERT INTO ".$_DB->prefix."c02_rate (person_idref, project_idref, rate, effective_asof, expire_after)
			VALUES (".$_STATE->record_id.",".$_STATE->project_id.",".$_STATE->new_rate["rate"].
					",:effective,:expire);";
	$stmt = $_DB->prepare($sql);
	$stmt->bindValue(':effective', $_STATE->new_rate["eff"], db_connect::PARAM_DATE);
	$stmt->bindValue(':expire', $_STATE->new_rate["exp"], db_connect::PARAM_DATE);
	$stmt->execute();
	$stmt->closeCursor();
}

function delete_rate() {
	global $_DB, $_STATE;

	$sql = "SELECT effective_asof, expire_after FROM ".$_DB->prefix."c02_rate
			WHERE rate_id=".$_STATE->new_rate["ID"].";";
	if (!$row = $_DB->query($sql)->fetchObject())
		throw_the_bum_out(NULL,"Evicted(".__LINE__."): invalid rate id"); //we're being spoofed

	$sql = "SELECT * FROM ".$_DB->prefix."v00_timelog
			WHERE (person_id=".$_STATE->record_id.") AND (project_id=".$_STATE->project_id.")";
	if (is_null($row->expire_after)) $sql .= " AND logdate >= :effdate;";
	else $sql .= " AND (logdate BETWEEN :effdate AND :expdate);";
	$stmt = $_DB->prepare($sql);
	$stmt->bindValue(':effdate', $row->effective_asof, db_connect::PARAM_DATE);
	if (!is_null($row->expire_after))
		$stmt->bindValue(':expdate', $row->expire_after, db_connect::PARAM_DATE);
	$stmt->execute();
	if ($stmt->fetchObject()) {
		$_STATE->msgStatus = "!**delete denied: time has been logged against this rate**";
		return;
	}
	$stmt->closeCursor();

	$sql = "DELETE FROM ".$_DB->prefix."c02_rate
			WHERE rate_id=".$_STATE->new_rate["ID"].";";
	$_DB->exec($sql);

}

function update_rate() {
	global $_DB, $_STATE;

	$sql = "SELECT * FROM ".$_DB->prefix."c02_rate WHERE rate_id=".$_STATE->new_rate["ID"].";";
	if (!$row = $_DB->query($sql)->fetchObject())
		throw_the_bum_out(NULL,"Evicted(".__LINE__."): invalid rate id"); //we're being spoofed

	$eff_new = DateTime::createFromFormat('Y-m-d', $_STATE->new_rate["eff"]);
	$eff_old = DateTime::createFromFormat('Y-m-d', $row->effective_asof);
	if ($eff_new != $eff_old) {
		$_STATE->msgStatus = "!**effective date update is not yet implemented...**";
		return;
	}

	$doit = false;
	$exp_new = DateTime::createFromFormat('Y-m-d', $_STATE->new_rate["exp"]);
	$exp_old = DateTime::createFromFormat('Y-m-d', $row->expire_after);
	if ($exp_new != $exp_old) {
		if ($_STATE->rate_ndx != 0) {
			$_STATE->msgStatus = "!**expiration date update allowed only on most recent rate record**";
			return;
		}
		if ($_STATE->new_rate["exp"] != 0) {
			$sql = "SELECT * FROM ".$_DB->prefix."v00_timelog
					WHERE (person_id=".$_STATE->record_id.") AND (project_id=".$_STATE->project_id.")
					AND logdate >= :expdate;";
			$stmt = $_DB->prepare($sql);
			$stmt->bindValue(':expdate', $_STATE->new_rate["exp"], db_connect::PARAM_DATE);
			$stmt->execute();
			if ($stmt->fetchObject()) {
				$_STATE->msgStatus = "!**update denied: time has been logged after this expiration**";
				return;
			}
			$stmt->closeCursor();
		}
		$doit = true;
	}

	if ($_STATE->new_rate["rate"] != $row->rate) $doit = true;

	if (($_STATE->new_rate["exp"] == 0) || ($_STATE->new_rate["exp"] == "")) $_STATE->new_rate["exp"] = null;
	if ($doit) {
		$sql = "UPDATE ".$_DB->prefix."c02_rate SET rate='".$_STATE->new_rate["rate"]."' , expire_after=:expdate
				WHERE rate_id=".$_STATE->new_rate["ID"].";";
		$stmt = $_DB->prepare($sql);
		$stmt->bindValue(':expdate', $_STATE->new_rate["exp"], db_connect::PARAM_DATE);
		$stmt->execute();
	}
}

function audit_input() {
	global $_STATE;

	if (!is_numeric($_STATE->new_rate["rate"]) || ($_STATE->new_rate["rate"] < 0)) {
		$_STATE->msgStatus = "!**Invalid entry for rate amount**";
		return false;
	}
	if (($_STATE->new_rate["rate"] > 200) && !isset($_STATE->replies["RT"])) {
		$_STATE->msgStatus = "?RTThis rate is suspiciously high, accept it?";
		return false;
	}
	$_STATE->new_rate["rate"] = number_format($_STATE->new_rate["rate"], 2);  //2 decimal places

	$today = COM_NOW();
	if ($_STATE->new_rate["eff"] != 0) {
		if (!($eff = DateTime::createFromFormat('Y-m-d', $_STATE->new_rate["eff"]))) {
			$_STATE->msgStatus = "!**Invalid effective date**";
			return false;
		}
		if (($today->diff($eff, true)->days > 365) && !isset($_STATE->replies["EF"])) {
			$_STATE->msgStatus = "?EFEffective date is more than a year away, accept it?";
			return false;
		}
	}

	if (($_STATE->new_rate["exp"] != 0) && ($_STATE->new_rate["exp"] != "") && ($_STATE->new_rate["eff"] != 0)) {
		if (!($exp = DateTime::createFromFormat('Y-m-d', $_STATE->new_rate["exp"]))) {
			$_STATE->msgStatus = "!**Invalid expiration date**";
			return false;
		}
		if (($today->diff($exp, true)->days > 365) && !isset($_STATE->replies["EX"])) {
			$_STATE->msgStatus = "?EXExpiration date is more than a year away, accept it?";
			return false;
		}
		if (isset($eff) && ($exp <= $eff)) {
			$_STATE->msgStatus = "!**New expiration date must follow effective date**";
			return false;
		}
	}

	$_STATE->msgStatus = "-"; //tell server_call to reset page
	return true;
}

function save_input() {
	global $_STATE;

	$_STATE->new_rate = array(
		"ID" => $_POST["ID"],
		"rate" => $_POST["rate"],
		"eff" => $_POST["eff"],
		"exp" => $_POST["exp"],
		);

	person_list();
	if (!array_key_exists($_STATE->record_id, $_STATE->records))
		throw_the_bum_out(NULL,"Evicted(".__LINE__."): invalid person id"); //we're being spoofed

	$rates = $_STATE->records[$_STATE->record_id]["rates"];
	$ndx = 0;
	if ($_POST["ID"] == 0) {
		$rate_rec = array("ID" => 0,);
		array_unshift($rates, $rate_rec); //add to beginning
	} else {
		$found = false;
		foreach ($rates as $rate_rec) {
			if ($rate_rec["ID"] == $_STATE->new_rate["ID"]) {
				$found = true;
				break;
			}
			++$ndx;
		}
		if (!$found)
			throw_the_bum_out(NULL,"Evicted(".__LINE__."): invalid rate id");
 	}
	$_STATE->rates = $rates; //rates for this person
	$_STATE->rate_ndx = $ndx;
}

function rate_change() {
	global $_STATE;

	if ($_SERVER['REQUEST_METHOD'] == "POST") { //POST carries rate data
		save_input();
		$_STATE->replies = array();
	} else {
		foreach ($_GET as $field => $reply) { //GET carries replies
			$_STATE->replies[$field] = $reply;
		}
	}

	if (!audit_input()) return;

	if ($_STATE->rates[$_STATE->rate_ndx]["ID"] == 0) {
		add_rate();
	} elseif ($_STATE->new_rate["eff"] == 0) {
		delete_rate();
	} else {
		update_rate();
	}
}

//Main State Gate: (the while (1==1) allows a loop back through the switch using a 'break 1')
while (1==1) { switch ($_STATE->status) {
case STATE::INIT:
	$_STATE->project_id = 0;
	$_STATE->close_date = false;
	$_STATE->show_inactive = false;
	require_once "project_select.php";
	$projects = new PROJECT_SELECT($_PERMITS->restrict("set_rates"));
	$_STATE->project_select = serialize(clone($projects));
	if ($projects->selected) {
		$_STATE->status = SELECTED_PROJECT;
		break 1; //re-switch to SELECTED_PROJECT
	}
	$_STATE->msgGreet = "Select the project";
	$_STATE->status = SELECT_PROJECT;
	break 2;
case SELECT_PROJECT:
	require_once "project_select.php"; //catches $_GET list refresh
	$projects = unserialize($_STATE->project_select);
	$projects->set_state();
	$_STATE->project_select = serialize(clone($projects));
	$_STATE->status = SELECTED_PROJECT; //for possible goback
	$_STATE->replace();
//	break 1; //re_switch
case SELECTED_PROJECT:
	require_once "project_select.php"; //in case of goback
	$projects = unserialize($_STATE->project_select);
	$_STATE->project_name = $projects->selected_name();
	person_list();
	$_STATE->msgGreet = $_STATE->project_name."<br>Select a person";
	$_STATE->status = SELECT_PERSON;
	break 2;
case SELECT_PERSON:
	if ((!isset($_POST["txtPerson"])) || ($_POST["txtPerson"] == "")) {
		$inactive = $_STATE->show_inactive;
		$_STATE = $_STATE->goback(1); // go back to SELECTED_PROJECT, ie. re-display persons (active vs inactive)
		$_STATE->show_inactive = !$inactive;
		break 1;
	}
	record_select();
	$_STATE->status = SELECTED_PERSON; //for possible goback
	$_STATE->replace();
//	break 1; //re_switch
case SELECTED_PERSON:
	$_STATE->msgGreet = $_STATE->project_name."<br>Rate history for ".$_STATE->records[strval($_STATE->record_id)]["name"];
	$_STATE->status = STATE::CHANGE;
	break 2;
case STATE::CHANGE:
	if (isset($_GET["reset"])) {
		$_STATE = $_STATE->goback(1); // go back to SELECTED_PERSON
		person_list();
		break 1;
	}
	ob_clean();
	rate_change();
	echo $_STATE->msgStatus; //the XMLHttpRequest responseText
	$_STATE->replace();
	exit();
	break 2;
default:
	throw_the_bum_out(NULL,"Evicted(".__LINE__."): invalid state=".$_STATE->status);
} } //while & switch

EX_pageStart(); //standard HTML page start stuff - insert SCRIPTS here

echo "<script type='text/javascript' src='".$EX_SCRIPTS."/call_server.js'></script>\n";
echo "<script type='text/javascript' src='".$EX_SCRIPTS."/set_rates.js'></script>\n";

EX_pageHead(); //standard page headings - after any scripts

//forms and display depend on process state; note, however, that the state was probably changed after entering
//the Main State Gate so this switch will see the next state in the process:
switch ($_STATE->status) {
case SELECT_PROJECT:

	echo $projects->set_list();

	break; //end SELECT_PROJECT status ----END STATE: EXITING FROM PROCESS----
case SELECT_PERSON:
	$checked = "";
	if ($_STATE->show_inactive) $checked = " checked";
?>
<p>
<form method="post" name="frmAction" id="frmAction_ID" action="<?php echo $_SERVER['SCRIPT_NAME']; ?>">
<input type='checkbox' name='chkInactive' value='show'<?php echo $checked; ?> onclick='this.form.submit()'>
Show inactive persons
<div style="visibility:hidden;height:1px;"><textarea name="txtPerson" id="txtPerson_ID"></textarea></div>
</form>
<table align='center' cellpadding='4' border='2' class="list">
  <tr><th>&nbsp;</th><th>Rate</th><th>Effective as of</th><th>Expires after</th></tr>
<?php
	$today = COM_NOW();
	foreach($_STATE->records as $person_id => $record) {
		$opacity = "1.0"; //opacity value
		if (!is_null($record["inactive_asof"])) {
			if (new DateTime($record["inactive_asof"]) <= $today) {
				if (!$_STATE->show_inactive) continue;
				$opacity = "0.5";
			}
		}
?>
  <tr style='opacity:<?php echo $opacity; ?>'>
    <td ID='<?php echo($person_id);?>' onclick='return submit_it(this);'><?php echo($record["name"]);?></td>
    <td><?php echo(number_format($record["rates"][0]["rate"],2));?></td>
    <td><?php echo($record["rates"][0]["eff"]);?></td>
    <td><?php echo($record["rates"][0]["exp"]);?></td>
  </tr>
<?php
	} ?>
</table>
</p>
<?php //end SELECT_PERSON status ----END STATUS PROCESSING----
	break;
case STATE::CHANGE:
?>
<p>
<table align='center' cellpadding='4' border='2' class="list">
  <tr><th style="width:100px">&nbsp;</th><th>Rate</th><th>Effective as of</th><th>Expires after</th></tr>
  <tr>
    <td id="BN_0" data-recid="0" onclick="init_row(this)" title="Click to add new rate"><img src="<?php
		echo $_SESSION["_SITE_CONF"]["_REDIRECT"]?>/images/add.png"></td>
    <td id="RT_0"></td>
    <td id="EF_0"></td>
    <td id="EX_0"></td>
  </tr>
<?php
	$counter = 1;
	foreach($_STATE->records[strval($_STATE->record_id)]["rates"] as $rate) {
		if (!isset($rate["rate"])) continue; ?>
  <tr>
    <td id='BN_<?php echo($counter."' data-recid='".$rate["ID"]);?>' onclick='return init_row(this)'><?php echo($counter);?></td>
    <td id='RT_<?php echo($counter."'>".number_format($rate["rate"],2));?></td>
    <td id='EF_<?php echo($counter."'>".$rate["eff"]);?></td>
    <td id='EX_<?php echo($counter."'>".$rate["exp"]);?></td>
<?php
		++$counter;
	} ?>
</table>
<?php
} //end select ($_STATE->status) ----END STATE: EXITING FROM PROCESS----

EX_pageEnd(); //standard end of page stuff
?>

