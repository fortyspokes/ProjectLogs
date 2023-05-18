<?php
//copyright 2015-2016,2019,2022 C.D.Price. Licensed under Apache License, Version 2.0
//See license text at http://www.apache.org/licenses/LICENSE-2.0
if (!$_PERMITS->can_pass("set_rates")) throw_the_bum_out(NULL,"Evicted(".__LINE__."): no permit");

require_once "lib/field_edit.php";

//The Main State Gate cases:
define('LIST_PROJECTS',		STATE::INIT);
define('SELECT_PROJECT',		LIST_PROJECTS + 1);
define('SELECTED_PROJECT',		LIST_PROJECTS + 2);
define('LIST_PERSONS',		STATE::INIT + 10);
define('SELECT_PERSON',			LIST_PERSONS + 1);
define('SELECTED_PERSON',		LIST_PERSONS + 2);
define('RATE_DISPLAY',		STATE::INIT + 20);
define('RATE_CHANGE',			RATE_DISPLAY + 1);

//Main State Gate: (the while (1==1) allows a loop back through the switch using a 'break 1')
while (1==1) { switch ($_STATE->status) {
case LIST_PROJECTS:
	$_STATE->project_id = 0;
	$_STATE->close_date = false;
	$_STATE->show_inactive = false;
	require_once "lib/project_select.php";
	$projects = new PROJECT_SELECT($_PERMITS->restrict("set_rates"));
	$_STATE->project_select = serialize(clone($projects));
	if ($projects->selected) {
		$_STATE->init = LIST_PERSONS;
		$_STATE->status = SELECTED_PROJECT;
		break 1; //re-switch to SELECTED_PROJECT
	}
	$_STATE->msgGreet = "Select the ".ucfirst($projects->get_label("project"));
	Page_out();
	$_STATE->status = SELECT_PROJECT;
	break 2; //return to executive

case SELECT_PROJECT:
	require_once "lib/project_select.php"; //catches $_GET list refresh
	$projects = unserialize($_STATE->project_select);
	$projects->set_state();
	$_STATE->project_select = serialize(clone($projects));
case SELECTED_PROJECT:
	$_STATE->project_name = $projects->selected_name();

case LIST_PERSONS:
	$_STATE->set_a_gate(LIST_PERSONS); //for a 'goback' - sets status
	person_list();
	$_STATE->msgGreet = $_STATE->project_name."<br>Select a person";
	$_STATE->backup = LIST_PROJECTS; //set goback
	Page_out();
	$_STATE->status = SELECT_PERSON;
	$_STATE->goback_to(LIST_PROJECTS);
	break 2; //return to executive

case SELECT_PERSON:
	if ((!isset($_POST["txtPerson"])) || ($_POST["txtPerson"] == "")) {
		$inactive = $_STATE->show_inactive;
		$_STATE = $_STATE->goback_to(LIST_PERSONS, true);
		$_STATE->show_inactive = !$inactive;
		break 1;
	}
	record_select();
case SELECTED_PERSON:
	$_STATE->set_a_gate(SELECTED_PERSON); //for a 'goback' - sets status
	$_STATE->goback_to(LIST_PERSONS); //set goback
	$_STATE->msgGreet = $_STATE->project_name."<br>Rate history for ".$_STATE->records[strval($_STATE->record_id)]["name"];
	$_STATE->status = RATE_DISPLAY;
	Page_out();
	break 2; //return to executive

case RATE_DISPLAY:
	echo input_display($_GET["row"]); //the XMLHttpRequest responseText
	$_STATE->status = RATE_CHANGE;
	$_STATE->replace();
	$_STATE->push();
	exit(); //server_call return

case RATE_CHANGE:
	if (isset($_GET["reset"])) {
		$_STATE = $_STATE->goback_to(SELECTED_PERSON, true);
		person_list();
		break 1;
	}
	//The executive 'pulls' the state but it doesn't have the DATE_FIELD object def so that the
	//un-serialize doesn't work; may be a better way than re-doing the pull but it works for now:
	$_STATE = STATE_pull(); //'pull' the working state
	rate_change();
	echo $_STATE->msgStatus; //the XMLHttpRequest responseText
	$_STATE->replace();
	exit(); //server_call return

default:
	throw_the_bum_out(NULL,"Evicted(".$_STATE->ID."/".__LINE__."): invalid state=".$_STATE->status);
} } //while & switch
//End Main State Gate & return to executive

