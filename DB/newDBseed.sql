-- Be sure to change "<PREFIX>"

-- This person is the superduper user and must have person_id=0; it may be necessary to update after creation to
-- reset back to 0 (mySQL is the guilty party here).  Create your own names, password, etc. using CreatePwdHash.php
-- and CreateSoundex.php.
INSERT INTO <PREFIX>c00_person (person_id,lastname,lastsoundex,firstname,loginname,password)
VALUES (0,'Uhuru','U600','Micky','rootstock','$2y$10$SrCeMpgeKZC4JiMAKuJileeKZzsOsy.IPY7O0AQMzZzGkeqGR5vmK');

INSERT INTO <PREFIX>a00_organization (organization_id,name,description)
VALUES (1,'seed','initial seed organization - please change');

INSERT INTO <PREFIX>a10_project (project_id,organization_idref,accounting_idref,name,description)
VALUES (1,1,1,'seed','initial seed project - please change');

INSERT INTO <PREFIX>a12_task (task_id,project_idref,name,description)
VALUES (1,1,'seed','initial seed task - please change');

INSERT INTO <PREFIX>a14_subtask (subtask_id,task_idref,name,description)
VALUES (1,1,'seed','initial seed subtask - please change');

INSERT INTO <PREFIX>a20_accounting (accounting_id,organization_idref,name,description)
VALUES (1,1,'seed','initial seed accounting group - please change');

INSERT INTO <PREFIX>a21_account (account_id,accounting_idref,name,description)
VALUES (1,1,'seed','initial seed account - please change');

-- d01_permit
-- It would be prudent to check an existing DB to be sure nothing new has been added.
INSERT INTO <PREFIX>d01_permit VALUES (1, '_*_', 'Superuser - can do anything', '', 1);
INSERT INTO <PREFIX>d01_permit VALUES (2, 'org_edit', 'Add, change, delete organizations', '', 1);
INSERT INTO <PREFIX>d01_permit VALUES (3, 'person_edit', 'Add, change, delete persons', '', 10);
INSERT INTO <PREFIX>d01_permit VALUES (4, 'assign_permits', 'Grantrevoke permissions', '', 10);
INSERT INTO <PREFIX>d01_permit VALUES (5, 'project_edit', 'Add, change projects', 'Projects are not deleted.  Only the INACTIVE_ASOF date is set.', 10);
INSERT INTO <PREFIX>d01_permit VALUES (6, 'task_edit', 'Add, change tasks', 'Tasks are not deleted.  Only the INACTIVE_ASOF date is set.', 100);
INSERT INTO <PREFIX>d01_permit VALUES (7, 'subtask_edit', 'Add, change subtasks', 'Subtasks are not deleted.  Only the INACTIVE_ASOF date is set.', 100);
INSERT INTO <PREFIX>d01_permit VALUES (8, 'account_edit', 'Add, change accounts', 'Accounts are not deleted.  Only the INACTIVE_ASOF date is set.', 10);
INSERT INTO <PREFIX>d01_permit VALUES (9, 'project_logs', 'Download project timelogs', '', 100);
INSERT INTO <PREFIX>d01_permit VALUES (10, 'edit_logs', 'Edit logs of any person in the org', '', 100);
INSERT INTO <PREFIX>d01_permit VALUES (11, 'reports', 'download reports', 'ie. taskreport', 100);
INSERT INTO <PREFIX>d01_permit VALUES (12, 'set_rates', 'Set hourly rates', '', 100);
INSERT INTO <PREFIX>d01_permit VALUES (13, 'accounting_edit', 'Add, change accounts lists', '', 10);
INSERT INTO <PREFIX>d01_permit VALUES (14, 'event_edit', 'Add, change events', 'Events are not deleted.  Only the INACTIVE_ASOF date is set.', 100);
INSERT INTO <PREFIX>d01_permit VALUES (15, 'logs_prune', 'Delete stale logs records', '', 100);
INSERT INTO <PREFIX>d01_permit VALUES (16, 'property_admin', 'administer properties and values', 'connecting values to elements occurs at the element admin page', 10);
INSERT INTO <PREFIX>d01_permit VALUES (17, 'repository_get', 'Download from the repository', '', 10);
INSERT INTO <PREFIX>d01_permit VALUES (18, 'repository_put', 'Insert into the repository', '', 10);

-- d02_currency
INSERT INTO <PREFIX>d02_currency VALUES (1, 'us dollar', '$', 2);
INSERT INTO <PREFIX>d02_currency VALUES (2, 'euro', '&euro', 2);
INSERT INTO <PREFIX>d02_currency VALUES (3, 'pound', '&pound', 2);
INSERT INTO <PREFIX>d02_currency VALUES (4, 'yen', '&yen', 2);

-- d10_preferences
-- list the 'template' preferences; user_idref < -10 are 'cosmetic'
INSERT INTO <PREFIX>d10_preferences (preferences_id, user_table, user_idref, name, prefer) VALUES (1, 'a00', -1, 'staff', 'text:');
INSERT INTO <PREFIX>d10_preferences (preferences_id, user_table, user_idref, name, prefer) VALUES (2, 'a00', -11, 'menu', 'text:');
INSERT INTO <PREFIX>d10_preferences (preferences_id, user_table, user_idref, name, prefer) VALUES (3, 'a00', -11, 'theme', 'select:..default..');
INSERT INTO <PREFIX>d10_preferences (preferences_id, user_table, user_idref, name, prefer) VALUES (4, 'c10', -11, 'menu', 'text:');
INSERT INTO <PREFIX>d10_preferences (preferences_id, user_table, user_idref, name, prefer) VALUES (5, 'c10', -11, 'theme', 'select:..default..');
INSERT INTO <PREFIX>d10_preferences (preferences_id, user_table, user_idref, name, prefer) VALUES (100, '000', -1, 'dummy', 'save 100 slots for the templates');
-- for postgreSQL:
ALTER SEQUENCE <PREFIX>d10_preferences_preferences_id_seq RESTART WITH 101;
