<?php

return [

    'secret' => env('JWT_SECRET'),

    'ttl' => (int) env('JWT_TTL', 120),

    'algorithm' => 'HS256',

];