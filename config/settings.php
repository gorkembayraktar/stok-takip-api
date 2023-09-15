<?php
// Database settings
return [
    "basePath" => "/stok-takip/api",
    "jwtSettings" => [
        'secret' => 'a21o21o2o12o1-fsakfafk-m2121', // Gizli anahtarınızı buraya ekleyin
        'algorithm' => 'HS256', // Kullanmak istediğiniz JWT algoritması
        'expires' => '1 hour', // Token'ın süresi
    ],
    "db" => [
        'driver' => 'mysql',
        'host' => 'localhost',
        'database' => 'stok-takip',
        'username' => 'root',
        'password' => '',
        'charset' => 'utf8mb4',
        'collation' => 'utf8mb4_unicode_ci'
    ]
];