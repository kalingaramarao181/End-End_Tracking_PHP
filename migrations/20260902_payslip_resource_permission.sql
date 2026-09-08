-- Payslips is an action resource inside Attendance Management, not a standalone page.
INSERT INTO resources(resource_name,display_name,icon,route,component_key,resource_type,sort_order,status)
VALUES('payslips','Payslips','fa fa-file-invoice-dollar',NULL,NULL,'ACTION',15,'Active')
ON DUPLICATE KEY UPDATE display_name=VALUES(display_name),icon=VALUES(icon),resource_type='ACTION',status='Active';

INSERT INTO permissions(position_id,resource_id,can_view,can_create,can_edit,can_delete,
 can_export,can_import,can_upload,can_download,can_approve,can_reject,can_assign,
 can_manage,can_print,can_share,data_scope)
SELECT 1,id,1,1,1,1,1,1,1,1,1,1,1,1,1,1,'ALL' FROM resources WHERE resource_name='payslips'
ON DUPLICATE KEY UPDATE can_view=1,can_create=1,can_edit=1,can_delete=1,
 can_export=1,can_import=1,can_upload=1,can_download=1,can_approve=1,
 can_reject=1,can_assign=1,can_manage=1,can_print=1,can_share=1,data_scope='ALL';