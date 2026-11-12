# TeamFlow integration

TeamFlow now uses E2E authentication, permissions, React pages and the PHP API. No separate TeamFlow Node or Python service is needed.

**Deploying migrations alone does not import the original records.** Follow [Server data import](teamflow-server-data-import.md) and use the private import package to populate each new server database safely.

## Local import completed

Imported from `team_flow_manager 2.sql` into the local `e2e_tracking` database:

| Data | Rows |
| --- | ---: |
| Shared E2E Candidate logins | 74 |
| Separate candidate profiles | 74 |
| Projects | 46 |
| Issues | 220 |
| Weekly timesheets | 145 |
| Daily work / attendance sessions | 895 |
| Reports | 434 |
| Chat messages retained | 5 |
| Work-status document references | 46 |
| Project document references | 2 |
| Teams | 1 |
| Work holidays | 10 |

Original position, permission, resource and user-position rows are retained in `teamflow_legacy_*` tables for reference. All 1,978 non-user source rows were compared against the import, including every original column after user-ID remapping.

534 available attachments were copied to private storage. Ten unavailable attachments have **Awaiting upload** entries: issue records 53, 54, 117, 268 and 269; report records 68, 104, 198 and 199; project document record 2. These are placeholders for the original files; no fabricated document content is created. Open the record's **View → Attachments → Upload file** action to replace one. Project documents appear under their parent project's attachments. Upload requires edit and upload permissions.

The original E2E users, employees, employee attendance and older `teamflow_users`, `candidate_teamflow_identity` and `teamflow_holidays` data were checked and remained unchanged. The new integration uses `teamflow_candidates` and `teamflow_work_holidays`.

## Accounts and ID relationships

- The original TeamFlow email is the E2E username (`users.nick_name`) and login email. Original bcrypt password hashes are copied byte-for-byte; passwords are not reset.
- Every imported account has the E2E **Candidate** position, regardless of its previous TeamFlow position. Old administrator grants are retained only as legacy reference data.
- Existing E2E accounts with a matching email or username cause the importer to stop. It never replaces an existing account's password or role.
- E2E assigns new `users.id` values. `teamflow_candidates.source_user_id` retains the old ID; `teamflow_candidates.user_id` holds the new shared E2E ID. Candidate IDs display as `TF-C-0001` etc.
- Project leads, issue assignees, owner/uploader references, chat senders, membership JSON and assignment references use the new E2E user IDs. Original project/issue/timesheet/report IDs stay unchanged inside the dedicated TeamFlow tables.
- Imported accounts have `employee_id=0`, the existing application's unlinked-account convention. Candidate profiles are separate records and do not create employee records or employee IDs.
- Imported personal profiles start empty, as requested. Users fill personal information, current/permanent address, family, education, experience, certifications, references, documents, declaration and photo through **My Profile**. Shared login credentials are not editable through that profile.
- Candidate sessions use `teamflow_employee_work_status.candidate_id` and `.user_id`. Existing employee attendance remains in its own table. Timestamp values are imported in UTC to preserve the source dump; daily work login/logout values display in US Eastern Time.

## Pages and permissions

| Resource | Page |
| --- | --- |
| `teamflow_candidates` | `/dashboard/teamflow/candidates` |
| `teamflow_projects` | `/dashboard/teamflow/projects` |
| `teamflow_issues` | `/dashboard/teamflow/issues` |
| `teamflow_timesheets` | `/dashboard/teamflow/timesheets` |
| `teamflow_reports` | `/dashboard/teamflow/reports` |
| `teamflow_attendance` | `/dashboard/teamflow/attendance` |
| Existing `profile` | `/dashboard/my-profile` |

The existing **Candidate Portal Management** menu at `/dashboard/candidate-access` opens the shared TeamFlow candidate list. Its `candidate-access` React component is registered in dynamic routes. Apply `20261112_candidate_portal_route.sql` after the main TeamFlow migration to repair the existing resource route and carry legacy portal role/user grants into missing TeamFlow Candidates permission rows. Explicit existing TeamFlow permissions, including denials, are preserved. Accounts without candidate-list access see a permission message instead of an empty page.

Candidates receive OWN access: assigned projects, their issues/timesheets/reports/sessions and their profile. Administrators receive ALL scope and management actions. Other roles can receive individual resources/actions through E2E's permission matrix. Both API record access and file downloads enforce scope; hiding a menu item is not the access control.

Projects become visible through lead, issue assignment, imported team membership, work/timesheet history or explicit project membership. An administrator can use **Assign members** to add candidates. New shared E2E Candidate accounts can be connected through **TeamFlow Candidates → Link Candidate Login** after creation in User Management.