function person_list($person_id=-1) {
	global $_DB, $_STATE;

	//get each person in this org, then get their rate records; if no rate, return NULLs
	$sql = "SELECT c00.person_id, c00.lastname, c00.firstname,
						c02.rate_id, c02.rate, c02.effective_asof, c02.expire_after,
						c00.inactive_asof
			FROM (
				SELECT c00.person_id, c00.lastname, c00.firstname, c10.inactive_asof
				FROM ".$_DB->prefix."c00_person AS c00
				INNER JOIN ".$_DB->prefix."c10_person_organization AS c10
				ON c10.person_idref = c00.person_id
				WHERE c10.organization_idref = ".$_SESSION["organization_id"]."
				) AS c00
				LEFT OUTER JOIN (
				SELECT rate_id, person_idref, rate, effective_asof, expire_after
				FROM ".$_DB->prefix."c02_rate
				WHERE project_idref = ".$_STATE->project_id."
				) AS c02
				ON c00.person_id = c02.person_idref";
	if ($person_id > 0) $sql .= "
			WHERE c00.person_id = ".$person_id;
	$sql .= "
			ORDER BY c00.lastname, c00.person_id, c02.effective_asof DESC;";
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
				"inactive_asof"=>new DATE_FIELD($row_sav->inactive_asof), //provides formatting only
				"rates" => $rates,
				);
			$_STATE->records[strval($row_sav->person_id)] = $record;
			if ($EOF == 1) break; //all done
			$rates = array();
			$row_sav = $row;
		}
		$rates[] = array(
			"ID" => $row->rate_id,
			"update" => false,
			"rate" => $row->rate,
							//pagename,DBname,load from DB?,write to DB?,required?,maxlength,disabled,value
			"eff"=>new DATE_FIELD("txtEff","effective_asof",FALSE,FALSE,FALSE,0,FALSE,$row->effective_asof),
			"exp"=>new DATE_FIELD("txtExp","expire_after",FALSE,FALSE,FALSE,0,FALSE,$row->expire_after),
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

function input_display($row) { //fill out the line with input boxes then return
	global $_STATE;

	if ($row == 0) {
		$eff = new DATE_FIELD("txtEff","effective_asof",FALSE,FALSE,FALSE,0,FALSE,"");
		$exp = new DATE_FIELD("txtExp","expire_after",FALSE,FALSE,FALSE,0,FALSE,"");
	} else {
		person_list($_STATE->record_id); //get only this person's rates
		$eff = $_STATE->records[$_STATE->record_id]["rates"][$row - 1]["eff"];
		$exp = $_STATE->records[$_STATE->record_id]["rates"][$row - 1]["exp"];
	}

	$HTML = "@"; //eval
	$HTML .= "var cell = document.getElementById('BN_".$row."');\n";
	$HTML .= "var cellGuts = \"<button type='button' onclick='new_rate(".$row.")'>\";\n";
	if ($row == 0) {
		$HTML .= "cellGuts += \"Submit the new rate\";\n";
	} else {
		$HTML .= "cellGuts += \"Submit rate changes\";\n";
	}
	$HTML .= "cellGuts += \"</button>\";\n";
	$HTML .= "cellGuts += \"<br><button type='button' name='btnReset' onclick='return Reset()'>Cancel</button>\";\n";
	$HTML .= "cell.innerHTML = cellGuts;\n";

	$HTML .= "cell = document.getElementById('RT_".$row."');\n";
	$HTML .= "cellGuts = \"<input type='text' name='txtRate' id='txtRate_ID' size='6' maxlength='6' class='number' value='\";\n";
	if ($row == 0) {
		$HTML .= "cellGuts += \"0\";\n";
	} else {
		$HTML .= "cellGuts += cell.innerHTML;\n";
	}
	$HTML .= "cellGuts += \"'>\";\n";
	$HTML .= "cell.innerHTML = cellGuts;\n";

	$HTML .= "cell = document.getElementById('EF_".$row."');\n";
	$HTML .= "cellGuts = \"\";\n";
	foreach ($eff->HTML_input() as $line) {
		$HTML .= "cellGuts += \"".$line."\";\n";
	}
	$HTML .= "cell.innerHTML = cellGuts;\n";

	$HTML .= "cell = document.getElementById('EX_".$row."');\n";
	$HTML .= "cellGuts = \"\";\n";
	foreach ($exp->HTML_input() as $line) {
		$HTML .= "cellGuts += \"".$line."\";\n";
	}
	$HTML .= "cell.innerHTML = cellGuts;\n";

	foreach ($eff->POSTring() as $line) {
		$HTML .= "POSTdates.push(new Array(\"".$line[0]."\",\"".$line[1]."\"));\n";
	}
	foreach ($exp->POSTring() as $line) {
		$HTML .= "POSTdates.push(new Array(\"".$line[0]."\",\"".$line[1]."\"));\n";
	}

	if ($row != 0) {
		$HTML .= "document.getElementById('msgGreet_ID').innerHTML += \":<br>To delete, erase effective date\";\n";
	}

	return $HTML;
}

function add_rate() {
	global $_DB, $_STATE;

	if (is_null($_STATE->new_rate["eff"]->value)) {
		$_STATE->msgStatus = "!**Adding a new rate with 0 effective date makes no sense**";
		return false;
	}

	$eff = $_STATE->new_rate["eff"]->value;
	if (!is_null($_STATE->rates[1]["ID"])) { //have an existing rate
		if (!is_null($_STATE->rates[1]["exp"]->value)) { //rates[0] is the new rate
			$prior = $_STATE->rates[1]["exp"]->value;
			if (($eff <= $prior) && !isset($_STATE->replies["AP"])) {
				$_STATE->msgStatus = "?APEffective date precedes prior expiration, adjust the prior date?";
				return false;
			}
		}
		$prior = $_STATE->rates[1]["eff"]->value;
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

	$sql = "INSERT INTO ".$_DB->prefix."c02_rate (person_idref, project_idref, rate, effective_asof, expire_after)
			VALUES (".$_STATE->record_id.",".$_STATE->project_id.",".$_STATE->new_rate["rate"].
					",:effective,:expire);";
	$stmt = $_DB->prepare($sql);
	$stmt->bindValue(':effective', $_STATE->new_rate["eff"]->format("Y-m-d"), db_connect::PARAM_DATE);
	if (is_null($_STATE->new_rate["exp"]->value)) {
		$stmt->bindValue(':expire', null, db_connect::PARAM_DATE);
	} else {
		$stmt->bindValue(':expire', $_STATE->new_rate["exp"]->format('Y-m-d'), db_connect::PARAM_DATE);
	}
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

	//effective date:
	$eff_new = $_STATE->new_rate["eff"]->value;
	$eff_old = DateTime::createFromFormat('!Y-m-d', $row->effective_asof);
	if ($eff_new != $eff_old) {
		if (!is_null($_STATE->new_rate["exp"]->value)) {
			if ($eff_new > $_STATE->new_rate["exp"]->value) {
				$_STATE->msgStatus = "!**Update denied: effective date later than expiration**";
				return;
			}
		}
		if ($eff_new < $eff_old) {
			$prior = $_STATE->rate_ndx + 1;
			if ($prior < count($_STATE->rates)) { //a prior rate exists
				$prior_exp = $_STATE->rates[$prior]["exp"]->value;
				if (($eff_new <= $prior_exp) && !isset($_STATE->replies["UP"])) {
					$_STATE->msgStatus = "?UPEffective date precedes prior expiration, adjust the prior date?";
					return;
				}
				$prior_exp = clone($eff_new); //new prior expiration
				$prior_exp->sub(new DateInterval('P1D')); //subtract a day
				$prior_eff = $_STATE->rates[$prior]["eff"]->value;
				if ($prior_exp < $prior_eff) {
					$_STATE->msgStatus = "!**Update denied: adjusted prior expiration precedes prior effective**";
					return;
				}
				$_STATE->rates[$prior]["update"] = true;
				$_STATE->rates[$prior]["exp"]->value = $prior_exp;
			}
		} else { //$eff_new > $eff_old
			$sql = "SELECT timelog_id FROM ".$_DB->prefix."v00_timelog
					WHERE (person_id=".$_STATE->record_id.") AND (project_id=".$_STATE->project_id.")
					AND (logdate BETWEEN :eff_old AND :eff_new);";
			$stmt = $_DB->prepare($sql);
			$stmt->bindValue(':eff_old', $eff_old->format('Y-m-d'), db_connect::PARAM_DATE);
			$stmt->bindValue(':eff_new', $eff_new->format('Y-m-d'), db_connect::PARAM_DATE);
			$stmt->execute();
			if ($stmt->fetchObject()) {
				$_STATE->msgStatus = "!**Effective date change denied: time has been logged against this rate**";
				return;
			}
			$stmt->closeCursor();
		}
		$_STATE->rates[$_STATE->rate_ndx]["update"] = true;
		$_STATE->rates[$_STATE->rate_ndx]["eff"]->value = $eff_new;
	}

	//Expiration date:
	if ($_STATE->rate_ndx == 0) { //updating most recent rate record expiration
		if (!is_null($_STATE->new_rate["exp"]->value)) {
			$sql = "SELECT logdate FROM ".$_DB->prefix."v00_timelog
					WHERE (person_id=".$_STATE->record_id.") AND (project_id=".$_STATE->project_id.")
					AND logdate >= :expdate;";
			$stmt = $_DB->prepare($sql);
			$stmt->bindValue(':expdate', $_STATE->new_rate["exp"]->value, db_connect::PARAM_DATE);
			$stmt->execute();
			if ($stmt->fetchObject()) {
				$_STATE->msgStatus = "!**Update denied: time has been logged after this expiration**";
				return;
			}
			$stmt->closeCursor();
		}
		$_STATE->rates[$_STATE->rate_ndx]["exp"] = $_STATE->new_rate["exp"];
		$_STATE->rates[$_STATE->rate_ndx]["update"] = true;
	} else {
		$exp_new = $_STATE->new_rate["exp"]->value;
		$exp_old = DateTime::createFromFormat('!Y-m-d', $row->expire_after);
		if ($exp_new != $exp_old) {
			if ($exp_new > $exp_old) {
				$next = $_STATE->rate_ndx - 1;
				$next_eff = $_STATE->rates[$next]["eff"]->value;
				if (($exp_new >= $next_eff) && !isset($_STATE->replies["UN"])) {
					$_STATE->msgStatus = "?UNExpiration date later than next effective, adjust the next date?";
					return;
				}
				$next_eff = clone($exp_new); //new next effective
				$next_eff->add(new DateInterval('P1D')); //add a day
				if (!is_null($_STATE->rates[$next]["exp"]->value)) {
					$next_exp = $_STATE->rates[$next]["exp"]->value;
				} else {
					$next_exp = $next_eff;
				}
				if ($next_eff > $next_exp) {
					$_STATE->msgStatus = "!**Adjusted next effective greater than next expire**";
					return;
				}
				$_STATE->rates[$next]["update"] = true;
				$_STATE->rates[$next]["eff"]->value = $next_eff;
			} else { //$exp_new < $exp_old
				$sql = "SELECT timelog_id FROM ".$_DB->prefix."v00_timelog
						WHERE (person_id=".$_STATE->record_id.") AND (project_id=".$_STATE->project_id.")
						AND (logdate BETWEEN :exp_old AND :exp_new);";
				$stmt = $_DB->prepare($sql);
				$stmt->bindValue(':eff_old', $eff_old->format('Y-m-d'), db_connect::PARAM_DATE);
				$stmt->bindValue(':eff_new', $eff_new->format('Y-m-d'), db_connect::PARAM_DATE);
				$stmt->execute();
				if ($stmt->fetchObject()) {
					$_STATE->msgStatus = "!**expiration date change denied: time has been logged against this rate**";
					return;
				}
				$stmt->closeCursor();
			}
			$_STATE->rates[$_STATE->rate_ndx]["update"] = true;
			$_STATE->rates[$_STATE->rate_ndx]["exp"]->value = $exp_new;
		}
	}

	if ($_STATE->new_rate["rate"] != $row->rate) {
		$_STATE->rates[$_STATE->rate_ndx]["rate"] = $_STATE->new_rate["rate"];
		$_STATE->rates[$_STATE->rate_ndx]["update"] = true;
	}

	//make the changes:
	$sql = "UPDATE ".$_DB->prefix."c02_rate
			SET rate=:rate, effective_asof=:eff, expire_after=:exp
			WHERE rate_id=:ID;";
	$stmt = $_DB->prepare($sql);
	reset($_STATE->rates);
	foreach($_STATE->rates as $rate) {
		if ($rate["update"]) {
			$stmt->bindValue(':rate',$rate["rate"], PDO::PARAM_STR);
			$stmt->bindValue(':eff', $rate["eff"]->format('Y-m-d'), db_connect::PARAM_DATE);
			if (is_null($rate["exp"]->value)) {
				$stmt->bindValue(':exp', null, db_connect::PARAM_DATE);
			} else {
				$stmt->bindValue(':exp', $rate["exp"]->format('Y-m-d'), db_connect::PARAM_DATE);
			}
			$stmt->bindValue(':ID', $rate["ID"], PDO::PARAM_INT);
			$stmt->execute();
		}
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
	$eff = $_STATE->new_rate["eff"]->value;
	$exp = $_STATE->new_rate["exp"]->value;
	if (!is_null($eff)) {
		if (($today->diff($eff, true)->days > 365) && !isset($_STATE->replies["EF"])) {
			$_STATE->msgStatus = "?EFEffective date is more than a year away, accept it?";
			return false;
		}
	}

	if ((!is_null($exp)) && (!is_null($eff))) {
		if (($today->diff($exp, true)->days > 365) && !isset($_STATE->replies["EX"])) {
			$_STATE->msgStatus = "?EXExpiration date is more than a year away, accept it?";
			return false;
		}
		if ($exp <= $eff) {
			$_STATE->msgStatus = "!**New expiration date must follow effective date**";
			return false;
		}
	}

	$_STATE->msgStatus = "-"; //tell server_call to reset page
	return true;
}

function save_input() {
	global $_STATE;

	person_list($_STATE->record_id); //get only this person's rates
	if (!array_key_exists($_STATE->record_id, $_STATE->records))
		throw_the_bum_out(NULL,"Evicted(".__LINE__."): invalid person id"); //we're being spoofed

	$_STATE->new_rate = array(
		"ID" => $_POST["ID"],
		"update" => false,
		"rate" => $_POST["rate"],
		"eff" => new DATE_FIELD("txtEff","effective_asof",FALSE,FALSE,FALSE,0,FALSE,""),
		"exp" => new DATE_FIELD("txtExp","expire_after",FALSE,FALSE,FALSE,0,FALSE,""),
		);
	//this 'audit' inits the DATE_FIELD
	if (($msg = $_STATE->new_rate["eff"]->audit(false)) !== true) {
		$_STATE->msgStatus = "!Effective date: ".$msg;
		return false;
	}
	if (($msg = $_STATE->new_rate["exp"]->audit(false)) !== true) {
		$_STATE->msgStatus = "!Expiration date: ".$msg;
		return false;
	}

	$rates = $_STATE->records[$_STATE->record_id]["rates"];
	$ndx = 0;
	if ($_POST["ID"] == 0) { //adding
		$rate_rec = array("ID" => 0, "update" => false,);
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
	$_STATE->rates = $rates; //rates for this person (+ a possible new one)
	$_STATE->rate_ndx = $ndx;

	return true;
}

function rate_change() {
	global $_STATE;

	if ($_SERVER['REQUEST_METHOD'] == "POST") { //POST carries rate data
		if (!save_input()) return;
		$_STATE->replies = array(); //prepare for warnings
	} else {
		foreach ($_GET as $field => $reply) { //GET carries replies
			$_STATE->replies[$field] = $reply;
		}
	}

	if (!audit_input()) return;

	if ($_STATE->rates[$_STATE->rate_ndx]["ID"] == 0) {
		add_rate();
	} elseif (is_null($_STATE->new_rate["eff"]->value)) {
		delete_rate();
	} else {
		update_rate();
	}
}

function Page_out() {
	global $_DB, $_STATE;

	$scripts = array("call_server.js");
	EX_pageStart($scripts); //standard HTML page start stuff - insert SCRIPTS here

	switch ($_STATE->status) { //add javascript

	case LIST_PERSONS:
?>
<script language="JavaScript">
function select_person(me) {
	var myText = document.getElementById("txtPerson_ID");
	var myForm = document.getElementById("frmAction_ID");
	myText.innerHTML = me.id;
	myText.value = me.id;
	myForm.submit();
}
</script>
<?php
		break;

	case RATE_DISPLAY:
?>
<script language="JavaScript">
var selectedRow;
function open_row(me) {
	selectedRow = me.id.substr(3);
	var row;
	for (var ndx=0; ndx<=<?php echo count($_STATE->records[strval($_STATE->record_id)]["rates"]) ?>; ndx++) {
		row = document.getElementById("BN_"+ndx);
		if (row == null) break; //happens if no rates
		row.title = "";
		row.onclick = null;
		row.style.cursor = "default";
	}
	server_call("GET", "row="+selectedRow);
}
var POSTdates = []; //filled in by server call above
function new_rate(row) {
	var content;

	if (document.getElementById("txtEff_ID").value == "0") {
		if (!confirm("Are you sure you want to delete this rate record?")) return;
	}
	var content = "ID=" + document.getElementById("BN_"+selectedRow).getAttribute("data-recid");
	content += "&rate=" + document.getElementById("txtRate_ID").value;
	POSTdates.forEach(function(line) {
		content += "&" + line[0] + "=" + eval(line[1]);
	});

	server_call("POST", content);
}
function Reset() {
	var URL_delim = "&"; if (IAm.indexOf("?") == -1 ) URL_delim = "?";
	window.location = IAm + URL_delim + "reset";
}
</script>
<?php
		break;
	} //end switch ($_STATE->status) for javascript

	EX_pageHead(); //standard page headings - after any scripts

	switch ($_STATE->status) {

	case LIST_PROJECTS:
		global $projects;
		echo $projects->set_list();
		break; //end LIST_PROJECTS status ----END STATE: EXITING FROM PROCESS----

	case LIST_PERSONS:
		$checked = "";
		if ($_STATE->show_inactive) $checked = " checked";
?>
<p>
<form method="post" name="frmAction" id="frmAction_ID" action="<?php echo $_SESSION["IAm"]; ?>">
<input type='checkbox' name='chkInactive' value='show'<?php echo $checked; ?> onclick='this.form.submit()'>
Show inactive persons
<div style="visibility:hidden;height:1px;"><textarea name="txtPerson" id="txtPerson_ID"></textarea></div>
</form>
<table align='center' cellpadding='4' border='2' class="list">
  <tr><th>&nbsp;</th><th>Rate</th><th>Effective as of</th><th>Expires after</th></tr>
<?php
		$today = COM_NOW();
		$title = "Click to select";
		foreach($_STATE->records as $person_id => $record) {
			$opacity = "1.0"; //opacity value
			$inact = "";
			if (!is_null($record["inactive_asof"]->value)) {
				if ($record["inactive_asof"]->value <= $today) {
					if (!$_STATE->show_inactive) continue;
					$opacity = "0.5";
				$inact = "; inactive as of ".$record["inactive_asof"]->format();
				}
			}
?>
  <tr title='<?php echo($title.$inact); ?>' style='opacity:<?php echo $opacity; ?>'>
    <td ID='<?php echo($person_id);?>' onclick='return select_person(this);'><?php echo($record["name"]);?></td>
    <td><?php
    		if (is_null($record["rates"][0]["rate"])) {
    			echo "N/A";
    		} else {
    			echo(number_format($record["rates"][0]["rate"],2));
    		}
    	?></td>
    <td><?php echo($record["rates"][0]["eff"]->format());?></td>
    <td><?php echo($record["rates"][0]["exp"]->format());?></td>
  </tr>
<?php
		} //end foreach
?>
</table>
</p>
<?php
		break; //end LIST_PERSONS status ----END STATUS PROCESSING----

	case RATE_DISPLAY:
		global $_VERSION;
?>
<p>
<table align='center' cellpadding='4' border='2' class="list">
  <tr><th style="width:100px">&nbsp;</th><th>Rate</th><th>Effective as of</th><th>Expires after</th></tr>
  <tr>
    <td id="BN_0" data-recid="0" onclick="open_row(this)" title="Click to add new rate" style="cursor:pointer">
      <img src="<?php echo $_SESSION["BUTLER"]; ?>?IAm=IG&file=add.png&ver=<?php echo $_VERSION; ?>">
    </td>
    <td id="RT_0"></td>
    <td id="EF_0"></td>
    <td id="EX_0"></td>
  </tr>
<?php
		$counter = 1;
		foreach($_STATE->records[strval($_STATE->record_id)]["rates"] as $rate) {
			if (!isset($rate["rate"])) continue; ?>
  <tr>
    <td id='BN_<?php echo($counter."' data-recid='".$rate["ID"]);?>' onclick='return open_row(this)' title='Click to update rate' style='cursor:pointer'><?php echo($counter);?></td>
    <td id='RT_<?php echo($counter."'>".number_format($rate["rate"],2));?></td>
    <td id='EF_<?php echo($counter."'>".$rate["eff"]->format());?></td>
    <td id='EX_<?php echo($counter."'>".$rate["exp"]->format());?></td>
<?php
			++$counter;
		}
?>
</table>
<?php

		break; //end RATE_DISPLAY status ----END STATUS PROCESSING----

	default:
		throw_the_bum_out(NULL,"Evicted(".$_STATE->ID."/".__LINE__."): invalid state=".$_STATE->status);

	} //end select ($_STATE->status) ----END STATE: EXITING FROM PROCESS----

	EX_pageEnd(); //standard end of page stuff

} //end Page_out()
?>
