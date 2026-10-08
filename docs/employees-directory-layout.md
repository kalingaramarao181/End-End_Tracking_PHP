# Employees directory update

The directory has six global summary cards, company/role/status/joining-date filters, profile completion, and a single Status column. A three-dot menu supports hover, click and keyboard navigation. View/Edit/Delete follow existing permissions; Assign User appears only for unlinked employees with assignment permission. Deletion requires confirmation. The table scrolls horizontally within its container on small screens.

Deploy the rebuilt frontend and backend `modules/employee/model.php` and `modules/employee/controller.php` together. No database migration is required. Preserve the existing EmployeeProfileCompletion.php dependency and other deployed employee services.

Validated with frontend action/filter tests, PHP syntax checks, and temporary-table database tests covering global summary counts, combined filters, pagination, private data exclusion and prepared inputs. Testing does not change production records.
