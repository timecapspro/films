<?php
return [
    'adminEmail' => 'admin@example.com',
    'jwtSecret' => getenv('JWT_SECRET') ?: null,
    'jwtTtl' => getenv('JWT_TTL') ? (int)getenv('JWT_TTL') : null,
    'kinopoiskApiKey' => 'c7c43696-f623-415a-bbad-019e665f448b',
];
