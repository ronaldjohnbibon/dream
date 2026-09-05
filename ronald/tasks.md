# 1. Authentication Security Check

Audit the application's authentication security.

Rules:

* Follow the existing Laravel, Vue, TypeScript, Inertia, and Tailwind patterns.
* Keep the code simple and straight to the point.
* Avoid overengineering, unnecessary abstractions, and unrelated refactors.
* Reuse existing Laravel authentication features and existing project logic.
* Do not create Laravel or Vue tests.
* Check existing code before changing anything.
* Only fix confirmed authentication security issues.

Check:

* Login and logout security.
* Password hashing and password handling.
* Session regeneration after login.
* Session invalidation after logout.
* Remember-me implementation if used.
* Password reset security if available.
* Email verification if required by the application.
* Brute-force/login attempt protection.
* Authentication middleware on protected routes.
* Sensitive authentication information leaking through responses, logs, or frontend props.
* Session/cookie configuration for production.

Fix any vulnerabilities or unsafe implementation you find while keeping changes minimal.

-----

# 2. Authorization Security Check

Audit the application's authorization and access-control security.

Rules:

* Follow the existing Laravel/Vue/Inertia project patterns.
* Keep implementation simple.
* Do not overengineer or refactor unrelated code.
* Reuse existing policies, gates, middleware, roles, permissions, and ownership checks.
* Do not create tests.
* Only fix confirmed security issues.

Check every sensitive action and route for:

* User vs admin access.
* Resource ownership.
* Unauthorized record viewing.
* Unauthorized create/update/delete actions.
* Changing IDs in URLs, forms, or requests to access another user's records.
* Admin-only pages and actions.
* Order, payment, loan/pautang, points, inventory, settings, reports, and user-management permissions.
* Server-side authorization even when buttons are hidden in Vue.
* Insecure direct object reference / IDOR vulnerabilities.

Never rely only on frontend visibility or disabled buttons for authorization.

Fix all confirmed authorization issues with Laravel's existing authorization mechanisms.

-----

# 3. CSRF Security Check

Audit the application for CSRF vulnerabilities.

Rules:

* Follow existing Laravel, Vue, Inertia, and project patterns.
* Keep changes minimal and simple.
* Do not create unnecessary custom CSRF logic.
* Do not create tests.
* Only modify code where necessary.

Check:

* POST, PUT, PATCH, and DELETE requests.
* Laravel web routes and middleware.
* Inertia forms.
* Axios/fetch requests.
* AJAX requests if any.
* Logout and other state-changing actions.
* Payment confirmation actions.
* Points-related actions.
* Admin actions.
* Any routes excluded from Laravel CSRF protection.

Make sure state-changing browser requests are protected by Laravel's CSRF protection.

Review CSRF middleware exclusions carefully and remove unsafe exclusions unless they are genuinely required.

Do not disable CSRF protection as a workaround.

-----

# 4. Input Validation Security Check

Audit all user-controlled input for proper server-side validation.

Rules:

* Use Laravel validation.
* Follow existing project patterns.
* Keep validation simple and maintainable.
* Do not create unnecessary DTOs, repositories, helpers, or abstractions.
* Do not create tests.
* Do not refactor unrelated code.

Check validation for:

* Registration/profile fields.
* Orders.
* Pautang/loan requests.
* Payment submissions.
* GCash reference numbers.
* Uploaded payment screenshots.
* Points redemption.
* Quantities.
* Prices and monetary values.
* Dates.
* Status fields.
* IDs and foreign keys.
* Admin settings.
* Inventory adjustments.

Check for:

* Missing required validation.
* Invalid enum/status values.
* Negative numbers.
* Manipulated prices or totals.
* Invalid IDs.
* Excessive string lengths.
* Unexpected arrays/objects.
* Mass-assignment risks.
* Client-side validation without matching server-side validation.

Never trust values coming from Vue, hidden fields, query parameters, route parameters, or JavaScript-calculated totals.