Tables provide search, date/status/project filters where applicable, pagination (5/10/25/50/100), scoped Excel export and action menus. Candidate timesheets can be edited in Draft/Rejected state, submitted as Pending, and approved/rejected by authorized reviewers. Hours support fractions and totals are calculated on the server. Archive actions preserve records rather than deleting their history.

Candidate attendance is available inside **My Profile → Attendance**, with the workday switch visible near the profile header. Candidate accounts use remote attendance without a company IP restriction or WFH approval. Employee IP restrictions are unchanged. The separate Candidate Attendance navigation item is hidden for candidates who have profile access; its direct URL opens their profile Attendance tab. Administrators retain the candidate attendance management page.

### Candidate profile attendance and work-based timesheets

- Signing into E2E opens the account; it does not clock the candidate into work. Turning on the profile switch opens the assigned-project, module/task and planned-work form. Attendance starts only after a valid submission succeeds. Canceling the form does not start attendance.
- While working, **Update work** saves completed work, task progress percentage and optional blockers/next steps. Turning off the switch opens a completion form; submission records the server logout time and ends the session. The navbar presence indicator refreshes after clock actions.
- My Profile includes monthly attendance cards, a daily-hours chart, a clickable calendar and paginated daily work details. Calendar dates filter the related sessions. US Eastern Time is the common work-date standard; new clock instants also store UTC timestamps for accurate elapsed hours across daylight-saving transitions. A session can cross midnight and an ISO week/year boundary.
- Each newly completed profile session adds its elapsed seconds to an attendance-generated **Draft** weekly timesheet, grouped by candidate, project and ISO week. Overnight work is split across the correct Eastern dates and weeks. Task progress is a work-status measure; it does not multiply or reduce attendance hours.
- The **Timesheets** profile tab shows the candidate's sheets. Attendance-generated Draft/Rejected hours are editable with an adjustment reason and audit trail; dates remain tied to their source week. Daily work progress is visible below the weekly grid. Candidates submit the draft for approval; authorized reviewers approve/reject it. Pending/Approved sheets remain locked. If additional work is recorded after submission, it goes into a new draft rather than changing an approved sheet. Repeated clock-outs or synchronization cannot duplicate work entries.
- Previously imported/manual timesheets and historical attendance are preserved. Historical sessions are not automatically backfilled into new timesheets, which would double-count previously entered work. Old unclosed legacy sessions display **Needs review** in history and do not keep the new workday switch turned on.
- Personal details display as profile information, with a section edit action. The profile hero includes photo upload/update and removal with confirmation, candidate ID, contact details and completion status. Family, education, experience, certifications, references, documents and declaration remain editable in their own tabs.

## Deploy to another E2E server

The completed local import does not deploy anything to the live server. Back up the destination database and private upload folders before deployment. Check username conflicts against that destination; local IDs must not be copied into an existing live `users` table.

For a server where TeamFlow is already integrated, this profile-attendance upgrade needs `20261112_candidate_profile_attendance.sql`, the updated React build, and the updated complete `modules/teamflow/` folder (including `attendance.php`). Apply the additive migration before activating the new PHP/frontend code. No account/data reimport is needed. Include the sidebar, related navigation and dynamic route updates when building the frontend.

1. Deploy the updated React source/build and these PHP changes from `C:/xampp/htdocs/E2E_Tracking`:
   - `index.php` (TeamFlow route registration).
   - `modules/auth/model.php` (candidate metadata in the shared authorization profile).
   - The complete `modules/teamflow/` folder.
   - `scripts/import_teamflow.php` (CLI import only).
   - `migrations/20261111_teamflow_integration.sql`.
   - `migrations/20261112_candidate_portal_route.sql` (apply after the main migration).
   - `migrations/20261112_candidate_profile_attendance.sql` (profile calendar, UTC clocks and worklog-backed timesheets).
