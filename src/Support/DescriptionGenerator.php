<?php

namespace Laraditz\Jaga\Support;

use Illuminate\Support\Str;

class DescriptionGenerator
{
    private static array $templates = [
        'index' => 'List all :resource',
        'show' => 'View a :resource',
        'store' => 'Create a :resource',
        'update' => 'Update a :resource',
        'destroy' => 'Delete a :resource',
        'edit' => 'Edit :resource',
    ];

    public static function group(string $routeName): ?string
    {
        $segments = explode('.', $routeName);

        if (count($segments) < 2) {
            return null;
        }

        $segment = $segments[count($segments) - 2];

        return Str::title(Str::replace(['-', '_'], ' ', $segment));
    }

    public static function generate(string $routeName): string
    {
        $segments = explode('.', $routeName);

        if (count($segments) < 2) {
            return Str::apa($routeName);
        }

        $action = array_pop($segments);
        $rawResource = array_pop($segments);
        $resource = $action === 'index' ? $rawResource : Str::singular($rawResource);

        if (!isset(self::$templates[$action])) {
            return $routeName;
        }

        return str_replace(':resource', $resource, self::$templates[$action]);
    }
}
