# Workshop meetings

Workshop appears at **People & Workplace > Workshop**, route `/dashboard/workshop`.

## Included

- Meeting date and calculated weekday, team from existing positions, topic, presenter from existing users, user attendees, private meeting photos and remarks.
- Add/view/edit/delete with existing resource permissions. The presenter is automatically included in attendees.
- Date and Day are the first two table and Excel columns. Filters: topic/remarks search, team, presenter, attendee and date range. Pagination offers 5, 10, 25 and 50 rows (default/minimum 5).
- Responsive light/dark design, accessible centered dialogs, photo gallery and cancellation during exports. Horizontal scrolling is contained in the table; actions stay visible at the right.
- Excel exports every matching meeting, not just the visible page. The branded workbook includes a meeting sheet with full metadata/remarks/names, an attendee-by-attendee detail sheet, and optional embedded photo thumbnails in a photo sheet. Each sheet begins with Date and Day. Photos are included by default. Export is limited to 500 matching meetings; narrow filters for larger ranges. Unavailable photos are explicitly marked rather than silently omitted.

## Backend deployment

Deploy these backend files from `C:\xampp\htdocs\E2E_Tracking`:

- `index.php` (registers `/workshops` before generic routing).
- `modules/workshop/model.php` and `modules/workshop/routes.php`.
- `migrations/20261030_workshop.sql`.

Run the migration once on the deployment database before opening Workshop. It is additive/repeatable and creates `workshop_meetings`, `workshop_attendees`, `workshop_photos`, the Workshop PAGE resource and missing role grants. It does not change employee IDs, existing tables or later custom Workshop permission grants. Local migration application and a repeated run both passed.

Provide PHP write access to `storage/workshop_photos` (or its parent `storage` so the app can create it). Keep storage outside public access where the host supports that; Apache denial rules are written in the photo directory. On Nginx also deny direct web access to `/storage/`. Never serve meeting photos as public static files.

The app accepts at most six JPG/PNG/WebP photos, up to 5 MB each and 20 MB total, limited further by server PHP settings. Keep `file_uploads=On`, `max_file_uploads>=6`, `upload_max_filesize>=5M`, and `post_max_size>=32M` to allow the full application limits. Smaller host limits are read by the options endpoint and shown in the form. SVG, arbitrary files disguised as images and images larger than 24 megapixels are rejected. A failed photo move rolls back the meeting save. Missing uploads produce an explicit error. No email/SMTP or external storage configuration is required.

## Permissions and visibility

The migration grants administrators and HR positions full access by default. Other active positions receive view/create/edit/export/upload/download with OWN scope and no delete grant. Existing explicit per-user overrides remain authoritative. Adjust the **Workshop** resource in the existing Permission Matrix as needed.

OWN scope lets a user see meetings they recorded, presented or attended. Changing a record additionally requires being its creator or presenter. ALL scope can manage all meetings when the action grant exists. TEAM/DEPARTMENT/OFFICE scopes use position membership for this module's team model. Attending a meeting alone does not grant edit access. Every API request and private photo request is authenticated and scoped on the backend.

Deletion removes a meeting from normal lists and denies access to its photos, while retaining the database record and private files for audit/administrative retention. No automatic physical purge or restore UI is introduced.

## Frontend deployment

Deploy the complete output from `npm run build`. Workshop reuses React, Axios, ExcelJS and the existing authentication/theme system; there is no Python runtime, new frontend framework or new production service. Refresh permissions or sign in again after running the migration so the new sidebar resource loads.

## API

- `GET /workshops/options`: contextual user/position names, upload limits and action capabilities.
- `GET /workshops`: scoped filtered/pageable meetings and summary counts.
- `POST /workshops`: multipart meeting creation (`meeting_date`, `position_id`, `topic`, `presenter_id`, JSON `attendee_ids`, `remarks`, `photo_count`, optional `photos[]`).
- `POST /workshops/{id}`: multipart update with JSON `keep_photo_ids` to retain saved photos.
- `DELETE /workshops/{id}`: permission-checked soft removal.
- `GET /workshops/export`: all scoped filtered metadata (requires export permission).
- `GET /workshops/photos/{id}`: authenticated image stream; never a public upload URL.

Meeting dates are calendar dates with no timezone conversion; weekday is calculated from the exact saved date. Audit timestamps are UTC and labelled UTC in Excel.

## Verification helpers (development only)

- `src/pages/Dashboard/Workshop.test.js` and `src/utils/workshopTools.test.js` cover filtering, pagination, multipart user IDs, delete confirmation and workbook data.
- `python scripts/test_workshop_api.py` tests the real local PHP/MySQL API. It creates uniquely labelled synthetic meeting records and a synthetic photo, tests authorization/record scope, multipart uploads, rejected files/dates, editing, export and deletion, then removes its own fixtures. It does not print JWTs, passwords or existing user details. Existing authorization audit entries from requests may remain.
- `node scripts/verify_workshop_browser.js` tests the production build in isolated Chrome using fictional API fixtures. It reads back the actual downloaded Excel workbook and confirms all filtered rows and an embedded photo. Screenshots and downloads remain in ignored `.server-audit/workshop-browser`.

The Python/JavaScript verification scripts are optional development tools; none is needed to run Workshop in production.
