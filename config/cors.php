<?php

/*
|--------------------------------------------------------------------------
| Cross-Origin Resource Sharing (CORS) Configuration
|--------------------------------------------------------------------------
|
| يقتصر الوصول عبر المصادر (CORS) على واجهة مسار فقط بدل الافتراضي "*".
| المصادقة عبر Bearer token (وليست كوكيز)، لذا supports_credentials = false.
| في بيئة التطوير (local) تُضاف مصادر فيتي المحلية حتى يعمل تسجيل الدخول من المتصفح.
|
*/

$origins = ['https://masar.slidd.app'];

if (env('APP_ENV') === 'local') {
    $origins[] = 'http://localhost:5173';
    $origins[] = 'http://127.0.0.1:5173';

    // مصادر إضافية للتطوير من أجهزة الشبكة (مفصولة بفاصلة)
    foreach (explode(',', (string) env('CORS_LOCAL_ORIGINS', '')) as $extra) {
        $extra = trim($extra);
        if ($extra !== '') {
            $origins[] = $extra;
        }
    }
}

return [

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    'allowed_origins' => $origins,

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => false,

];
