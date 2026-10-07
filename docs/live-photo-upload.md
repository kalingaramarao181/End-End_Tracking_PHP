# Live employee photo upload

Deploy modules/employee/EmployeePhotoService.php and modules/employee/EmployeeCollectionService.php together. No frontend rebuild or database migration is required for this change.

The previous generic 422 response did not identify whether storage, encryption, or the database failed. The updated handler returns a safe, specific configuration message and writes the underlying exception to the PHP error log with `Employee photo [storage]`, `[encryption]`, or `[database]`.

## Private storage

Configure EMPLOYEE_DOCUMENT_DIR to an absolute existing private folder outside Apache DOCUMENT_ROOT, owned/writable by the PHP hosting account. Use the existing employee document folder when files are already uploaded. Do not move or replace it without migrating the existing encrypted files. Do not use a public uploads folder or world-writable permissions.

Without an explicit setting, existing legacy storage is retained. New installations choose e2e_employee_documents beside the public document root rather than assuming a fixed number of parent directories. Creation and writability are checked.

## Encryption and database

Keep the SAME MAIL_CREDENTIAL_KEY or config/mail-credential-key.php from the existing deployment. Changing the key makes existing encrypted employee documents and payroll information unreadable. Verify PHP OpenSSL is enabled. PHP Fileinfo is used when available; validated image headers provide MIME detection when it is unavailable.

For a database-stage error inspect the PHP log and the employees.photo column definition. The encrypted filename requires 42 characters. Do not apply speculative schema changes before checking the actual database exception.

## Verification

Retry a valid JPG, PNG or WebP under 2 MB after deployment. Check the returned configuration message and matching PHP error-log entry if it still fails. The live root cause cannot be confirmed without that log. Development regression uses temporary employees tables and a temporary private directory; no production data is changed.
