-- AgriMALL Migration 018: keep expired active listings visible in the catalogue
-- (FR-LIST-033) so rejection is observable, and flag them as expired.
DROP VIEW IF EXISTS `v_active_listings`;
CREATE OR REPLACE VIEW `v_active_listings` AS
SELECT
  pl.*,
  (CASE WHEN pl.expiry_date IS NOT NULL AND pl.expiry_date <> '' AND pl.expiry_date < CURDATE() THEN 1 ELSE 0 END) AS is_expired,
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
  AND pl.available_quantity > 0;
