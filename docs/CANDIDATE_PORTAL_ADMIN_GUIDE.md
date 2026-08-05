# Candidate Portal Administration

## Assign a TeamFlow user to an E2E candidate

1. Sign in to E2E as Super Admin or Admin.
2. Open **Candidate Portal Management** from the E2E sidebar.
3. Use **Login Users** to create or update the candidate's login identity when needed.
4. Open **Candidate Access**, select the candidate, and choose exactly one TeamFlow user.
5. Keep the role as **Candidate** and status as **Active**.
6. Select the projects the candidate is allowed to work on.
7. Enable only the required resource actions and save.

The same assignment control also remains available on the individual Candidate detail page.

## Centralized administration

**Candidate Portal Management** provides native E2E views for candidates, login users, projects, issues, timesheets, workflow, messages, reports, documents, and calendar. Admins can create/update login users and projects/issues/messages, approve or reject timesheets, update/close workflows, and edit messages without opening TeamFlow.

The candidate signs in on the normal E2E login screen using the selected TeamFlow user's email and password. An inactive assignment cannot log in. One TeamFlow user cannot be assigned to multiple candidates.

## Candidate access

Candidates receive only **My TeamFlow** in the E2E navigation. The portal includes their E2E personal profile and permitted TeamFlow projects, timesheets, work status, issues, project chat, reports, documents, and calendar. Server-side ownership checks always use the mapped TeamFlow user ID and explicit project memberships.

Candidates can change their password from **My Profile** inside the portal. The password is stored only in E2E `teamflow_users` after the import.

## Synchronize existing TeamFlow data

Run from the E2E backend directory:

```powershell
php scripts\sync_teamflow_into_e2e.php
```

The synchronization is insert-missing-only. It never updates or deletes TeamFlow source rows and never deletes E2E rows. TeamFlow holidays use `teamflow_holidays` so they do not conflict with E2E attendance holidays.

## Security model

- Assignment administration: Super Admin and Admin only.
- Personal profile, timesheets, work status, reports, and status documents: mapped user only.
- Projects, issues, chats, and project documents: explicit project membership only.
- All create actions overwrite ownership with the authenticated candidate's mapped ID; client-supplied user IDs are ignored.
- Existing TeamFlow primary IDs remain unchanged during import.
