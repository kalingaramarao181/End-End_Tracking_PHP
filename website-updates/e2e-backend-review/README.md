# Cloud backend update

The production employees.date_of_leaving column was verified as DATE. All employee, user, position and attendance columns required by the public API were verified through a temporary token-protected schema check, which was removed immediately. The existing migration was not rerun and employee data was not changed.

The deployed index.php was backed up and its existing request-path assignment was moved before the public-route guard. Other deployed routes and the deployed global origin list were preserved. The staged replacement and published content were verified. See deployment-receipt.json for backup location and verification results.

Live API: https://e2e.beedatatech.com/public/employees
Website: https://beedatatech.com/employees.html

Live HTTP checks passed for approved/disallowed origins, preflight, methods, filter validation, pagination, private-field exclusion and existing employee authentication. The website now loads actual employee data; desktop/mobile browser checks passed without uncaught JavaScript errors. Origin restriction remains browser-based, as requested; it is not authentication against non-browser clients.

Admin and Super Admin employees are excluded server-side from public lists, counts,
exact-ID searches and photo access. Both the employee saved role and linked account
role are checked (role IDs 1/2 and administrator role-name variants). Protected
internal employee management remains unchanged. The directory and homepage use
these filtered API totals automatically.
