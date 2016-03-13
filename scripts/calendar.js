//copyright 2015 C.D.Price. Licensed under Apache License, Version 2.0
//See license text at http://www.apache.org/licenses/LICENSE-2.0

function CAL_set_day(day, date) {
  if (day.getAttribute("data-id") == "today") {
    day.style.backgroundColor = "Red";
  } else {
//    day.style.backgroundColor = "Beige";
  }
  day.draggable = "true";
  dd = day.innerHTML;
  if (dd.length == 1) dd = "0" + dd;
  day.setAttribute("data-date", date+"-"+dd);
  // Event Listener for when the drag interaction starts:
  day.addEventListener('dragstart', function(e) {
    e.dataTransfer.effectAllowed = 'copy';
    e.dataTransfer.setData('text', this.getAttribute("data-date"));
  });
}

function CAL_set_month(ID, yyyy, mm) {
  var day;
  var days;
  var month = document.getElementById(ID);
  month.style.width = "140px";
  for (var i = 0; i < month.rows.length; i++) {
    days = month.rows[i];
    for (var j = 0; j < days.cells.length; j++) {
      day = days.cells[j];
      if (day.getAttribute("name") == "calDay") {
        CAL_set_day(day, yyyy+"-"+mm);
      }
    }
  }
}

function CAL_set_drop(where, yyyy, mm, dd) {
  var date = document.getElementById(where);
  // Event Listener for when the dragged element enters the drop zone:
  date.addEventListener('dragenter', function(e) {
    this.className = "over";
  });
  // Event Listener for when the dragged element is over the drop zone:
  date.addEventListener('dragover', function(e) {
    if (e.preventDefault) {
      e.preventDefault();
    }
    e.dataTransfer.dropEffect = 'copy';
    return false;
  });
  // Event Listener for when the dragged element leaves the drop zone:
  date.addEventListener('dragleave', function(e) {
    this.className = "";
  });
  // Event Listener for when the dragged element dropped in the drop zone:
  date.addEventListener('drop', function(e) {
    if (e.preventDefault) e.preventDefault(); 
    if (e.stopPropagation) e.stopPropagation();
    this.className = "";
    var text = e.dataTransfer.getData('text');
    document.getElementById(yyyy).value = text.substr(0,4);
    document.getElementById(mm).value = text.substr(5,2);
    document.getElementById(dd).value = text.substr(8,2);
    return false;
  });
}

