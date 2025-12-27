<?php
return [
    'adminEmail' => 'admin@example.com',
    'jwtSecret' => getenv('JWT_SECRET') ?: null,
    'jwtTtl' => getenv('JWT_TTL') ? (int)getenv('JWT_TTL') : null,
];
