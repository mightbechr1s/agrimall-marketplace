<?php
// AnimalMarketplace — config template.
// Copy this file to config.php and fill in your local values.
// Never commit config.php (see .gitignore).

return [
    'db' => [
        'host'   => '127.0.0.1',
        'port'   => 3306,
        'name'   => 'animal_marketplace',
        'user'   => 'root',
        'pass'   => '',
        'charset' => 'utf8mb4',
    ],
    'app' => [
        'name'       => 'AnimalMarketplace',
        'base_url'   => '',
        'timezone'   => 'Asia/Manila',
        'env'        => 'development', // 'development' | 'production'
        'session_lifetime_minutes' => 30,
    ],
];