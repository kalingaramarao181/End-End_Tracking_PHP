# BeeData public employee API

## Endpoint and allowed websites

`GET /public/employees` has no login/JWT requirement. Local URL:
`http://localhost/E2E_Tracking/public/employees`

On a root deployment use `https://e2e.beedatatech.com/public/employees`.
If deployed under `/E2E_Tracking`, retain that prefix in the API URL.

Exactly these HTTPS browser origins are allowed:

- `https://beedatatechnology.com`
- `https://beedatatech.com`

Any page path on these websites works: browsers send the site's origin without its path.
Other origins, HTTP origins, www/other subdomains, alternate ports, missing Origin,
and `Origin: null` return 403. The list and photo endpoints share the same policy.
GET and approved OPTIONS preflight are supported. Other methods return 405.
The API never uses a Referer fallback, wildcard CORS, browser credentials, or JSONP.
Responses have `Vary: Origin` and `Cache-Control: private, no-store`.

This is **browser-origin restriction**, as requested, not caller authentication.
A non-browser client can forge Origin. Do not describe this API as preventing all
third-party access. Enforceable caller authentication requires server-held credentials.

## Filters and pagination

- `page`: positive integer, default 1, maximum 1000000.
- `limit`: 1 to 100, default 25.
- `employment`: `all` (default), `working`, or `not_working`.
- `employee_id`: optional exact employee code, e.g. `BDT-I-007`.

Invalid filters return 422. No matching employees returns a successful empty array.
Pagination is ordered by the stable internal employee record ID.

Example response (illustrative values):

```json
{
  "success": true,
  "page": 1,
  "limit": 25,
  "total": 1,
  "total_pages": 1,
  "as_of_date": "2026-10-09",
  "timezone": "America/New_York",
  "data": [{
    "employee_id": "BDT-I-007",
    "name": "Example Employee",
    "role": "IT Recruiter",
    "profile_image_url": "/E2E_Tracking/public/employees/7/photo",
    "is_working": true,
    "employment_status": "working",
    "date_of_joining": "2024-01-01",
    "date_of_leaving": null,
    "tenure": {
      "calendar_days": 1012,
      "years": 2,
      "months": 9,
      "days": 8,
      "through_date": "2026-10-09"
    },
    "recorded_workdays": 240
  }]
}
```

`is_working` means the employee is linked to an existing user account, regardless
of the account's login activity or Active/Inactive flag. `employment_status` is
`working` for linked employees, `left` for unlinked employees with a recorded leaving
date, and `not_working` for unlinked employees without a known leaving date.
A nonexistent linked account does not qualify as working.

The role uses the linked user's position, then the employee's saved position.
No email, phone, address, birthday, salary, bank details, credentials, documents,
user ID, or private image filename is returned. Unassigned employee codes and unknown
roles/dates/photos are null. Names come from employee first/last names.

`tenure` is calendar time from joining date to today for working employees, or to
leaving date for former employees. It is null if required dates are unknown or
inconsistent. `calendar_days` is elapsed days (same-day tenure is 0), and years,
months and days are calendar components. Dates use the existing E2E Eastern timezone.

`recorded_workdays` counts distinct attendance dates through today with a nonzero
clock-in and status `working`, `completed`, or `half_day`. Duplicate punches count
once; a half day counts as one attended date. Absence, leave, holiday, and future
records are excluded. This is recorded attendance, not inferred calendar tenure.

## Browser integration

Run this code on either approved website. No API key or JWT is required.

```js
const api = 'https://e2e.beedatatech.com/public/employees';
const response = await fetch(`${api}?employment=working&limit=25`, {
  mode: 'cors', credentials: 'omit'
});
const result = await response.json();
if (!response.ok || !result.success) throw new Error(result.message || 'Request failed');

for (const employee of result.data) {
  // Render name/role with textContent or your framework's escaped text rendering.
  if (employee.profile_image_url) {
    const image = document.createElement('img');
    image.crossOrigin = 'anonymous'; // Must be set BEFORE src to send the Origin header.
    image.alt = employee.name;
    image.src = new URL(employee.profile_image_url, api).href;
    image.onerror = () => image.remove(); // Missing/stale photos return 404.
    document.querySelector('#employees').append(image);
  }
}
```

Alternatively fetch the photo with `credentials: 'omit'`, create a blob URL, and
revoke it when the image is removed. Plain cross-origin `<img>` without
`crossorigin="anonymous"` normally omits Origin and is rejected. Images are read
through existing encrypted private storage; arbitrary legacy paths/URLs are not
published. Only a validated saved portrait gets a photo URL. Missing files return 404.
Paginate through `total_pages` to retrieve more than one page.

## Leaving-date tracking (authenticated staff only)

Apply `migrations/20261009_employee_leaving_date.sql` once on each deployment database.
The nullable column is already applied to the local XAMPP database. Existing employees
are unchanged; historical leaving dates must be entered, never guessed from attendance.
The public API works before migration, returning unknown former tenure; date editing
requires the migration.

Existing authenticated account-disconnection endpoint:

```http
PUT /employees/7/remove-company-name
Authorization: Bearer <staff JWT>
Content-Type: application/json

{"date_of_leaving":"2026-09-30"}
```

The date is optional; normal UI disconnection records today's Eastern business date.
The existing `employees.can_assign` permission applies. The user's role is preserved
on the employee when disconnected. Reconnecting clears the previous leaving date.

To correct an already unlinked employee's historical date:

```http
PUT /employees/7/leaving-date
Authorization: Bearer <staff JWT>
Content-Type: application/json

{"date_of_leaving":"2026-09-30"}
```

This requires `employees.can_edit` and ALL employee data scope. Linked employees
cannot be marked left through this endpoint. Dates must be valid YYYY-MM-DD, no
later than today and no earlier than joining. The protected employee detail response
includes `date_of_leaving`. Editing joining date also checks departure chronology.
Application user deletion records departure and preserves role in the same transaction
as deletion. Failed deletion rolls back departure changes. Direct database changes
outside the application do not capture departure dates.

This tracks the current/latest employee record, not multiple historical employment
periods. On rehire, staff should update joining date to the correct new start date;
recorded attendance still covers all saved attendance for that employee record.

## Verification

```powershell
$env:E2E_COLLECTION_TEST_MODE='1'
C:\xampp\php\php.exe tests/public_employee_directory_test.php
python tests/public_employee_api_http_test.py
```

The PHP suite uses temporary tables, preserving real employee data. HTTP checks are
read-only against local Apache and print no employee details. Existing employee and
profile routes remain authenticated. Use HTTPS for production API hosting.
Cloud deployment verified: the production leaving-date column exists as DATE, the
public-route initialization order is corrected, and live API/domain/authentication
checks passed. The BeeData website now loads the live API successfully.

Admin and Super Admin employees are excluded server-side from public lists, counts,
exact-ID searches and photo access. The linked account role takes precedence; the saved employee role is used when
no account role is available (role IDs 1/2 and administrator name variants). Protected
internal employee management remains unchanged. The directory and homepage use
these filtered API totals automatically.
