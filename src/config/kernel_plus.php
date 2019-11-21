<?php

return [
    'laravel_expect' => [
        'schedule:run',
        'schedule:finish',
        'list',
    ],
    'load_cmd_dirs'  => [
        app()->basePath('App/Console/Commands'),
        app()->basePath('App/' . env('KERNEL_PLUS_MODULE_DIR', 'Biz')),
    ]
];