2. Configure a writable private **TEAMFLOW_FILE_DIR**, outside the public web root. The PHP web process and import CLI must use the same directory. The default local directory is `C:/xampp/e2e_teamflow_files`. Candidate identity documents use the existing private `EMPLOYEE_DOCUMENT_DIR` and encryption configuration. Preserve that encryption key and private directory during moves.
3. Configure PHP uploads: at least 15 MB `upload_max_filesize`, a larger `post_max_size` for multipart requests, a writable `upload_tmp_dir`, and writable private destination directories. Raise request limits at Apache/reverse proxy when needed. Photos allow JPEG/PNG/WebP up to 5 MB and 24 megapixels. General attachments allow up to 15 MB and are served through authenticated downloads.
4. Apply the SQL migrations in order to the destination E2E database (for example using phpMyAdmin): `20261111_teamflow_integration.sql`, `20261112_candidate_portal_route.sql`, then `20261112_candidate_profile_attendance.sql`. They add dedicated tables, resources, missing permission grants and attendance/worklog columns. They do not execute or import the original database dump. Reapplying them preserves customized permission rows. Deploy the complete `modules/teamflow/` folder, including the new `attendance.php`, together with the updated frontend build.
5. Prepare the import manifest using the frontend repository's development script:

   ```powershell
   node scripts/prepare_teamflow_import.js "C:/Users/ADMIN/Downloads/team_flow_manager 2.sql" --output .server-audit/teamflow-import.json
   ```

   The manifest contains private account hashes and business data. Transfer it to a private location on the destination, never `public/`, `build/`, a publicly served backend folder, or Git. Transfer the original TeamFlow server upload tree privately as well. The importer accepts its original `server` root containing `uploads` and `uploads/DailyStatusReports`.
6. Run the CLI importer on the destination, using that server's existing database configuration. First omit `--apply` for a preview:

   ```bash
   php scripts/import_teamflow.php --manifest=/private/teamflow-import.json --uploads=/private/original-teamflow/server
   php scripts/import_teamflow.php --manifest=/private/teamflow-import.json --uploads=/private/original-teamflow/server --apply
   ```

   Set `TEAMFLOW_FILE_DIR` in the CLI environment to the same private directory used by the web process. Data inserts run in a transaction. Failure rolls them back and removes newly copied files. A completed source hash is recorded in `teamflow_imports`; repeating that import returns `already_imported` without duplicating rows or overwriting subsequent edits. Destination TeamFlow tables must be empty for the initial import.
7. Build with `npm ci` and `npm run build`, serve the resulting build with the existing E2E setup, and restart the existing frontend service if required. Sign out/in to refresh the new resource list. Test a Candidate login and an administrator login. No additional long-running service is required.

For a full fresh database move instead of an import into an existing server, restore the complete local E2E backup and private storage together. Do not run the importer again on that restored database; its import ledger is already included. Never overwrite a live database containing newer records with a local dump.

## Verification performed

- Migration applied twice in an isolated clone; complete import and per-column comparisons passed.
- Actual local import: 74 account hashes/usernames preserved, 1,978 legacy/work rows verified, and repeat import did not duplicate data. Original E2E user/employee/attendance rows and older legacy tables remained unchanged.
- 18 React tests passed for form edits, pagination/filter requests, switch/work-form submission, calendar date selection, generated timesheet hour adjustments, current address edits, missing file replacement, SQL parsing and ISO week boundaries.
- Isolated real PHP API checks passed shared login, OWN access, cross-user rejection, project assignment, timesheet approval/fractional hours, private documents/photos, missing attachment replacement, remote candidate clocking while company IP restrictions remain enabled, progress updates, automatic timesheets, approved-sheet locking and clock duplicate prevention. Photo removal invalidates the previous photo download. Invalid attachments and photos roll back their accompanying saves.
- Worklog allocation tests passed overnight splitting, ISO week-year rollover, both daylight-saving transitions and repeated synchronization. A fresh pre-update backup comparison confirmed current account credentials, candidate profiles, imported work records and employee attendance were preserved.
- Chrome checks against the production build passed route/sidebar access, edit/save, pagination, Awaiting upload display, Excel download/read-back, profile current address changes and mobile dark-mode page fit, with no JavaScript runtime exceptions.
- Production build succeeded. Its remaining lint warnings are in pre-existing unrelated files.

Optional verification scripts are development tools, not deployment services: `scripts/verify_teamflow_browser.js`, `scripts/test_teamflow_api.py` and `scripts/prepare_teamflow_import.js`. The Python test uses the private `.server-audit` test database fixtures and refuses a non-test database.

Local backups, manifests and audit results are under ignored `.server-audit/`. They must remain private and must not be included in public deployment uploads.


## Weekly collaboration upgrade

See [Weekly timesheets and project collaboration](teamflow-weekly-timesheets.md) for the latest weekly grid, review workflow, notifications, scoped project discussions and deployment checklist. Apply `20261113_teamflow_weekly_collaboration.sql` after the earlier migrations and deploy the updated `modules/chat/model.php` and `modules/chat/routes.php` alongside the complete TeamFlow backend and React build.
