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

The email uses the staff member's authenticated sender mailbox. Failed delivery removes the newly created invitation. The link expires after seven days and is single-use. Existing invitations with no role continue to open. No real email is sent by the automated tests.

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
- Manual Add Employee and public submissions allocate `EMP-I-N` codes through a transactional singleton sequence. Manual entry has no employee-ID field; existing IDs remain visible and read-only during editing. The sequence checks the highest existing code and serializes concurrent creations, including when no employees exist.
- Apply `migrations/20261003_attendance_configurations.sql` before deploying the changed PHP files. It creates `employee_id_sequence` and `attendance_leave_mail_settings`; it was applied locally. Deploy `AttendanceLeaveMailService.php` along with the updated employee module and `UserSmtpCredentialService.php`.
- Configurations has its own permission-aware route so authorized employee creators do not need payroll-sharing permission or attendance-view permission just to configure their sender.
- Verify in staging: configure sender, send pre-offer from Employees, submit form, add employee manually, confirm sequential IDs, send payslip, configure leave mailbox and submit a leave request. Keep the existing encryption key unchanged.


### MySQL reserved identifier compatibility

The sequence column `last_value` is quoted with backticks in the migration and allocator SQL because LAST_VALUE is reserved in MySQL 8. If the original migration failed at CREATE TABLE, rerun the corrected entire `20261003_attendance_configurations.sql` and deploy the corrected `modules/employee/model.php`. Existing tables and sequence values are preserved; no rename or drop is needed. Local integration checks run on XAMPP and do not establish the live server version.
