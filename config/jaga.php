<?php

return [
    'guard' => 'web',

    'cache' => [
        'enabled' => true,
        'ttl' => 3600,
        'key_prefix' => 'jaga',
    ],

    'sync' => [
        'exclude_uri_prefixes' => ['telescope', '_debugbar', 'horizon', '_ignition', 'storage', 'sanctum', 'livewire-', '_boost'],
        'exclude_name_prefixes' => ['telescope.', 'debugbar.', 'horizon.', 'ignition.', 'storage.', 'sanctum.', 'livewire.', 'boost.'],
    ],

    'ownership' => [
        'owner_key' => 'user_id',
        'owner_model' => \App\Models\User::class,
    ],

    'permissions' => [
        // 'route.name' => ['description' => '...', 'group' => '...'],
        'invitations.accept' => [
            'description' => 'Accept an invitation to join a team',
            'group' => 'Teams',
        ]
    ],

    'seeder' => [
        'path' => null, // null = database_path('seeders/JagaSeeder.php')
    ],

    'tables' => [
        'roles' => 'roles',
        'permissions' => 'permissions',
        'model_role' => 'model_role',
        'role_permission' => 'role_permission',
        'model_permission' => 'model_permission',
    ],
];
