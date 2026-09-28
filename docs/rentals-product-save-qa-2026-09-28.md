# Rentals product save fix — 28 September 2026

The live form's “Could not save. Check required fields and unique slug or SKU”
message was a catch-all for database and file-storage failures. A 302 followed
by 200 is the form redirect after that exception, not evidence of a successful
save. The supplied server listing shows project-local micro owned by root,
with no write permission for the apache group. The exact live exception has
not been inspected; local fixes are prepared for the senior to deploy.

## Change

- New uploads go to micro beside 3am-web, outside the checkout. Existing local
  uploads were moved with all files preserved. Existing database references and
  product image URLs still work; legacy project-local files remain readable.
- Product category and quantity require valid integers. Type and availability
  require valid selections. Unicode names/SKUs/descriptions/units are checked
  by character count. Overlong entries get a named error instead of truncation.
- Slug/SKU stay optional. Deposit can be blank (zero). Decimal rates and deposits
  use the database's range and are stored without an intermediate float.
- Upload limits reflect PHP settings. Oversized product requests return a clear
  413 instead of an expired-session error. Image/storage errors no longer blame
  SKU. Failed saves retain text/selections and show an accessible “Not saved”
  notice. No deployment instructions appear in the website UI.
- php bin/rentals.php --check remains read-only and additionally checks optional
  product column compatibility and external storage permissions.

## Deployment note for the senior

Before pulling the commit, copy the server's existing micro uploads to the
sibling micro directory, preserving all file names. This also preserves the old
product image that was previously tracked in Git. Give the actual PHP worker
write access to the storage subdirectories and apply the deny template there.
Do not grant write access to the whole project or use world-writable permissions.
Keep the existing application key unchanged so historical proofs remain readable.
The optional absolute RENTALS_STORAGE_ROOT setting is only needed if the host
uses a different external location. No .env or database schema changes were made
locally, and no live server writes were performed.

## Verification

PASS: expanded `tests/rentals-admin-qa.php` at root and mounted URLs, including
category/type/status/unit/numeric/text bounds, automatic URLs, optional SKU and
deposit, retained failed entries, upload rejection and external product/QR image
delivery. The mounted run used 2 MB upload and 3 MB POST limits, checking the
explicit 413 response. PHP lint, JS syntax and `git diff --check` passed.

PASS: `tests/rentals-http-flow.php` at root, including encrypted proof storage,
approval, rejection and proof resubmission; integration, URL, cart and completion
checks passed. Browser verification confirmed a visible duplicate-SKU error,
retained form entries, and a successful product save after clearing the SKU.
Screenshots are saved outside the repository in the task visualization folder.

Temporary fixtures and credentials were removed. Customer uploads and records
were preserved. The Payment Report follow-up requested no commit/push/merge;
all this work therefore remains local and uncommitted.
