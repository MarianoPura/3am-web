-- PROPOSAL ONLY. NOT EXECUTED. Do not run to make the Services count nonzero.
-- The owner must first confirm that item 7 is a quotation-based service and choose
-- an appropriate existing active Service category. Do not assume Film Production
-- (category 7) is appropriate just because it is currently a Service category.
-- First run rentals-live-services-diagnostics.sql and review every dependency.
-- Keep category 6 unchanged. Existing equipment booking history needs a separate
-- business plan rather than silently changing historical analytics/classification.

-- Default NULL is deliberately a no-op. Replace only after the business decision
-- and explicit approval to change production data, then review the guarded UPDATE.
SET @approved_service_category_id = NULL;

UPDATE rental_items i
JOIN rental_categories source ON source.id = i.category_id
JOIN rental_categories target ON target.id = @approved_service_category_id
SET i.category_id = target.id
WHERE i.id = 7 AND i.name = 'Video Production' AND i.category_id = 6
  AND i.is_active = 1 AND source.is_service = 0
  AND target.is_service = 1 AND target.is_active = 1
  AND NOT EXISTS (SELECT 1 FROM order_details d WHERE d.rental_item_id = i.id)
  AND NOT EXISTS (SELECT 1 FROM cart_items ci WHERE ci.rental_item_id = i.id)
  AND NOT EXISTS (SELECT 1 FROM rental_item_blackouts b WHERE b.rental_item_id = i.id)
  AND NOT EXISTS (SELECT 1 FROM rental_service_requests r WHERE r.rental_item_id = i.id);

-- If the requests table is absent, this proposal is not ready to execute.
-- If any dependency exists, zero rows change. Do not remove these guards to force
-- a conversion; preserve the existing Equipment workflow and seek a reviewed plan.
