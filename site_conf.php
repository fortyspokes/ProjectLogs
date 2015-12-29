; <?php header("Location: https://".$_SERVER["HTTP_HOST"]); ?> prevents hacking
;copyright 2015 C.D.Price. Licensed under Apache License, Version 2.0
;See license text at http://www.apache.org/licenses/LICENSE-2.0

;                          The site specific configuration file

;If $_SESSION["_SITE_CONF"] is not set, prepend.php will look for this file starting at SCRIPT_FILENAME,
;assume its directory is the root (OUR_ROOT) directory for the site (unless _REDIRECTed),
;and will parse it into an array which is then saved as $_SESSION["_SITE_CONF"].

_REDIRECT = "../redirect"
;_REDIRECT alters OUR_ROOT by backing up the directory structure for each ".." found then adding the rest to
;  OUR_ROOT and saving the rest as a new _REDIRECT.  This 'new' _REDIRECT is added as the top directory for
;  all file specs in HTML, eg. in a script src='_REDIRECT'/....
;  For example, assume OUR_ROOT="/ProjectLogs/prod/training":
;    if _REDIRECT=".." then OUR_ROOT becomes "/ProjectLogs/prod"
;    and scripts, etc. will be found in prod/scripts.
;  Or assume OUR_ROOT="/ProjectLogs/prod/demo":
;    if _REDIRECT="../../beta" then OUR_ROOT becomes "/ProjectLogs/beta"
;    and scripts, etc. will be found in beta/scripts
;  It can be used to push HTML called files down the directory but not altering OUR_ROOT.
;  For example, assume OUR_ROOT="/ProjectLogs/prod/demo":
;    if _REDIRECT="../demo", then the ".." will back the /demo off OUR_ROOT but the "/demo" in _REDIRECT
;    puts it back on leaving OUR_ROOT unaltered, while becoming the top directory for HTML called files.

_MORE[] = ../offline/more_conf.php
_MORE[] = ../offline/evenmore_conf.php
;The _MORE files will be parsed after this file allowing them to be put somewhere out of danger,
;ie. not under the Document Root.  Note that _MORE[] specifies an array of files.  If a value is duplicated,
;either within a file or between files, the last one specified is active.  These files are found by appending
;to OUR_ROOT so will be affected by a prior _REDIRECT.

;_OFFSET is the directory offset from DocumentRoot where this thread starts, eg. Logs.localhost/training.
;  It is used when restarting to prevent reverting to the DocumentRoot
;  If not specified in the site_conf, it will default to the opposite of _REDIRECT, ie. pick up those directory
;    levels the ".." in _REDIRECT lopped off.

;                 The following parms will typically be defined in a _MORE file:

_INCLUDE[] = ../offline/lib
_INCLUDE[] = ../offline/main
;The _INCLUDE paths will be added to the include path.
;Relative (ie. not starting with "/") paths get OUR_ROOT prepended.
;Note that these files must be specified after any _REDIRECT and can be an array of files.

_EXTENSIONS = ../extension/
;directory of subtask extension modules - needs ending backslash

_REFRESH = /../csvData/
;save backup .csv files here; see tables_list.php for more info

PAGETITLE = "Project Logs (development)"

THEME=grays
;the initial css theme subdirectory; can be overidden by preferences

SCR="/v1"
CSS="/v1"
;versioning for script and css files force re-caching: this string is inserted after HTML "/scripts" or "/css"

RUNLEVEL=1
;0=production; 1=development; 2=beta/demo: controls things like menu items and STATE display

TZO=-5
;TimeZoneOffset from Greenwich, in hours, of the server time; west is negative
;test a TZ on US East Coast

DBMANAGER=pgsql
;DBMANAGER=mysql
DBPREFIX = ""
;DBPREFIX = "t_"
;the DBPREFIX is appended to the name of each table in the DB to create 'virtual' DBs and is required
DBCONN="host=localhost;dbname=ProjectLogs" ;NOTE: order of variables is important to mysql
;The first char is the delimiter between user and password:
DBREADER=":ts_reader:ts*reader"
DBEDITOR=":ts_editor:ts*editor"

