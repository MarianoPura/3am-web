-- PROPOSAL ONLY: not applied; requires owner/senior approval before execution.
-- Run this read-only check first on the intended database.
SELECT COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE
FROM information_schema.columns
WHERE table_schema = DATABASE()
  AND table_name = 'rental_items'
  AND column_name IN ('image_path', 'additional_image_paths');

-- Execute only after approval, and only if additional_image_paths is absent.
-- Existing image_path values and every existing product remain unchanged.
ALTER TABLE rental_items
  ADD COLUMN additional_image_paths JSON NULL;

-- No UPDATE, DROP, renamed columns, new relations, indexes or foreign keys.
