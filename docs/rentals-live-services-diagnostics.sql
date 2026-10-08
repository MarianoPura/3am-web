-- READ ONLY. Run privately against the deployed application's configured database,
-- using its runtime database account so table visibility matches the website.
SELECT DATABASE() AS configured_database, VERSION() AS database_version;

SELECT TABLE_NAME, TABLE_TYPE
FROM information_schema.TABLES
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME IN ('rental_categories', 'rental_items', 'rental_service_requests',
                    'rental_notification_deliveries', 'users', 'payment_methods');

-- Compare the result to RentalSchema::COLUMNS and RentalServiceRequests::workflowReady.
-- An absent requests table explains ready() = false / the explicit HTTP 503 path.
-- Missing quotation/payment columns affect workflow readiness separately.
SELECT TABLE_NAME, COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME IN ('rental_categories', 'rental_items', 'rental_service_requests')
ORDER BY TABLE_NAME, ORDINAL_POSITION;

SELECT c.id, c.name, c.is_service, c.is_active,
       COUNT(i.id) AS items,
       SUM(CASE WHEN i.is_active = 1 THEN 1 ELSE 0 END) AS active_items,
       SUM(CASE WHEN i.is_active = 1 AND c.is_active = 1 THEN 1 ELSE 0 END) AS visible_items
FROM rental_categories c LEFT JOIN rental_items i ON i.category_id = c.id
GROUP BY c.id, c.name, c.is_service, c.is_active
ORDER BY c.id;

SELECT c.is_service, COUNT(*) AS visible_items
FROM rental_items i JOIN rental_categories c ON c.id = i.category_id
WHERE i.is_active = 1 AND c.is_active = 1 AND c.is_service IN (0, 1)
GROUP BY c.is_service;

SELECT i.id, i.category_id
FROM rental_items i LEFT JOIN rental_categories c ON c.id = i.category_id
WHERE c.id IS NULL;

-- Business classification evidence; names alone do not establish intent.
SELECT i.id, i.name, i.category_id, c.name AS category_name, c.is_service,
       i.is_active, i.description, i.ideal_use, i.rental_unit, i.rental_rate,
       i.security_deposit, i.available_quantity, i.availability_status
FROM rental_items i JOIN rental_categories c ON c.id = i.category_id
WHERE i.id = 7 OR c.id IN (6, 7)
ORDER BY i.id;

-- Check all category-6 products and historical equipment usage before any decision.
SELECT i.id, i.name,
       (SELECT COUNT(*) FROM order_details d WHERE d.rental_item_id = i.id) AS order_lines,
       (SELECT COUNT(*) FROM cart_items ci WHERE ci.rental_item_id = i.id) AS cart_lines,
       (SELECT COUNT(*) FROM rental_item_blackouts b WHERE b.rental_item_id = i.id) AS blackout_rows
FROM rental_items i WHERE i.category_id = 6;

-- Run these only AFTER confirming rental_service_requests exists above.
SELECT status, COUNT(*) AS requests FROM rental_service_requests GROUP BY status;
SELECT COUNT(*) AS visible_request_table
FROM information_schema.TABLES
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rental_service_requests';
