<?php

/*
| Proveedores de notificación por canal.
| - "log": registra el mensaje en storage/logs (simulación, sin proveedor externo).
| - "mail": usa el mailer de Laravel (solo para "Correo electrónico").
| - "twilio": envía SMS reales con Twilio (solo para "SMS"); credenciales en
|   config/services.php (TWILIO_SID, TWILIO_TOKEN, TWILIO_FROM).
| La integración con WhatsApp Business API se agrega como otro driver en
| App\Services\Mensajeria::despachar().
*/
return [
    'canales' => [
        'WhatsApp' => env('MENSAJERIA_WHATSAPP', 'log'),
        'SMS' => env('MENSAJERIA_SMS', 'log'),
        'Correo electrónico' => env('MENSAJERIA_CORREO', 'log'),
    ],
];
