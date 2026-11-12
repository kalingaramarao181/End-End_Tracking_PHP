# Weekly timesheets and project collaboration

Candidates open **My Profile → Timesheets** or the Timesheets resource to see their own week. Administrators with ALL scope see a weekly review list and can filter by candidate and status. Use the arrows or week picker to change the week.

- **Daily work progress** shows the candidate's work plans, completion updates, blockers and recorded hours, including weeks without saved time entries.
- Completed profile work sessions automatically populate draft hours. Candidates can edit Draft/Rejected hours; changing attendance-generated hours requires an adjustment reason and retains an audit trail. Later recorded work adds to those corrected hours. Pending/Approved entries stay locked.
- **Add work entry** supports Project, Leave and Training. Project entries can reference an assigned task. Save fractional daily hours, then submit the week for review. Copy previous brings across activities with **zero hours**.
- Authorized reviewers can approve or reject pending entries; rejection requires a reason. A candidate cannot approve their own submission. Admins/developers with ALL timesheet visibility receive submission notifications; the candidate receives review notifications. Developer approval still requires an explicit approval permission.
- Historical work is not silently backfilled. **Use recorded work hours** imports eligible completed, unlinked sessions, skipping projects with existing manual/imported entries to prevent duplicate hours.
- CSV and Excel export the displayed entries. Exporting does not discard unsaved edits.

## Projects, messages and notifications

Open a project to assign candidates, view its tasks/documents and use its discussion. Task details also provide a discussion. Project/task discussion membership comes from server-validated project membership, not client-supplied member IDs. Candidates can use existing E2E direct and group chats subject to permissions. New project/task assignments and messages use existing E2E notifications.

## Deployment upgrade for an already integrated server

1. Back up the destination database and private file directories.
2. Apply [20261113_teamflow_weekly_collaboration.sql](../migrations/20261113_teamflow_weekly_collaboration.sql) after the earlier TeamFlow integration, portal-route and profile-attendance migrations. It is additive and preserves existing explicit permission rows. No data reimport is needed.
3. Deploy the complete updated PHP `modules/teamflow/` directory from the local backend, including **weekly.php** and **collaboration.php**.
4. Also deploy updated PHP **modules/chat/model.php** and **modules/chat/routes.php**. These contain project membership checks and notification permission routing.
5. Build/deploy the updated React application with the existing build process. No new service or API key is required. Sign out and back in to refresh resource permissions.
6. Check a Candidate and an authorized administrator account: work entry → save → submit → notification → approve/reject → candidate notification. Check project discussions with a member and a nonmember.

The local implementation and database migration have been verified; this does not deploy changes to the live server. Keep `.server-audit/`, database dumps and private verification fixtures out of deployment uploads.

## Verification

Focused React tests, isolated real PHP API tests, production-build browser checks, hour allocation across midnight/ISO week boundaries/daylight-saving transitions, and comparison against the local pre-change backup cover the new workflow. Browser tests use synthetic API fixtures; the separate PHP tests exercise real routes and database transactions in an isolated test database.
