-- Creating a DB will probably be done via some setup routine.  This example from
-- the original Timesheets does, however, show you what must be done.

-- User: "ts_admin"
-- DROP USER ts_admin;
CREATE USER 'ts_admin'@'localhost' IDENTIFIED BY 'ts*admin';

-- User: "ts_editor"
-- DROP USER ts_editor;
CREATE USER 'ts_editor'@'localhost' IDENTIFIED BY 'ts*editor';

-- User: "ts_reader"
-- DROP USER ts_reader;
CREATE USER 'ts_reader'@'localhost' IDENTIFIED BY 'ts*reader';

-- Database: timesheets
-- DROP DATABASE timesheets;
CREATE DATABASE timesheets
    CHARACTER SET = utf8
    COLLATE = utf8_general_ci;

GRANT ALL ON timesheets.* TO 'ts_admin'@'localhost';
GRANT SELECT, UPDATE, INSERT, DELETE ON timesheets.* TO 'ts_editor'@'localhost';
GRANT SELECT ON timesheets.* TO 'ts_reader'@'localhost';
-- ALTER privilege is necessary to load new data which contains autonumbers
GRANT ALTER ON timesheets.* to 'ts_editor'@'localhost';

