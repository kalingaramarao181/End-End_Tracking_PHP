ALTER TABLE resources
    ADD COLUMN component_key VARCHAR(100) NULL AFTER route,
    ADD COLUMN resource_type ENUM('PAGE','WIDGET','REPORT','ACTION') NOT NULL DEFAULT 'PAGE' AFTER component_key;

UPDATE resources SET component_key='dashboard', resource_type='PAGE' WHERE resource_name='dashboard';
UPDATE resources SET component_key='recruiting', resource_type='PAGE' WHERE resource_name='recruiters';
UPDATE resources SET component_key='bench-sales', resource_type='PAGE' WHERE resource_name='bench_sales';
UPDATE resources SET component_key='hotlist', resource_type='PAGE' WHERE resource_name='hotlist';
UPDATE resources SET component_key='jobs', resource_type='PAGE' WHERE resource_name='jobs';
UPDATE resources SET component_key='vendors', resource_type='PAGE' WHERE resource_name='prime_vendors';
UPDATE resources SET component_key='clients', resource_type='PAGE' WHERE resource_name='clients';
UPDATE resources SET component_key='candidates', resource_type='PAGE' WHERE resource_name='candidates';
UPDATE resources SET component_key='training', resource_type='PAGE' WHERE resource_name='training';
UPDATE resources SET component_key='candidate-onboarding', resource_type='PAGE' WHERE resource_name='candidate_onboarding';
UPDATE resources SET component_key='employees', resource_type='PAGE' WHERE resource_name='employees';
UPDATE resources SET component_key='users', resource_type='PAGE' WHERE resource_name='users';
UPDATE resources SET component_key='profile', resource_type='PAGE' WHERE resource_name='profile';
UPDATE resources SET component_key='permissions', resource_type='PAGE' WHERE resource_name='permissions';
UPDATE resources SET component_key='positions', resource_type='PAGE' WHERE resource_name='positions';
UPDATE resources SET component_key='resources', resource_type='PAGE' WHERE resource_name='resources';
UPDATE resources SET component_key='document-reminders', resource_type='PAGE' WHERE resource_name IN('documents','document_reminders');

INSERT IGNORE INTO resources(resource_name,display_name,icon,route,component_key,resource_type,sort_order,status) VALUES
('dashboard_submissions','Submissions','submissions',NULL,'dashboard-submissions','WIDGET',101,'Active'),
('dashboard_active_candidates','Active Candidates','candidates',NULL,'dashboard-active-candidates','WIDGET',102,'Active'),
('dashboard_interviews','Interviews','interviews',NULL,'dashboard-interviews','WIDGET',103,'Active'),
('dashboard_placements','Placements','placements',NULL,'dashboard-placements','WIDGET',104,'Active');

INSERT INTO permissions(position_id,resource_id,can_view,can_export,data_scope)
SELECT p.position_id,r.id,p.can_view,p.can_export,p.data_scope
FROM permissions p
JOIN resources dashboard ON dashboard.id=p.resource_id AND dashboard.resource_name='dashboard'
JOIN resources r ON r.resource_name IN(
 'dashboard_submissions','dashboard_active_candidates','dashboard_interviews','dashboard_placements'
)
ON DUPLICATE KEY UPDATE
 can_view=VALUES(can_view),can_export=VALUES(can_export),data_scope=VALUES(data_scope);
