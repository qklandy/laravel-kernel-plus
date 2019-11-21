<?php

return [
    'laravel_expect' => [
        'schedule:run',
        'schedule:finish',
        'list',
    ],
    'load_cmd_dirs'  => [
        'app/Console/Commands',
        'app/' . env('KERNEL_PLUS_MODULE_DIR', 'Biz'),
    ]
];
