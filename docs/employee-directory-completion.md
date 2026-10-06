# Employee directory profile completion

Deploy modules/employee/model.php and the new modules/employee/EmployeeProfileCompletion.php together with the frontend build. No SQL migration is needed for this change.

The employees list returns profile_completion, calculated from the same required personal, family, banking, qualification, experience and certification fields used on the profile page. Optional fields do not lower completion. Banking values and candidate collection data are used internally and removed from the list response.

Development tests use shared fixtures to verify parity between frontend and backend calculations. Backend list integration uses a temporary employees table and verifies private fields are excluded.
