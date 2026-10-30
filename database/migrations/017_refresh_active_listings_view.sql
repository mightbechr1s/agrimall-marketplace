-- AgriMALL Migration 017: refresh v_active_listings to include discount_percent.
DROP VIEW IF EXISTS `v_active_listings`;
CREATE OR REPLACE VIEW `v_active_listings` AS
SELECT
  pl.*,
  pc.name AS category_name,
  pc.slug AS category_slug,
  qu.symbol AS unit_symbol,
  qu.type AS unit_type,
  qu.step AS unit_step,
  fp.farm_name,
  fp.barangay,
  fp.municipality_city,
  fp.province,
  fp.verification_status,
  (
    SELECT li.image_path
    FROM listing_images li
    WHERE li.listing_id = pl.id AND li.is_primary = 1
    ORDER BY li.sort_order
    LIMIT 1
  ) AS primary_image
FROM `produce_listings` pl
JOIN `produce_categories` pc ON pl.category_id = pc.id
JOIN `quantity_units` qu ON pl.unit_id = qu.id
JOIN `farmer_profiles` fp ON pl.farmer_id = fp.user_id
WHERE pl.status = 'active'
  AND fp.verification_status = 'approved'
  AND pc.is_active = 1
  AND qu.is_active = 1
  AND pl.available_quantity > 0
  AND (pl.expiry_date IS NULL OR pl.expiry_date = '' OR pl.expiry_date >= CURDATE());
