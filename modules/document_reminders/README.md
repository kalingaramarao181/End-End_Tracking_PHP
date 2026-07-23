# Document Expiry Reminder API

The document workflow uses manual entry. The uploaded file and entered visa details are saved in `candidate_documents`; no AI document reading or verification is performed.

## Document upload

`POST /api/document-reminders/documents/upload` accepts multipart fields:

- `candidate_id`
- `document_type`
- `document_file` (PDF, PNG, JPG, JPEG, or WEBP)
- `visa_type`
- `visa_number`
- `candidate_name`
- `issue_date` (`YYYY-MM-DD`)
- `expiry_date` (`YYYY-MM-DD`)
- `passport_number`
- `receipt_number`

Entered fields are stored in `document_details` using the original JSON structure, with `confidence: 100` and `entry_method: "Manual"`. A valid H1B expiry date creates the monthly reminder schedule.

## Reminder endpoints

- `GET /api/document-reminders` — searchable/filterable reminder list.
- `GET /api/document-reminders/{id}` — reminder, document, and entered JSON details.
- `POST /api/document-reminders/documents/{documentId}/manual` — add a missing expiry date.
- `POST /api/document-reminders/{id}/send` — send now without changing the schedule.
- `PUT /api/document-reminders/{id}/edit` — edit expiry, next reminder, or status.
- `POST /api/document-reminders/{id}/disable` — disable a reminder.
- `GET /api/document-reminders/dashboard` — 30/60/90/180 day counts.

## Automatic email

The Windows task `BeeData Document Expiry Reminders` runs `cron/document_expiry_reminders.php` daily at 8:00 AM. Email uses authenticated SMTP configuration from the Git-ignored `config/mail.local.php` file.

Test email delivery with:

```text
C:\xampp\php\php.exe C:\xampp\htdocs\api\cron\test_reminder_email.php recipient@example.com
```
