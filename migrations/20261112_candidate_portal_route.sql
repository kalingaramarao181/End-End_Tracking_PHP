-- Apply after 20261111_teamflow_integration.sql.
-- Restore the existing Candidate Portal Management entry and bridge its grants
-- to the shared TeamFlow candidate page without replacing explicit permissions.
INSERT INTO resources(resource_name,display_name,icon,route,component_key,resource_type,sort_order,status)
VALUES('candidate_portal_admin','Candidate Portal Management','users','/dashboard/candidate-access','candidate-access','PAGE',77,'Active')
ON DUPLICATE KEY UPDATE route=VALUES(route),component_key=VALUES(component_key);

INSERT IGNORE INTO permissions(position_id,resource_id,can_view,can_create,can_edit,can_delete,can_export,can_upload,can_download,can_approve,can_reject,can_assign,can_manage,data_scope)
SELECT p.id,r.id,1,1,1,1,1,1,1,1,1,1,1,'ALL'
FROM positions p JOIN resources r ON r.resource_name='candidate_portal_admin' WHERE p.id IN(1,2);

INSERT IGNORE INTO permissions(position_id,resource_id,can_view,can_create,can_edit,can_delete,can_export,can_upload,can_download,can_approve,can_reject,can_assign,can_manage,data_scope)
SELECT p.position_id,target.id,p.can_view,p.can_create,p.can_edit,p.can_delete,p.can_export,p.can_upload,p.can_download,p.can_approve,p.can_reject,p.can_assign,p.can_manage,p.data_scope
FROM permissions p
JOIN resources source ON source.id=p.resource_id AND source.resource_name='candidate_portal_admin'
JOIN resources target ON target.resource_name='teamflow_candidates';

INSERT IGNORE INTO user_permissions(user_id,resource_id,can_view,can_create,can_edit,can_delete,can_export,can_upload,can_download,can_approve,can_reject,can_assign,can_manage,data_scope)
SELECT p.user_id,target.id,p.can_view,p.can_create,p.can_edit,p.can_delete,p.can_export,p.can_upload,p.can_download,p.can_approve,p.can_reject,p.can_assign,p.can_manage,p.data_scope
FROM user_permissions p
JOIN resources source ON source.id=p.resource_id AND source.resource_name='candidate_portal_admin'
JOIN resources target ON target.resource_name='teamflow_candidates';
