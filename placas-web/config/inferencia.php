<?php

// Inferencia por lotes: las 20 imágenes de la carpeta INFERENCIA y su placa real
// (leída a mano de cada foto) para contar los aciertos de cada entorno.
return [
    'carpeta' => base_path('INFERENCIA'),
    'resultados' => storage_path('app/inferencia.json'),

    'placas_reales' => [
        'Bahrain_1003919.jpg' => '653037',
        'Bahrain_1004035.jpg' => '73049',
        'Bahrain_1004054.jpg' => '32789',
        'Ireland_1005977.jpg' => '07D40426',
        'Ireland_1006025.jpg' => '04W415',
        'Ireland_1029650.jpg' => '231D45186',
        'Ireland_1059723.jpg' => '172G2064',
        'Ireland_1183637.jpg' => '04RN2211',
        'Ireland_895907.jpg' => 'AS7284',
        'Norway_1007317.jpg' => 'KH90284',
        'Norway_1342295.jpg' => 'ZE61026',
        'Norway_972408.jpg' => 'ZH21710',
        'Norway_972486.jpg' => 'XK30060',
        'USA_1215816.jpg' => 'BG6W544',
        'USA_1215867.jpg' => 'UCZ595',
        'USA_2071067.jpg' => 'FLL8089',
        'USA_2229862.jpg' => 'RJB7',
        'USA_973865.jpg' => 'NI5179',
        'USA_973932.jpg' => 'EV3967',
        'USA_973960.jpg' => 'NI203',
    ],
];