Fix confirmed validation issues using Laravel's existing validation patterns.

-----

# 5. Rate Limiting Security Check

Audit the application for endpoints that need rate limiting.

Rules:

* Use Laravel's built-in rate-limiting features where possible.
* Keep implementation simple.
* Avoid unnecessary packages or custom infrastructure.
* Do not create tests.
* Do not refactor unrelated code.

Review especially:

* Login.
* Registration.
* Forgot/reset password.
* Email verification resend.
* OTP endpoints if any.
* Order submission.
* Payment submission.
* Payment screenshot upload.
* GCash reference submission.
* Points redemption.
* Contact/support forms.
* Search or expensive database endpoints.
* Any public API or AJAX endpoints that can be abused.

Check whether repeated requests could:

* Spam the application.
* Generate duplicate records.
* Exhaust server/database resources.
* Brute-force credentials.
* Abuse points or payments.

Add reasonable Laravel rate limits only where needed.

Make sure legitimate users are not unnecessarily blocked.

-----

# 6. Secure Upload Security Check

Audit every file-upload feature, especially GCash/payment screenshots.

Rules:

* Use Laravel's existing file validation and storage features.
* Keep implementation simple.
* Do not add unnecessary upload libraries.
* Do not create tests.
* Do not refactor unrelated functionality.

Check:

* Allowed file types.
* MIME type validation.
* File extension handling.
* Maximum file size.
* Random/safe generated filenames.
* Path traversal protection.
* Executable/script upload prevention.
* Public vs private file storage.
* Whether uploaded files can execute code.
* Whether users can overwrite existing files.
* Whether users can access files belonging to another user.
* File deletion/replacement security.
* Original filenames containing unsafe characters.
* Storage URLs exposing sensitive information.

For payment screenshots, only accept the image formats actually needed.

Do not trust the filename or Content-Type provided by the browser.

Fix confirmed upload vulnerabilities using Laravel's built-in validation and storage mechanisms.

-----

# 7. Payment Validation Security Check

Audit the GCash/manual payment workflow for security and transaction-integrity issues.

Rules:

* Treat all payment information submitted by the frontend as untrusted.
* Keep the implementation simple.
* Follow existing Laravel/Vue patterns.
* Do not create tests.
* Do not refactor unrelated code.

Check:

* Payment amount validation.
* Payment reference validation.
* Payment screenshot validation.
* Order/pautang ownership.
* Remaining balance calculation.
* Preventing overpayment or invalid payment amounts.
* Preventing payment against an already fully paid order.
* Payment status transitions.
* Who is allowed to approve/reject payments.
* Preventing normal users from marking their own payment as verified/paid.
* Preventing frontend manipulation of amount, balance, status, user ID, or order ID.
* Database transaction usage where payment approval updates multiple records.
* Audit/activity logging if the project already supports it.

Important:

* Calculate authoritative balances and totals on the server.
* Never trust payment totals calculated by Vue.
* Payment verification must be controlled server-side.

Fix all confirmed payment-integrity vulnerabilities while preserving the existing workflow.

-----

# 8. Points System Protection Check



-----

# 9. Migration Fix

Review and clean up all migration files.

Requirements:

* Treat the project as a fresh Laravel application with a new/empty database.
* Remove migration logic that checks for existing data, existing columns, existing tables, or updates/migrates old records only for backward compatibility.
* Remove data-fixing, data-migration, fallback, and legacy compatibility logic that is only needed for an existing production database.
* Define the final database structure directly in the migrations as it should exist on a fresh install.
* Make sure `php artisan migrate:fresh` can build the complete database correctly from zero.
* Keep migrations simple, clean, and straightforward.
* Do not preserve old data or support previous database states.
* Do not add unnecessary migrations if an existing migration can simply be corrected.
* Do not refactor unrelated application code.
* Do not create tests.

After updating, review the migration order, foreign keys, indexes, defaults, nullable fields, and dependencies to make sure a fresh migration runs correctly without errors.

