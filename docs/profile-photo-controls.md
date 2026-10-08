# Profile photo loading and removal

Deploy the frontend build with modules/employee/routes.php and modules/employee/EmployeePhotoService.php. No SQL migration is required. Retain the existing private storage configuration and encryption key.

Photo downloads share a bounded in-memory cache and concurrent requests, scoped to the login session for five minutes. Detail requests prefetch portraits. Successful uploads prime the cache with the new file and deletion invalidates it. No photos are persisted in localStorage. First uncached loading still depends on server/network speed.

Photo ownership checks now query only the employee ID instead of decoding a complete profile. DELETE /employees/{id}/photo uses the same self/staff permission checks as upload, clears the database reference transactionally, and cleans up the encrypted portrait file. Other documents remain untouched.

Update and Remove share one compact control under the portrait. Removal requires a confirmation dialog. Failed removals retain the portrait and allow retry; successful removal refreshes the navbar fallback immediately.
