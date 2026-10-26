-- 013_active_listings_view_expiry.sql
-- Expiry is evaluated in PHP via listing_is_expired() (FR-LIST-033), not in
-- this view, so the view stays a simple active-listings projection and every
-- consumer applies the same expiry rule in application code.

CREATE OR REPLACE VIEW v_active_listings AS
SELECT
    pl.id, pl.farmer_id, pl.category_id, pl.unit_id,
    pl.name, pl.description,
    pl.price_per_unit, pl.available_quantity,
    pl.minimum_order_qty, pl.maximum_order_qty,
    pl.harvest_date, pl.expiry_date,
    pl.is_organic, pl.status, pl.rejection_reason,
    pl.created_at, pl.updated_at, pl.published_at,
    pc.name AS category_name, pc.slug AS category_slug,
    qu.symbol AS unit_symbol, qu.type AS unit_type, qu.step AS unit_step,
    fp.farm_name, fp.barangay, fp.municipality_city, fp.province,
    fp.verification_status,
    (SELECT li.image_path
       FROM listing_images li
      WHERE li.listing_id = pl.id AND li.is_primary = 1
      ORDER BY li.sort_order
      LIMIT 1) AS primary_image
FROM produce_listings pl
JOIN produce_categories pc ON pl.category_id = pc.id
JOIN quantity_units   qu ON pl.unit_id = qu.id
JOIN farmer_profiles  fp ON pl.farmer_id = fp.user_id
WHERE pl.status = 'active'
  AND fp.verification_status = 'approved'
  AND pc.is_active = 1
  AND qu.is_active = 1
  AND pl.available_quantity > 0
;
