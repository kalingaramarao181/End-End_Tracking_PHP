# Self-profile updates

Deploy the frontend build and modules/employee/model.php together. No SQL migration is required.

Signed-in employees may update their own personal, family, banking, education, experience, certification, reference and document details. Joining date is editable in Personal. Uploaded document categories accept corrected versions, retaining earlier uploads. Public onboarding link expiry is unchanged.

Self-service remains bound to the employee linked to the authenticated user. Employee IDs remain optional until staff assign them, and immutable once assigned. Position, account assignment, salary and staff review fields remain protected from self-service changes. Valid dates and safe file types/sizes are still required.

The profile header places Employee ID before the employee name, or shows Not assigned. Regression checks verify self-service joining-date updates, corrected document uploads, and continued protection of IDs and privileged fields.
