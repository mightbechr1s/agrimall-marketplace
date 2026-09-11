-- Default roles
INSERT INTO roles (slug, name)
VALUES
    ('buyer', 'Buyer'),
    ('farmer', 'Farmer'),
    ('admin', 'Administrator')
ON DUPLICATE KEY UPDATE name = VALUES(name);

-- Default produce categories
INSERT INTO categories (name, description)
VALUES
    ('Vegetables', 'Fresh vegetables'),
    ('Fruits', 'Fresh fruits'),
    ('Rice and Grains', 'Rice and other grains'),
    ('Root Crops', 'Root crops and similar produce'),
    ('Other Produce', 'Other agricultural products')
ON DUPLICATE KEY UPDATE description = VALUES(description);
