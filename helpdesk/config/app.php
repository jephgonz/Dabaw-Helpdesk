<?php
declare(strict_types=1);

return [
    'name' => getenv('APP_NAME') ?: 'Dabaw Helpdesk',
    'environment' => getenv('APP_ENV') ?: 'local',
    'base_path' => rtrim(getenv('APP_BASE_PATH') ?: '/helpdesk', '/'),
];
