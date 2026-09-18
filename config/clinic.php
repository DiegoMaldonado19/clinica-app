<?php

/*
 | Datos de la clinica que no son reglas de negocio. Las reglas RN-xx viven en
 | `business_rule_settings`, no aqui.
 */
return [
    'name' => env('APP_NAME', 'Clínica Psicología y Bienestar'),

    // UTC en la base; esta zona solo para presentacion y para juzgar "el mismo dia".
    'timezone' => 'America/Guatemala',

    'phone' => env('CLINIC_PHONE', '2222-0000'),
    'email' => env('CLINIC_EMAIL', 'contacto@clinica.test'),
    'address' => env('CLINIC_ADDRESS', 'Ciudad de Guatemala'),

    // Llave del contenido SOAP, distinta de APP_KEY (doc 05 §2.3).
    'encryption_key' => env('CLINICAL_ENCRYPTION_KEY'),

    /*
     | Textos versionados de lo que el paciente acepta al agendar. `consents`
     | guarda codigo y version; cambiar un texto exige una version nueva.
     */
    'consents' => [
        'CANCELACION' => [
            '2026-09' => 'Cancelar con 24 horas o más de anticipación no tiene costo. Entre 24 horas y 1 hora se cobra el 50 % de la tarifa. Con menos de 1 hora, o si no asistes, se cobra el 100 %.',
        ],
        'PRIVACIDAD' => [
            '2026-09' => 'Tus datos se usan solo para coordinar tu atención. El contenido clínico está cifrado y solo la psicóloga tiene acceso a él.',
        ],
    ],
];
