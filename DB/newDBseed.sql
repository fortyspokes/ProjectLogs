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


