<?php
//copyright 2015-2016 C.D.Price. Licensed under Apache License, Version 2.0
//See license text at http://www.apache.org/licenses/LICENSE-2.0
define ('MENU', 0);
define ('PAGE', 1);
define ('PERMIT', 2);
define ('PRE_EXEC', 3);
//					MENU					PAGE					PERMIT				PRE_EXEC
$EX_staff = array(
"AC" => array('Account Edit',			"account_edit.php",		'account_edit',		""			),
"AG" => array('Accounting group Edit',	"accounting_edit.php",	'accounting_edit',	""			),
"CF" => array('Site Config',			"config.php",			PERMITS::_SUPERUSER,""			),
"EE" => array('Event Log Edit',			"eventlog.php",			'edit_logs',		'$_EDIT=true;'),
"EL" => array('Event Log Entry',		"eventlog.php",			'',					'$_EDIT=false;'),
"EV" => array('Event Edit',				"event_edit.php",		'event_edit',		""			),
//"EX" => 'experiment.php' but is loaded directly without going through the executive
"LC" => array('Prune Logs',				"logs_prune.php",		'logs_prune',		""			),//or Logs Cut?
"LP" => array('Download Logs',			"logs_put.php",			'project_logs',		""			),
"OE" => array('Org Edit',				"org_edit.php",			'org_edit',			""			),
"OS" => array('Org Select',				"org_select.php",		'',					""			),
"PE" => array('Person Edit',			"person_edit.php",		'',					""			),
"PI" => array('phpInfo',				"phpinfo.php",			PERMITS::_SUPERUSER,""			),
"PJ" => array('Project Edit',			"project_edit.php",		'project_edit',		""			),
"PM" => array('Grant/revoke permits',	"assign_permits.php",	'assign_permits',	""			),
"PR" => array('Property Admin',			"property_admin.php",	'property_admin',	""			),
"RC" => array('Refresh CSV',			"refresh_csv.php",		PERMITS::_SUPERUSER,""			),
"SC" => array('Save CSV',				"save_csv.php",			PERMITS::_SUPERUSER,""			),
"SR" => array('Set Rates',				"set_rates.php",		'set_rates',		""			),
"ST" => array('Subtask Edit',			"subtask_edit.php",		'subtask_edit',		""			),
"TK" => array('Task Edit',				"task_edit.php",		'task_edit',		""			),
"TE" => array('Time Log Edit',			"timelog.php",			'edit_logs',		'$_EDIT=true;'),
"TL" => array('Time Log Entry',			"timelog.php",			'',					'$_EDIT=false;'),
"TR" => array('Download Task Report',	"taskreport_put.php",	'reports',			""			),
"UP" => array('Upyear Timelog',			"upyear_timelog.php",	PERMITS::_SUPERUSER,""			),
"XE" => array('Expense Log Edit',		"expenselog.php",		'edit_logs',		'$_EDIT=true;'),
"XL" => array('Expense Log Entry',		"expenselog.php",		'',					'$_EDIT=false;'),
);
?>
