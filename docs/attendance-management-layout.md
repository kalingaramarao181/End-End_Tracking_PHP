# Attendance Management layout

Daily Attendance now uses a compact blue header, tabs, five summary cards, employee attendance table, three-dot actions and three charts. Metrics and charts use the selected date's roster; charts show recorded hours, not an estimate for open shifts. Breaks are not tracked by the current system and display as unavailable. Export is offered only with attendance export permission. Monthly adjustments and leave decisions use three-dot menus too. Existing payroll, mail configuration, IP exceptions, leave and holiday workflows remain available.

Daily corrections use the existing attendance edit API, require an explanation, and recalculate hours and punctuality on the server. View opens the employee profile. Rows without an attendance record cannot be corrected. Shift times are supplied from attendance_settings rather than hardcoded.

Deploy the rebuilt frontend and updated modules/employee/model.php together. No new database migration is required. The previously supplied grace-period migration is a separate deployment requirement if not already installed.
