<?php
// config.php
return [
    'db' => [
        'host'     => 'localhost',
        'dbname'   => 'u2611449_krasnoe',
        'username' => 'u2611449_krasnoe',
        'password' => 'u2611449_krasnoe',
        'charset'  => 'utf8mb4'
    ],
    'email' => [
        'from' => 'noreply@example.com',
        // параметры SMTP (если понадобится)
        'smtp' => [
            'host'       => 'smtp.example.com',
            'username'   => 'smtp_user',
            'password'   => 'smtp_pass',
            'port'       => 587,
            'encryption' => 'tls'
        ]
    ],
    'app' => [
        'base_url' => 'http://' . $_SERVER['HTTP_HOST']
    ]
];
