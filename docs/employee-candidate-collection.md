# Dynamic candidate / employee form and pre-offer invitations

One shared collection form is used for the public invitation, employee add/edit and employee profile. Basic identity is collected once; the duplicate personal-details section and visible checklist have been removed.

## Form behavior

1. Family details, with optional siblings added individually.
2. Previous experience is unchecked by default. Checking it opens the first company; more companies can be added individually (up to 20). Each company has its own experience / relieving letter uploads. Unchecking experience omits draft company details and new uploads from submission.
3. Education starts with the highest qualification. Each added entry has a degree selector, college / school, branch and passing year, plus separate certificate and marksheet uploads.
4. Certifications are added individually with name, issuer, year and document uploads. Awards / achievements remain optional.
5. References are optional and added individually.
6. Declaration consists only of the note and confirmation checkbox. The server records identity and confirmation time; there is no signature or date input. Staff cannot change the candidate's saved confirmation.

The existing basic details and optional bank/statutory fields remain available. Expected joining date is optional, so a selected candidate can complete the form before joining. The selected role is shown on the invitation form and retained with the submitted collection. This change does not create a separate candidate table or alter the existing company-login assignment process.

Each education, company and certification entry has a stable ID. Removing or reordering rows does not attach documents to another entry. Newly selected files for a removed entry are discarded. Earlier stored documents stay accessible under Previously Submitted Documents. Existing legacy collection data and documents are preserved when opening or editing records.

## Invitation email

Before sending, staff choose one of six roles: Sr. Bench Sales, Jr. Bench Sales, Sr. IT Recruiter, Jr. IT Recruiter, HR or Web Developer. The dialog previews the role's responsibilities. Candidate name is optional; personal email and selected role are required.

The branded BeeData pre-offer email congratulates the recipient on selection, states the role and responsibilities, and provides a secure information-form button. It explains that HR will share formal offer and joining details after review. It does not invent compensation or joining terms. Role definitions are returned from the backend, so the preview and email use the same responsibilities.

The email uses the staff member's authenticated sender mailbox. Failed delivery removes the newly created invitation. The link expires after seven days and is time-limited. Existing invitations with no role continue to open. No real email is sent by the automated tests.

A sample Web Developer email is available in `docs/pre-offer-preview.html`. Its example link is a preview placeholder.

## Deployment

1. Back up the database. Apply `migrations/20261002_employee_candidate_collection.sql` if it was not already applied, then `migrations/20261003_employee_preoffer_roles.sql`. Both have been applied locally. They add the encrypted collection column and nullable invitation role/name columns.
2. Deploy the changed employee PHP files, including `EmployeeCollectionService.php` and `EmployeePreOfferService.php`, and the employee routes.
3. Keep the existing payroll encryption key (`MAIL_CREDENTIAL_KEY` or `config/mail-credential-key.php`) stable and backed up.
4. Set `EMPLOYEE_DOCUMENT_DIR` to persistent storage outside the web root that PHP can write. The default local location is `C:/xampp/e2e_employee_documents`. Back up documents, database and encryption key together.
5. Deploy the frontend production build using the production API URL.

Candidate data and document contents remain encrypted using the existing AES-256-GCM mechanism. Downloads require authentication and employee/profile permission. The application supports genuine PDF, JPEG, PNG and WebP uploads. Effective limits follow PHP configuration, capped at 5 MB per file, 25 MB total and 20 files per submission. The UI displays server limits.

Employee changes, file storage and invitation completion are transactional. Failures roll back employee changes, remove newly written files and preserve invitation usability. Full employee details must load before staff can save edits.

## Verification

From the frontend workspace:
```powershell
python scripts/test_employee_collection.py --backend C:/xampp/htdocs/E2E_Tracking --php C:/xampp/php/php.exe
$env:CI='true'
npm test -- --watchAll=false --runInBand --runTestsByPath src/pages/PublicEmployeeOnboarding.test.js src/utils/employeeCollection.test.js
npm run build
```

Integration tests use verified empty MySQL temporary tables, a temporary PHP server and isolated document storage. They cover multiple employers, education/certification rows, references, no experience, declaration confirmation, invitation roles, six email templates, encrypted upload/download, staff edits, expired/reused links, invalid rows/files and cleanup after storage/database failures.

The fixture is disabled unless `E2E_COLLECTION_TEST_MODE=1`. After deployment, verify the production sender mailbox, storage permissions and reverse proxy upload limits.


## Employees and attendance configurations

- Employees now owns Employee Public Form invitations; Payslips contains only payroll workflows.
- Attendance Management > Configurations (direct route `/dashboard/attendance/configurations`) configures the signed-in sender for pre-offers, public forms and payslips. Employee creators and payslip sharers can manage their own sender; any authorized mailbox-configuring user can enter a different sender email, provided its SMTP credentials verify successfully.
- Super Admin with attendance edit permission can configure a separate organization-wide Leave Approval Mailbox and HR recipient. Existing environment/default settings remain the fallback until this mailbox is configured.
- Saving either mailbox sends a verification email before persisting encrypted credentials. Failed verification preserves the prior configuration. These deployment checks did not send real mail.
- Employee IDs are optional on creation. Public submissions leave the code NULL; authorized staff can enter a unique code manually once. Existing assigned IDs are immutable.
- Apply `migrations/20261003_attendance_configurations.sql` before deploying the changed PHP files. It creates `employee_id_sequence` and `attendance_leave_mail_settings`; it was applied locally. Deploy `AttendanceLeaveMailService.php` along with the updated employee module and `UserSmtpCredentialService.php`.
- Configurations has its own permission-aware route so authorized employee creators do not need payroll-sharing permission or attendance-view permission just to configure their sender.
- Verify in staging: configure sender, send pre-offer from Employees, submit form, add employee manually, confirm sequential IDs, send payslip, configure leave mailbox and submit a leave request. Keep the existing encryption key unchanged.


