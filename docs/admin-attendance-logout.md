# Daily Attendance admin logout

Logout appears in Daily Attendance three-dot actions for users with attendance edit access. It is enabled only for open shifts; absent and already logged-out rows show a disabled option with an explanatory tooltip. The server requires attendance edit permission and ALL scope.

The confirmation contains only a Full Day/Half Day selector. No editable logout time or reason is collected. The server automatically sets Full Day to login + 6 hours and Half Day to login + 4 hours, staying on the same attendance date. Original login, punctuality and earlier notes are preserved. Selection and acting user are recorded as an admin adjustment. Duplicate logout is rejected under a row lock.

Deploy the rebuilt frontend plus modules/employee/model.php, controller.php and routes.php together. No new SQL migration is required.
