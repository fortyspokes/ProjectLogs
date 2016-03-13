//copyright 2015-2016 C.D.Price. Licensed under Apache License, Version 2.0
//See license text at http://www.apache.org/licenses/LICENSE-2.0

function submit_it(me) {
  var myText = document.getElementById("txtPerson_ID");
  var myForm = document.getElementById("frmAction_ID");
  myText.innerHTML = me.id;
  myText.value = me.id;
  myForm.submit();
}

selectedRow = -1;
function init_row(me) {
  if (selectedRow > -1) return;
  selectedRow = me.id.charAt(3); //disallow another input line
  var cell = me;
  var cellGuts = "<button type='button' onclick='new_rate(" + selectedRow + ")'>";
  if (selectedRow == 0) {
    cellGuts += "Submit the new rate";
  } else {
    cellGuts += "Submit rate changes";
  }
  cellGuts += "</button>";
  cellGuts += "<br><button type='button' name='btnReset' onclick='return Reset()'>Cancel</button>";
  cell.innerHTML = cellGuts;

  cell = document.getElementById("RT_"+selectedRow);
  cellGuts = "<input type='text' name='txtRate' id='txtRate_ID' size='6' maxlength='6' class='number' value='";
  if (selectedRow == 0) {
	cellGuts += "0";
  } else {
	cellGuts += cell.innerHTML;
  }
  cellGuts += "'>";
  cell.innerHTML = cellGuts;

  cell = document.getElementById("EF_"+selectedRow);
  today = new Date();
  cellGuts = "<input type='text' name='txtEff' id='txtEff_ID' size='10' maxlength='10' class='number' value='";
  if (selectedRow ==0) {
    cellGuts += today.getFullYear() + "-" + today.getMonth() + "-" + today.getDate();
  } else {
    cellGuts += cell.innerHTML;
  }
  cellGuts += "'>";
  cell.innerHTML = cellGuts;

  cell = document.getElementById("EX_"+selectedRow);
//  today.setDate(today.getDate() + 365); //add a year
  cellGuts = "<input type='text' name='txtExp' id='txtExp_ID' size='10' maxlength='10' class='number' value='";
  if (selectedRow == 0) {
//    cellGuts += today.getFullYear() + "-" + today.getMonth() + "-" + today.getDate();
    cellGuts += "0";
  } else {
    cellGuts += cell.innerHTML;
  }
  cellGuts += "'>";
  cell.innerHTML = cellGuts;

  if (selectedRow != 0)
    document.getElementById("msgGreet_ID").innerHTML += ":<br>To delete, enter 0 for effective";
}

function new_rate(row) {
  var content;

  if (document.getElementById("txtEff_ID").value == "0") {
    if (!confirm("Are you sure you want to delete this rate record?")) return;
  }
  var content = "ID=" + document.getElementById("BN_"+selectedRow).getAttribute("data-recid");
  content += "&rate=" + document.getElementById("txtRate_ID").value;
  content += "&eff=" + document.getElementById("txtEff_ID").value;
  content += "&exp=" + document.getElementById("txtExp_ID").value;
  server_call("POST", content);
}

function Reset() {
	var URL_delim = "&"; if (IAm.indexOf("?") == -1 ) URL_delim = "?";
	window.location = IAm + URL_delim + "reset";
}

