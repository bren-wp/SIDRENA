<?php
return [
    'site_name' => 'Moj javni cjenik',
    'timezone' => 'Europe/Zagreb',
    'currency' => 'EUR',

    // Ako je true, neispravni/nepotpuni retci zaustavljaju novu objavu i
    // zadnji valjani cjenik ostaje aktivan.
    'strict_validation' => true,

    // Praktično za shared hosting bez crona: index.php/api.php provjeravaju
    // jesu li source CSV datoteke novije od zadnjeg snapshota.
    'auto_generate_on_request' => true,
    'auto_generate_min_interval' => 60,

    // Javne objave čuvaju se najmanje 30 dana.
    'retention_days' => 45,
    'csv_delimiter' => ';',

    // Prazno = web generate.php je isključen. CLI generiranje uvijek radi.
    // Za web generiranje postavite dugačak slučajni token ili, još bolje,
    // environment varijablu SIDRENA_GENERATE_TOKEN.
    'web_generate_token' => '',

    'location' => [
        'id' => 'webshop',
        'kind' => 'webshop',
        'code' => 'WEB-01',
        'address' => 'online',
        'sequence_products' => 1,
        'sequence_services' => 2,
    ],

    'sources' => [
        'products' => 'storage/source/products.csv',
        'services' => 'storage/source/services.csv',
    ],
];
