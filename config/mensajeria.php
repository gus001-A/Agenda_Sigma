<?php

/*
| Proveedores de notificación por canal.
| - "log": registra el mensaje en storage/logs (simulación, sin proveedor externo).
| - "mail": usa el mailer de Laravel (solo para "Correo electrónico").
| La integración con WhatsApp Business API / SMS se define en la etapa técnica:
| basta con agregar el driver correspondiente en App\Services\Mensajeria::despachar().
*/
return [
    'canales' => [
        'WhatsApp' => env('MENSAJERIA_WHATSAPP', 'log'),
        'SMS' => env('MENSAJERIA_SMS', 'log'),
        'Correo electrónico' => env('MENSAJERIA_CORREO', 'log'),
    ],
];
