<?php

return [
    'guard' => 'web',

    'cache' => [
        'enabled' => true,
        'ttl' => 3600,
        'key_prefix' => 'jaga',
    ],

    'sync' => [
        'exclude_uri_prefixes'  => ['telescope', '_debugbar', 'horizon'],
        'exclude_name_prefixes' => ['telescope.', 'debugbar.', 'horizon.'],
    ],

    'ownership' => [
        'owner_key'   => 'user_id',
        'owner_model' => \App\Models\User::class,
    ],

    'tables' => [
        'roles'            => 'roles',
        'permissions'      => 'permissions',
        'model_role'       => 'model_role',
        'role_permission'  => 'role_permission',
        'model_permission' => 'model_permission',
    ],
];
