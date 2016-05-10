<?php

class TABLE {
	public $name;
	public $idname;
	public $fields;
	public $type; //'t'=table; 'v'=view

	function __construct($iname, $iidname, $ifields, $itype="t") {
		$this->name = $iname;
		$this->idname = $iidname;
		$this->fields = $ifields;
		$this->type = $itype;
	}
}
class TFIELD {
	public $type;
	public $editor;

	function __construct($itype, $ieditor="") {
		$this->type = $itype;
		$this->editor = $ieditor;
	}
}

function DB_tables($prefix="") {

	$list = array();

	$fields = array(
		"organization_id" =>		new TFIELD(PDO::PARAM_INT),
		"name" =>					new TFIELD(PDO::PARAM_STR),
		"description" =>			new TFIELD(PDO::PARAM_STR,"string"),
//		"logo" =>					new TFIELD(db_connect::PARAM_LOB),
		"logo_type" =>				new TFIELD(PDO::PARAM_STR),
		"currency_idref" =>			new TFIELD(PDO::PARAM_INT),
		"timezone" =>				new TFIELD(PDO::PARAM_INT),
		"timestamp" =>				new TFIELD(db_connect::PARAM_DATE)
		);
	$list["organization"] = new TABLE(
		$prefix."a00_organization", "organization_id", $fields);

	$fields = array(
		"project_id" =>				new TFIELD(PDO::PARAM_INT),
		"organization_idref" =>		new TFIELD(PDO::PARAM_INT),
		"accounting_idref" =>		new TFIELD(PDO::PARAM_INT),
		"name" =>					new TFIELD(PDO::PARAM_STR),
		"description" =>			new TFIELD(PDO::PARAM_STR,"string"),
		"comment" =>				new TFIELD(PDO::PARAM_STR,"string"),
		"budget" =>					new TFIELD(PDO::PARAM_STR, "money"),
		"budget_exp" =>				new TFIELD(PDO::PARAM_STR, "money"),
		"budget_by" =>				new TFIELD(PDO::PARAM_STR),
		"mileage" =>				new TFIELD(PDO::PARAM_STR, "money"),
		"inactive_asof" =>			new TFIELD(db_connect::PARAM_DATE,"date"),
		"close_date" =>				new TFIELD(db_connect::PARAM_DATE,"date"),
		"timestamp" =>				new TFIELD(db_connect::PARAM_DATE)
		);
	$list["project"] = new TABLE(
		$prefix."a10_project", "project_id", $fields);

	$fields = array(
		"task_id" =>				new TFIELD(PDO::PARAM_INT),
		"project_idref" =>			new TFIELD(PDO::PARAM_INT),
		"name" =>					new TFIELD(PDO::PARAM_STR),
		"description" =>			new TFIELD(PDO::PARAM_STR,"string"),
		"budget" =>					new TFIELD(PDO::PARAM_STR, "money"),
		"budget_exp" =>				new TFIELD(PDO::PARAM_STR, "money"),
		"inactive_asof" =>			new TFIELD(db_connect::PARAM_DATE,"date"),
		"timestamp" =>				new TFIELD(db_connect::PARAM_DATE)
		);
	$list["task"] = new TABLE(
		$prefix."a12_task","task_id", $fields);

	$fields = array(
		"subtask_id" =>				new TFIELD(PDO::PARAM_INT),
		"task_idref" =>				new TFIELD(PDO::PARAM_INT),
		"name" =>					new TFIELD(PDO::PARAM_STR),
		"description" =>			new TFIELD(PDO::PARAM_STR,"string"),
		"extension" =>				new TFIELD(PDO::PARAM_STR),
		"inactive_asof" =>			new TFIELD(db_connect::PARAM_DATE,"date"),
		"timestamp" =>				new TFIELD(db_connect::PARAM_DATE)
		);
	$list["subtask"] = new TABLE(
		$prefix."a14_subtask","subtask_id", $fields);

	$fields = array(
		"accounting_id" =>			new TFIELD(PDO::PARAM_INT),
		"organization_idref" =>		new TFIELD(PDO::PARAM_INT),
		"name" =>					new TFIELD(PDO::PARAM_STR),
		"description" =>			new TFIELD(PDO::PARAM_STR),
		"comment" =>				new TFIELD(PDO::PARAM_STR,"string"),
		"timestamp" =>				new TFIELD(db_connect::PARAM_DATE)
		);
	$list["accounting"] = new TABLE(
		$prefix."a20_accounting","accounting_id", $fields);

	$fields = array(
		"account_id" =>				new TFIELD(PDO::PARAM_INT),
		"accounting_idref" =>		new TFIELD(PDO::PARAM_INT),
		"name" =>					new TFIELD(PDO::PARAM_STR),
		"description" =>			new TFIELD(PDO::PARAM_STR,"string"),
		"inactive_asof" =>			new TFIELD(db_connect::PARAM_DATE,"date"),
		"timestamp" =>				new TFIELD(db_connect::PARAM_DATE)
		);
	$list["account"] = new TABLE(
		$prefix."a21_account","account_id", $fields);

	$fields = array(
		"event_id" =>				new TFIELD(PDO::PARAM_INT),
		"project_idref" =>			new TFIELD(PDO::PARAM_INT),
		"name" =>					new TFIELD(PDO::PARAM_STR),
		"description" =>			new TFIELD(PDO::PARAM_STR,"string"),
		"budget" =>					new TFIELD(PDO::PARAM_STR, "money"),
		"inactive_asof" =>			new TFIELD(db_connect::PARAM_DATE,"date"),
		"timestamp" =>				new TFIELD(db_connect::PARAM_DATE)
		);
	$list["event"] = new TABLE(
		$prefix."a30_event","event_id", $fields);

	$fields = array(
		"timelog_id" =>				new TFIELD(PDO::PARAM_INT),
		"activity_idref" =>			new TFIELD(PDO::PARAM_INT),
		"person_idref" =>			new TFIELD(PDO::PARAM_INT),
		"subtask_idref" =>			new TFIELD(PDO::PARAM_INT),
		"account_idref" =>			new TFIELD(PDO::PARAM_INT),
		"type" =>					new TFIELD(PDO::PARAM_STR),
		"logdate" =>				new TFIELD(db_connect::PARAM_DATE,"date"),
		"hours" =>					new TFIELD(db_connect::PARAM_STR),
		"timestamp" =>				new TFIELD(db_connect::PARAM_DATE)
		);
	$list["timelog"] = new TABLE(
		$prefix."b00_timelog","timelog_id", $fields);

	$fields = array(
		"activity_id" =>			new TFIELD(PDO::PARAM_INT),
		"description" =>			new TFIELD(PDO::PARAM_STR,"string"),
		"timestamp" =>				new TFIELD(db_connect::PARAM_DATE)
		);
	$list["activity"] = new TABLE(
		$prefix."b02_activity","activity_id", $fields);

	$fields = array(
		"eventlog_id" =>			new TFIELD(PDO::PARAM_INT),
		"event_idref" =>			new TFIELD(PDO::PARAM_INT),
		"person_idref" =>			new TFIELD(PDO::PARAM_INT),
		"account_idref" =>			new TFIELD(PDO::PARAM_INT),
		"session_count" =>			new TFIELD(db_connect::PARAM_INT),
		"attendance" =>				new TFIELD(db_connect::PARAM_INT),
		"logdate" =>				new TFIELD(db_connect::PARAM_DATE,"date"),
		"comments" =>				new TFIELD(PDO::PARAM_STR),
		"timestamp" =>				new TFIELD(db_connect::PARAM_DATE)
		);
	$list["eventlog"] = new TABLE(
		$prefix."b10_eventlog","eventlog_id", $fields);

	$fields = array(
		"expenselog_id" =>			new TFIELD(PDO::PARAM_INT),
		"activity_idref" =>			new TFIELD(PDO::PARAM_INT),
		"person_idref" =>			new TFIELD(PDO::PARAM_INT),
		"subtask_idref" =>			new TFIELD(PDO::PARAM_INT),
		"account_idref" =>			new TFIELD(PDO::PARAM_INT),
		"type" =>					new TFIELD(PDO::PARAM_STR),
		"logdate" =>				new TFIELD(db_connect::PARAM_DATE,"date"),
		"amount" =>					new TFIELD(PDO::PARAM_STR, "money"),
		"timestamp" =>				new TFIELD(db_connect::PARAM_DATE)
		);
	$list["expenselog"] = new TABLE(
		$prefix."b20_expenselog","expenselog_id", $fields);

	$fields = array(
		"person_id" =>				new TFIELD(PDO::PARAM_INT),
		"lastname" =>				new TFIELD(db_connect::PARAM_STR,"string"),
		"lastsoundex" =>			new TFIELD(db_connect::PARAM_STR),
		"firstname" =>				new TFIELD(db_connect::PARAM_STR,"string"),
		"loginname" =>				new TFIELD(db_connect::PARAM_STR),
		"password" =>				new TFIELD(db_connect::PARAM_STR),
		"email" =>					new TFIELD(db_connect::PARAM_STR),
		"timestamp" =>				new TFIELD(db_connect::PARAM_DATE)
		);
	$list["person"] = new TABLE(
		$prefix."c00_person","person_id", $fields);

	$fields = array(
		"rate_id" =>				new TFIELD(PDO::PARAM_INT),
		"person_idref" =>			new TFIELD(PDO::PARAM_INT),
		"project_idref" =>			new TFIELD(PDO::PARAM_INT),
		"rate" =>					new TFIELD(PDO::PARAM_STR, "money"),
		"effective_asof" =>			new TFIELD(db_connect::PARAM_DATE,"date"),
		"expire_after" =>			new TFIELD(db_connect::PARAM_DATE,"date"),
		"timestamp" =>				new TFIELD(db_connect::PARAM_DATE)
		);
	$list["rate"] = new TABLE(
		$prefix."c02_rate","rate_id", $fields);

	$fields = array(
		"person_organization_id" => new TFIELD(PDO::PARAM_INT),
		"person_idref" =>			new TFIELD(PDO::PARAM_INT),
		"organization_idref" =>		new TFIELD(PDO::PARAM_INT),
		"inactive_asof"	=>			new TFIELD(db_connect::PARAM_DATE,"date"),
		"timestamp" =>				new TFIELD(db_connect::PARAM_DATE)
		);
	$list["person_organization"] = new TABLE(
		$prefix."c10_person_organization","person_organization_id", $fields);

	$fields = array(
		"person_permit_id" =>		new TFIELD(PDO::PARAM_INT),
		"person_idref" =>			new TFIELD(PDO::PARAM_INT),
		"permit_idref" =>			new TFIELD(PDO::PARAM_INT),
		"organization_idref" =>		new TFIELD(PDO::PARAM_INT),
		"project_idref" =>			new TFIELD(PDO::PARAM_INT),
		"timestamp" =>				new TFIELD(db_connect::PARAM_DATE)
		);
	$list["person_permit"] = new TABLE(
		$prefix."c20_person_permit","person_permit_id", $fields);

	$fields = array(
		"permit_id" =>				new TFIELD(PDO::PARAM_INT),
		"name" =>					new TFIELD(PDO::PARAM_STR),
		"description" =>			new TFIELD(PDO::PARAM_STR,"string"),
		"comment" =>				new TFIELD(PDO::PARAM_STR,"string"),
		"grade" =>					new TFIELD(PDO::PARAM_INT),
		"timestamp" =>				new TFIELD(db_connect::PARAM_DATE)
		);
	$list["permit"] = new TABLE(
		$prefix."d01_permit","permit_id", $fields);

	$fields = array(
		"currency_id" =>			new TFIELD(PDO::PARAM_INT),
		"name" =>					new TFIELD(PDO::PARAM_STR),
		"symbol" =>					new TFIELD(PDO::PARAM_STR),
		"decimal_cnt" =>			new TFIELD(PDO::PARAM_INT)
		);
	$list["currency"] = new TABLE(
		$prefix."d02_currency","currency_id", $fields);

	$fields = array(
		"preferences_id" =>			new TFIELD(PDO::PARAM_INT),
		"organization_idref" =>		new TFIELD(PDO::PARAM_INT),
		"project_idref" =>			new TFIELD(PDO::PARAM_INT),
		"person_idref" =>			new TFIELD(PDO::PARAM_INT),
		"name" =>					new TFIELD(PDO::PARAM_STR),
		"prefer" =>					new TFIELD(PDO::PARAM_STR),
		"timestamp" =>				new TFIELD(db_connect::PARAM_DATE)
		);
	$list["preferences"] = new TABLE(
		$prefix."d10_preferences","preferences_id", $fields);

	$fields = array(
		"property_id" =>			new TFIELD(PDO::PARAM_INT),
		"organization_idref" =>		new TFIELD(PDO::PARAM_INT),
		"name" =>					new TFIELD(PDO::PARAM_STR),
		"description" =>			new TFIELD(PDO::PARAM_STR,"string"),
		"timestamp" =>				new TFIELD(db_connect::PARAM_DATE)
		);
	$list["property"] = new TABLE(
		$prefix."e00_property","property_id", $fields);

	$fields = array(
		"prop_value_id" =>			new TFIELD(PDO::PARAM_INT),
		"property_idref" =>			new TFIELD(PDO::PARAM_INT),
		"name" =>					new TFIELD(PDO::PARAM_STR),
		"description" =>			new TFIELD(PDO::PARAM_STR,"string"),
		"timestamp" =>				new TFIELD(db_connect::PARAM_DATE)
		);
	$list["prop_value"] = new TABLE(
		$prefix."e02_prop_value","prop_value_id", $fields);

	$fields = array(
		"prop_element_id" =>		new TFIELD(PDO::PARAM_INT),
		"prop_value_idref" =>		new TFIELD(PDO::PARAM_INT),
		"element_table" =>			new TFIELD(PDO::PARAM_STR),
		"element_idref" =>			new TFIELD(PDO::PARAM_INT),
		"timestamp" =>				new TFIELD(db_connect::PARAM_DATE)
		);
	$list["prop_element"] = new TABLE(
		$prefix."e04_prop_element","prop_element_id", $fields);

	return $list;

}
?>
