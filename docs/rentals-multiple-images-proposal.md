# Multiple equipment images — implementation proposal

The current repository and read-only local schema inspection support one
`rental_items.image_path` (nullable varchar(500)). There is no existing gallery
JSON column or media/image relation to reuse. The senior intentionally removed
the old `rental_item_images` table. It has not been recreated.

## Proposed additive storage

Add one nullable JSON column: `rental_items.additional_image_paths`.
Keep `image_path` as the primary image, first in the customer gallery. Store an
ordered array of additional managed relative image references in the new column.
NULL means no extra images. No backfill is required for current products.
No indexes or foreign keys are needed because this is an ordered list of storage
references attached to the existing item, not a second entity or availability model.

The exact read-only check and additive ALTER are in
`docs/rentals-multiple-images-proposal.sql`. Review server JSON support/version
before execution. The SQL has NOT been executed locally or live. Adding a
column can briefly lock the table; the server administrator should choose an
appropriate deployment window. No existing column, table, record or primary
image is deleted or renamed.

## Planned Admin behavior

- Retain the current primary-image field and upload behavior.
- Add optional multiple upload, thumbnails and individual remove/replace controls.
- Reuse `RentalManagedImage`, `RentalStorage`, MIME/size validation, randomized
  filenames, existing Admin authorization and CSRF protection.
- Enforce per-file and total-request upload limits and a small explicit gallery
  limit. A rejected upload leaves the previous gallery intact.
- Save ordered paths with the item; clean new files if persistence fails. Remove
  replaced files only after saving and after checking every primary/gallery/category
  reference, so shared files are never deleted accidentally.
- Legacy single-image equipment and services retain current behavior.

## Planned customer behavior

- Cards use the existing primary image; equipment detail shows it first.
- Previous/next, swipe, keyboard navigation, image count and useful alternative text.
- Single-image items show no unnecessary carousel controls.
- Reuse the managed `/rentals/product-image/<filename>` URL builder; never expose
  external `/micro` storage directly. Existing repository media keeps its normal URL.
- Keep the branded missing-image fallback, quantity/calendar behavior and mobile
  layout. Failed extra images receive the same clean fallback handling.

## Status

No gallery schema change, multiple-upload implementation or carousel was applied.
The original Fixes(2).docx requests this feature. The requirement to stop for
schema approval was added by the generated prompt, not by the original document.
This remains a proposal, not a completed feature. No automatic live database
changes, committing, pushing or merging are authorized.
