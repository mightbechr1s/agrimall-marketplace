<?php
// Inline definitions for the two reporting views.
//
// Some shared hosts (InfinityFree) do not grant CREATE VIEW. The PDO wrapper
// expands "FROM v_*" into these derived tables, so every query keeps using the
// view names while the database needs only tables.
declare(strict_types=1);

function active_listings_sql(): string
{
    return <<<'SQL'
SELECT
    pl.*,
    CASE WHEN pl.expiry_date IS NOT NULL AND pl.expiry_date <> '' AND pl.expiry_date < CURDATE() THEN 1 ELSE 0 END AS is_expired,
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
FROM produce_listings pl
JOIN produce_categories pc ON pl.category_id = pc.id
JOIN quantity_units qu ON pl.unit_id = qu.id
JOIN farmer_profiles fp ON pl.farmer_id = fp.user_id
WHERE pl.status = 'active'
    AND fp.verification_status = 'approved'
    AND pc.is_active = 1
    AND qu.is_active = 1
    AND pl.available_quantity > 0
SQL;
}

function order_summary_sql(): string
{
    return <<<'SQL'
SELECT
    o.*,
    CONCAT(bp.full_name) AS buyer_name,
    bp.contact_phone AS buyer_phone,
    fp.farm_name,
    fp.contact_person AS farmer_contact_person,
    fp.contact_phone AS farmer_phone,
    pl.name AS listing_name,
    pl.unit_id,
    qu.symbol AS unit_symbol
FROM orders o
JOIN buyer_profiles bp ON o.buyer_id = bp.user_id
JOIN farmer_profiles fp ON o.farmer_id = fp.user_id
JOIN produce_listings pl ON o.listing_id = pl.id
JOIN quantity_units qu ON pl.unit_id = qu.id
SQL;
}

function expand_view_aliases(string $sql): string
{
    $sql = (string) preg_replace(
        '/\bFROM\s+v_active_listings\b/i',
        'FROM (' . active_listings_sql() . ') AS v_active_listings',
        $sql
    );
    return (string) preg_replace(
        '/\bFROM\s+v_order_summary\b/i',
        'FROM (' . order_summary_sql() . ') AS v_order_summary',
        $sql
    );
}
