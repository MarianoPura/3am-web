-- Read-only live category audit. No data is changed by these queries.
-- 1. All category IDs, slugs, activation state, and active equipment counts.
SELECT c.id, c.name, c.slug, c.is_active, COUNT(i.id) AS active_items
FROM rental_categories c
LEFT JOIN rental_items i ON i.category_id = c.id
    AND i.is_active = 1 AND c.is_service = 0
GROUP BY c.id, c.name, c.slug, c.is_active
ORDER BY c.id;

-- 2. Repeated active names using the database's comparison rules.
SELECT name, COUNT(*) AS total
FROM rental_categories
WHERE is_active = 1
GROUP BY name
HAVING COUNT(*) > 1;

-- 3. Normalized repeated names, including their IDs and every assigned item.
-- This does not assume that similarly named records can safely be merged.
SELECT c.id AS category_id, c.name, c.slug, c.is_active,
    i.id AS item_id, i.name AS item_name, i.slug AS item_slug,
    i.is_active AS item_active, c.is_service
FROM rental_categories c
LEFT JOIN rental_items i ON i.category_id = c.id
WHERE c.is_active = 1 AND EXISTS (
    SELECT 1 FROM rental_categories other
    WHERE other.is_active = 1 AND other.id <> c.id
        AND LOWER(TRIM(other.name)) = LOWER(TRIM(c.name))
)
ORDER BY c.id, i.id;