### MySQL reserved identifier compatibility

The sequence column `last_value` is quoted with backticks in the migration and allocator SQL because LAST_VALUE is reserved in MySQL 8. If the original migration failed at CREATE TABLE, rerun the corrected entire `20261003_attendance_configurations.sql` and deploy the corrected `modules/employee/model.php`. Existing tables and sequence values are preserved; no rename or drop is needed. Local integration checks run on XAMPP and do not establish the live server version.


## October 5: BDT IDs, reusable links, and employee profiles

Apply `migrations/20261006_manual_employee_ids.sql` before deploying the latest employee APIs. Earlier automatic allocation is superseded by optional one-time manual assignment. Existing employee IDs are preserved.

Public links accept their first submission within seven days of invitation. After submission, the same token opens the saved employee record and remains editable for four days from the original completed_at. Edits do not allocate another ID, duplicate employees, or extend that deadline. The response includes edit_until with its timezone; the form displays it in the browser's local time. Public document downloads use the same token, record scope, and deadline. Once expired, HR must edit through the authenticated employee page.

Signed-in users can edit their own linked profile through POST /employees/me. The server chooses the employee by authenticated user ID; the request cannot choose another owner. Personal details, family/experience/education/certifications/references, documents, and bank/statutory details are editable. Employee ID, company user mapping, position, schedule, joining date, salary, selected role and HR review are protected from self edits. Candidate declarations remain unchanged during staff/self-service edits. HR/admin employee editing remains subject to employees edit permission and ALL scope.

The profile displays personal, bank/statutory, family, experience, qualifications, certifications, references, documents and attendance details. Editing is inline on the profile. Attendance Login / Logout is a top switch (clocking attendance, not signing out of the application) without an ID popup. It sends the stored employee code to the existing endpoint, which continues to enforce authenticated ownership, company IP/WFH rules and attendance policy. Blocked networks disable the switch and show the network explanation.

Deploy the updated frontend build, employee PHP module (including collection/pre-offer services), and migration. No real invitation or verification emails are sent by the automated checks.


## Professional profile view and document locks

The profile view uses information cards rather than disabled form fields. Personal details, family, banking, education, experience, certifications/achievements, references, documents, declaration, performance and attendance are presented separately. The completion ring is a guidance score calculated from personal/family/bank information, qualification details and relevant document slots. Optional references, siblings and achievements do not lower the score. Expanded remaining-item links point to the appropriate section.

Self-service users and candidates using shared links can fill missing document slots. Once a document category has been uploaded, further uploads to that category are rejected server-side; associated qualification/experience/certification entries cannot be removed. Existing documents remain downloadable within existing authorization rules. Corrected versions can be submitted by users with employees edit permission and ALL record scope, through authenticated employee editing. Access follows permissions and record scope, not the role name Admin. Existing versions are retained.

No additional database migration is required for this profile redesign/document policy. Deploy the updated frontend build plus EmployeeCollectionService.php, model.php and payroll.php. The previous BDT/profile migration remains required if it has not been applied already.

## Section editing and attendance insights

Profile cards provide local Edit and Add controls for education, experience, certifications and references. Documents have their own upload editor, retaining the existing document locks. Missing required completion items appear in red. Personal, family and banking section saves validate their required fields. Section-scoped saves merge only the selected section with the current database record, preserving unrelated information. Attendance Insights loads its report when opened and can be collapsed. No additional migration is required; deploy the frontend build and updated employee model.php.


## Employee ID assignment and profile fixes (2026-10-06)

This replaces the earlier automatic allocator behavior. Run `migrations/20261006_manual_employee_ids.sql` BEFORE deploying the updated PHP employee APIs. Existing IDs, collation and unique indexes are preserved. Public submissions leave the ID NULL. Authorized employee create/edit staff may enter an ID manually once; assigned IDs cannot be cleared or replaced. Public/self-profile edits cannot assign IDs. Attendance login needs an assigned ID and still enforces company IP restrictions.

Deploy modules/employee/model.php, payroll.php, controller.php, EmployeeCollectionService.php and EmployeePhotoService.php together with the rebuilt frontend. Permanent address and parent phone numbers are optional and excluded from completion warnings. Profile views show personal, contact and family information normally. Banking/statutory fields are masked as XXXXX.XXX......, with independent Show/Hide eye buttons. Edit fields retain original values. HR-created banking details persist encrypted. Photo limits follow PHP upload limits, capped at 2 MB. Keep EMPLOYEE_DOCUMENT_DIR writable and outside the web root, and retain the same encryption key on live. Python regression scripts are development checks, not deployment requirements.
