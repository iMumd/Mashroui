<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | يقتصر الوصول عبر المصادر (CORS) على واجهة مسار فقط بدل الافتراضي "*".
    | المصادقة عبر Bearer token (وليست كوكيز)، لذا supports_credentials = false.
    | أضف مصادر تطوير محلية هنا عند الحاجة.
    |
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    'allowed_origins' => [
        'https://masar.slidd.app',
    ],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => false,

];
