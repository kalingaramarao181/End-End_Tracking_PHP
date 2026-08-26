CREATE TABLE IF NOT EXISTS w2_forms (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 manager_name VARCHAR(150), recruiter_name VARCHAR(150), project_consultant_name VARCHAR(150), project_start_date DATE NULL, project_end_date DATE NULL, position_title VARCHAR(180), work_address TEXT,
 consultant_name VARCHAR(150) NULL, consultant_address TEXT, consultant_phone VARCHAR(50), consultant_email VARCHAR(180) NULL, emergency_contact VARCHAR(180), emergency_address TEXT, employment_w2 TINYINT(1) NOT NULL DEFAULT 0, employment_1099 TINYINT(1) NOT NULL DEFAULT 0, employment_corp_to_corp TINYINT(1) NOT NULL DEFAULT 0,
 end_client_name VARCHAR(180), end_client_address TEXT, reporting_manager VARCHAR(150), client_phone VARCHAR(50), client_fax VARCHAR(50), client_email VARCHAR(180), switchboard_extension VARCHAR(100), client_website VARCHAR(255), project_name VARCHAR(180), team_name VARCHAR(180),
 invoice_company_name VARCHAR(180), invoice_address TEXT, invoice_phone VARCHAR(50), invoice_fax VARCHAR(50), invoice_email VARCHAR(180), timesheet_email VARCHAR(180), invoice_website VARCHAR(255), invoice_contact_person VARCHAR(180), net_payment_terms VARCHAR(120), invoice_terms TEXT,
 comments TEXT, h1b_validity VARCHAR(120), lca_h1b_amendment VARCHAR(180), created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 INDEX idx_w2_consultant(consultant_name), INDEX idx_w2_client(end_client_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
INSERT INTO resources(resource_name,display_name,icon,route,component_key,resource_type,sort_order,status) VALUES ('w2_forms','W-2 Forms','file-invoice-dollar','/dashboard/w2-forms','w2-forms','PAGE',95,'Active') ON DUPLICATE KEY UPDATE display_name=VALUES(display_name),route=VALUES(route),component_key=VALUES(component_key),resource_type='PAGE',status='Active';
INSERT INTO permissions(position_id,resource_id,can_view,can_create,can_edit,can_delete,can_download,data_scope) SELECT 1,id,1,1,1,1,1,'ALL' FROM resources WHERE resource_name='w2_forms' ON DUPLICATE KEY UPDATE can_view=1,can_create=1,can_edit=1,can_delete=1,can_download=1,data_scope='ALL';
