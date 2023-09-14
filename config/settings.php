<?php
// Database settings
return [
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
        'collation' => 'utf8mb4_unicode_ci',
        'prefix' => '',
        'options' => [
            // Turn off persistent connections
            PDO::ATTR_PERSISTENT => false,
            // Enable exceptions
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            // Emulate prepared statements
            PDO::ATTR_EMULATE_PREPARES => true,
            // Set default fetch mode to array
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            // Set character set
            PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci'
        ],
    ]
];