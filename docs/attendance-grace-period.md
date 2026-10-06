# Attendance grace period

Shift begins at 9:30 AM America/New_York (Eastern Time, including daylight saving). Login through 10:00:00 AM is On Time. 10:00:01 AM onward is Late. This changes punctuality only, not actual hours worked or half-day classification.

Deploy updated modules/employee/model.php and the frontend build together. Run migrations/20261007_attendance_grace_period.sql on the live database to update the default grace period and reclassify existing attendance. The migration is repeatable and retains punch times, hours, notes, and day status.

Local database migration was applied. Live deployment has not been performed.

Development-only tests/attendance_grace_test.php verifies cutoff boundaries, historical classification, and admin corrections using temporary tables only. Run with E2E_COLLECTION_TEST_MODE=1.
