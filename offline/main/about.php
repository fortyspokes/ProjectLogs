<?php
//copyright 2016 C.D.Price. Licensed under Apache License, Version 2.0
//See license text at http://www.apache.org/licenses/LICENSE-2.0

EX_pageStart(); //standard HTML page start stuff - insert scripts here
EX_pageHead(); //standard page headings - after any scripts
?>
<br>
<br>
<div align="center">
<div class="greet">
<?php echo $_SESSION["_SITE_CONF"]["PAGETITLE"]; ?><br><br>
an implememtation of<br><br>
ProjectLogs<br><br>
Version <?php echo $_VERSION; ?><br><br>
</div>
For more infomation, go to <a href="https://github.com/fortyspokes/ProjectLogs/tree/v<?php echo $_VERSION; ?>">github.com</a>
<br><br>Licensed under Apache License, Version 2.0<br><br>
See license text at <a href="http://www.apache.org/licenses/LICENSE-2.0">apache.org</a><br><br>
<?php
EX_pageEnd(); //standard end of page stuff
?>
</div>
