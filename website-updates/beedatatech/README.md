# BeeData website employee directory update

Published to the existing IONOS-hosted beedatatech.com website using the corrected
SFTP host `access869825273.webspace-data.io`. The SFTP root `index.html` was verified
against the public homepage before publication. Seven files were uploaded with
staged content checks and atomic replacement. No credentials are stored in this package.

Live directory: https://beedatatech.com/employees.html
The original overwritten files are saved under `backups/`; the exact backup directory
and deployed hashes are recorded in `deployment-receipt.json`.

## What changes

The homepage's existing employee card becomes a link to `/employees.html` and its
hard-coded 125 is replaced by the actual total from the public E2E API. Other
counters, project rendering, homepage navigation and carousel remain in place. `index.html` changes only
the main-script URL cache version to load the updated counter immediately. The count displays an em dash when the API is unavailable; it never uses a
made-up or stale employee count.

The new branded directory provides:

- Total, currently working, and former/unlinked summary cards that also filter results.
- Search by name, employee ID, or role; role and employment filters.
- Name, joining-date and tenure sorting; twelve cards per batch with Show more.
- Profile images with required CORS attributes and initials for missing images.
- Name, ID, role, working/left status, joining/leaving dates, tenure and workdays.
- Expandable employment details, loading, empty, retry and unavailable states.
- Responsive desktop/tablet/mobile layouts, labels, keyboard focus and reduced motion.

Employee content is inserted as text, not raw HTML. Photo URLs are limited to the
configured API origin and photo-route format. No extra private employee fields are
requested. All API pages are loaded before totals and local filters are displayed,
so employees beyond the first 100 are included. No API fixtures ship in runtime files.

## API configuration

Default endpoint: `https://e2e.beedatatech.com/public/employees`.
The API is now deployed and its routing and database schema are verified. The homepage
and directory load real employee data automatically. If the API is unavailable,
the count shows an em dash and the directory offers a retry.

If the backend is deployed under `/E2E_Tracking`, update the endpoint to
`https://e2e.beedatatech.com/E2E_Tracking/public/employees` in BOTH places:

1. `employees.html`: the `beedata-employees-api` meta tag.
2. `UpdatedFiles/employee-api.js`: the default endpoint used by the homepage.

Browser API calls use `credentials: omit`. The page must be served from the exact
approved HTTPS origin `https://beedatatech.com` (or the other API-approved domain,
if the same assets are intentionally installed there). The backend currently does
not allow www subdomains; www traffic should redirect to the approved apex domain.

## Deployment and future updates

The corrected SFTP account serves **beedatatech.com** directly from its root.
Both `index.html` and `UpdatedFiles/main.js` matched the published originals before
upload. Future updates must still check the current remote content before replacing it.

Back up `UpdatedFiles/main.js` outside the publicly served document root. Compare
the current remote source with the original source hash in
`deployment-manifest.json`. If it changed, apply only the employee-counter replacement
to that newer file, preserving unrelated changes.

Upload these new files to the beedatatech.com document root first:

- `employees.html`
- `UpdatedFiles/employee-api.js`
- `UpdatedFiles/employee-directory.js`
- `UpdatedFiles/employee-directory.css`
- `UpdatedFiles/employee-counter.css`

Finally, upload the modified `UpdatedFiles/main.js` and the root `index.html` whose
script URL includes `?v=20261010`. Use temporary remote filenames and atomic rename.
Keep backups for rollback. Do not upload this README, manifests, receipts, backups,
tests, or local tool directories. The runtime ZIP contains exactly these seven files.

After upload, verify `/employees.html`, all five JS/CSS asset URLs, the homepage
employee-card link, and the actual origin-based API responses. If only the frontend
is deployed first, verify its unavailable/retry state. After backend deployment,
verify the real employee total, image requests and filters on the live website.

## Validation

JavaScript syntax checks passed for all three changed/new scripts. Headless Chrome
checks passed using 133 synthetic test employees, including two API pages, summary
counts, role/status/search filters, sorting, expandable details, load more, image
CORS and missing-image fallback, text escaping, failed API, retry and existing
homepage rendering. Desktop (1440px), tablet (768px) and mobile (390px) layouts had
no horizontal overflow or uncaught JavaScript errors.

To rerun from the E2E repository, install Python `playwright` in the isolated tools
folder `%TEMP%/e2e-beedatatech-site-work/tools`, then:

```powershell
python website-updates/beedatatech/tests/browser_test.py
```

The test uses installed Chrome, intercepts webpage/API requests for verification,
and saves screenshots only to that temporary folder. It does not upload anything,
log in to IONOS, deploy the backend, or modify employee records.
Live verification passed: all seven public files match the package, the homepage
employee card opens the directory, existing projects still render, and desktop/mobile
layouts have no overflow or uncaught JavaScript errors. The cloud backend routing fix
is now deployed, the leaving-date column is verified, and the API returns HTTP 200.
Actual employee counts and profiles load on the live website.

Admin and Super Admin employees are excluded server-side from public lists, counts,
exact-ID searches and photo access. The linked account role takes precedence; the saved employee role is used when
no account role is available (role IDs 1/2 and administrator name variants). Protected
internal employee management remains unchanged. The directory and homepage use
these filtered API totals automatically.

Employee-page header now matches the supplied website reference: certification
badge/contact bar, four social links, BeeData logo and full navy navigation.
Dropdowns support click/keyboard interaction and the mobile menu is accessible.
Readability improvements include larger type and portraits, clearer status/tenure
labels, a reading guide, more spacing, filter reset and subtle reduced-motion-aware
card animation. Runtime HTML cache versions were updated with this release.

The employee header is sticky and retains the homepage navigation. Working
employees appear first by default; explicit sort choices remain available. Cards
and compact list views preserve active filters. BeeData logo colors accent the
cards, with staggered entrance and hover transitions that respect reduced motion.
Browser checks cover sticky positioning, working-first order, view switching,
mobile overflow, filters, loading failures and existing homepage behavior.
Default working employee priority: HR, developers, bench sales, recruiters, then other roles. Employees within each group sort by name; non-working employees follow all working employees.
