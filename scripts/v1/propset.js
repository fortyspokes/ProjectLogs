//copyright 2016 C.D.Price. Licensed under Apache License, Version 2.0
//See license text at http://www.apache.org/licenses/LICENSE-2.0

LoaderS.push('PS_init_cells();');

function PS_init_cells() {
	var rows = document.getElementById('tblLog').rows;
	var cell = rows[1].cells[0]; //add row 'button'
	cell.addEventListener("click", begin, true)
	for (var rndx=2; rndx<rows.length; rndx++) { //skip header and add rows
		var cells = rows[rndx].cells;
		for (var cndx=0; cndx<cells.length; cndx++) {
			var title = "";
			cell = cells[cndx];
			switch (cell.id.substr(0,3)) {
			case "BN_":
				if ((cell.title == '') || (cell.title === null) || (cell.title === undefined)) {
					cell.title = "Click to change/delete value";
					cell.addEventListener("click", begin, true)
				}
				break
			case "VA_":
				if (cell.getAttribute("data-recid") > 0) {
					title = "\nShift/Click to re-select";
					cell.addEventListener("click", beginShift, true)
				} //fall thru
			case "NM_":
				title = "Double click to get a full description" + title;
				cell.title = title;
				cell.ondblclick = new Function("get_desc(this)");
				break
			}
		}
	}
}

var myCell;
var set_desc; //the callback to set description
function get_desc(me,service) {
	//set defaults:
	service = service !== undefined ?  service : set_title;
	myCell = me;
	set_desc = service;
	var content = "getdesc=" + me.id.substr(0,2) + "&ID=" + Math.abs(me.getAttribute("data-recid"));
	server_call("GET", content);
}
function got_desc(desc) {
	set_desc(myCell,desc);
}
function set_title(who,desc) {
	var ndx = who.title.indexOf('\n');
	if (ndx == -1) ndx = who.title.length;
	who.title = desc + who.title.slice(ndx);
	alert(who.title);
}

function beginShift(evt) { //confirm shift key before doing begin()
	if (evt.shiftKey) { begin(evt); }
}
var captions = {loading:"loading, please wait..."};
on_path = false; //reset on reload of page
function begin(evt) {
	if (on_path) return; //update in process
	on_path = true;
	server_set_dbug(); //clear out the debug area
	proceed(evt.currentTarget,evt.currentTarget.id.substr(3));
}
function proceed(me,row,interim) {
	if (interim === undefined) {
		me.innerHTML = captions.loading;
	} else {
		me.innerHTML = interim;
	}
	var content = "agent="+me.id.substr(0,2);
	content += "&row="+row;
	content += "&id="+get_cell_recid("BN_"+row);
	server_call("GET",content);
}

var submitRow = -1;
function mouseDown(row) {
	submitRow = row;
}

function get_cell_recid(cellID) {
	var cell = document.getElementById(cellID);
	if ((cell === undefined) || (cell == null)) return "0";
	return cell.getAttribute("data-recid");
}

var awaitingServer = false;
function changes(row) {
	if (awaitingServer) return;
	if (get_cell_recid("VA_"+row) == 0) {
		if (!confirm("Are you sure you want to delete the property?")) return;
	}
	var content = "row="+row;
	content += "&id="+get_cell_recid("BN_"+row);
	content += "&name="+get_cell_recid("NM_"+row);
	content += "&value="+get_cell_recid("VA_"+row);

	awaitingServer = true;
	server_call("POST", content);
	awaitingServer = false;
}

function Reset() {
	var URL_delim = "&"; if (IAm.indexOf("?") == -1 ) URL_delim = "?";
	window.location = IAm + URL_delim + "reset";
}

