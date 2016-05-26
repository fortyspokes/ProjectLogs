<?php
//copyright 2015-2016 C.D.Price. Licensed under Apache License, Version 2.0
//See license text at http://www.apache.org/licenses/LICENSE-2.0

require_once "permits.php";

//These modules are all found in the _INCLUDEd 'main' directory so will have 'main/' prepended
//to the page path
define ('KEYLENGTH', 2);
define ('MENU', 0);
define ('PAGE', 1);
define ('PERMIT', 2);
define ('PRE_EXEC', 3);
//					MENU					PAGE					PERMIT				PRE_EXEC
$EX_staff = array(
"AB" => array('About',					"about.php",			'',					""		),
"AC" => array('Account Edit',			"account_edit.php",		'account_edit',		""		),
"AG" => array('Accounting group Edit',	"accounting_edit.php",	'accounting_edit',	""		),
"CG" => array('Css file get (service)',	"service/css_get.php",	'',					""		),
"CF" => array('Site Config',			"config.php",			PERMITS::_SUPERUSER,""		),
//"EE" => array('Event Log Edit',			"eventlog.php",			'edit_logs',		'$_EDIT=true;'),
"EL" => array('Event Log Entry',		"eventlog.php",			'',					'$_EDIT=false;'),
"EN" => array('Extension Executive',	"service/ext_exec.php",	'',					""		),
"ET" => array('Experiment',				"experiment.php",		PERMITS::_SUPERUSER,""		),
"EV" => array('Event Edit',				"event_edit.php",		'event_edit',		""		),
"EX" => array('Executive',				"service/executive.php",'',					""		),
"HD" => array('Head frame',				"service/head.php",		'',					""		),
"IG" => array('Image get (service)',	"service/image_get.php",'',					""		),
"LG" => array('Org logo get (service)',	"service/org_logo_get.php",'',				""		),
"LI" => array('Login',					"service/login.php",	'',					""		),
"LC" => array('Prune Logs',				"logs_prune.php",		'logs_prune',		""		),//Logs Cut?
"LP" => array('Download Logs',			"logs_put.php",			'project_logs',		""		),
"MA" => array('Main frame',				"service/main.php",		'',					""		),
"MU" => array('Menu frame',				"service/menu.php",		'',					""		),
"OE" => array('Org Edit',				"org_edit.php",			'org_edit',			""		),
"OS" => array('Org Select',				"org_select.php",		'',					""		),
"PE" => array('Person Edit',			"person_edit.php",		'',					""		),
"PI" => array('phpInfo',				"phpinfo.php",			PERMITS::_SUPERUSER,""		),
"PJ" => array('Project Edit',			"project_edit.php",		'project_edit',		""		),
"PM" => array('Grant/revoke permits',	"assign_permits.php",	'assign_permits',	""		),
"RG" => array('Repository Get',			"repository.php",		'repository_get',	'$_UPLOAD=false;'),
"RP" => array('Repository Put',			"repository.php",		'repository_put',	'$_UPLOAD=true;'),
"PR" => array('Property Admin',			"property_admin.php",	'property_admin',	""		),
"RC" => array('Refresh CSV',			"refresh_csv.php",		PERMITS::_SUPERUSER,""		),
"SC" => array('Save CSV',				"save_csv.php",			PERMITS::_SUPERUSER,""		),
"SG" => array('Script get (service)',	"service/script_get.php",'',				""		),
"SR" => array('Set Rates',				"set_rates.php",		'set_rates',		""		),
"ST" => array('Subtask Edit',			"subtask_edit.php",		'subtask_edit',		""		),
"TK" => array('Task Edit',				"task_edit.php",		'task_edit',		""		),
"TE" => array('Time Log Edit',			"timelog.php",			'edit_logs',		'$_EDIT=true;'),
"TL" => array('Time Log Entry',			"timelog.php",			'',					'$_EDIT=false;'),
"TR" => array('Download Task Report',	"taskreport_put.php",	'reports',			""		),
"UP" => array('Upyear Timelog',			"upyear_timelog.php",	PERMITS::_SUPERUSER,""		),
"XE" => array('Expense Log Edit',		"expenselog.php",		'edit_logs',		'$_EDIT=true;'),
"XL" => array('Expense Log Entry',		"expenselog.php",		'',					'$_EDIT=false;'),
);

$MENU_LIST = array( //the standard application menu list: (altered/replaced by preferences)
	"TL", //Time Log Entry
	"PE", //Person Edit
	"EL", //Event Log Entry (Classes)
	"XL", //Expense Log Entry
	"TE", //Edit time Logs (for any person)
	"XE", //Edit expense logs
	"LP", //Download logs
	"TR", //Download Task Report
	"SR", //Set Rates
	"PJ", //Project Edit
	"TK", //Task Edit
	"ST", //Subtask Edit
	"AG", //Accounting group Edit
	"AC", //Account Edit
	"EV", //Event Edit
	"RG", //Get repository
	"RP", //Put repository
	);
$ADMIN_LIST = array( //the admin menu items:
	"PM", //Grant/revoke permits
	"PR", //Property Admin
	"OE", //Org Edit
	"OS", //Org Select
	"CF", //Site Config
	"LC", //Logs Prune (Cut)
	);
if ($_SESSION["_SITE_CONF"]["RUNLEVEL"] > 0) { //can't do this stuff in production
	$ADMIN_LIST = array_merge($ADMIN_LIST, array(
		"RC", //Refresh CSV
		"SC", //Save CSV
		"UP", //Upyear Timelog
		"ET", //Experiment
		"PI", //phpInfo
		));
}
$ADMIN_LIST[] = "AB"; //'About' page - always last in the menu

//The 'audit' functions called by menu_list.php:
function menu_list_OS(&$staff) { //Check "Org Select" for multiple orgs
	global $org_count;
	if ($org_count > 1) return true;
	return false;
}
function menu_list_PI(&$staff) { //phpInfo
	if (($_SESSION["person_id"] == 0) || ($_SESSION["_SITE_CONF"]["RUNLEVEL"] == 1))
		return true;
	return false;
}
function menu_list_AB(&$staff) { //add PAGETITLE to 'About'
	$staff[MENU] .= " ".$_SESSION["_SITE_CONF"]["PAGETITLE"];
	return true;
}

?>
