<?php

use Laraditz\Jaga\Support\DescriptionGenerator;

it('generates description for standard RESTful actions', function (string $routeName, string $expected) {
    expect(DescriptionGenerator::generate($routeName))->toBe($expected);
})->with([
    ['posts.index',        'List all posts'],
    ['posts.show',         'View a post'],
    ['posts.store',        'Create a post'],
    ['posts.update',       'Update a post'],
    ['posts.destroy',      'Delete a post'],
    ['admin.users.index',  'List all users'],
    ['admin.users.store',  'Create a user'],
    ['admin.users.update', 'Update a user'],
]);

it('falls back to raw route name for unknown actions', function () {
    expect(DescriptionGenerator::generate('reports.export.csv'))->toBe('reports.export.csv');
});

it('singularises the resource correctly', function () {
    expect(DescriptionGenerator::generate('categories.store'))->toBe('Create a category');
});

it('derives group from penultimate segment of a route name', function () {
    expect(DescriptionGenerator::group('posts.index'))->toBe('Posts');
    expect(DescriptionGenerator::group('posts.show'))->toBe('Posts');
    expect(DescriptionGenerator::group('admin.users.index'))->toBe('Users');
    expect(DescriptionGenerator::group('admin.posts.destroy'))->toBe('Posts');
});

it('returns null for single-segment route names', function () {
    expect(DescriptionGenerator::group('dashboard'))->toBeNull();
});

it('converts hyphens and underscores to spaces and title-cases the group', function () {
    expect(DescriptionGenerator::group('blog-posts.index'))->toBe('Blog Posts');
    expect(DescriptionGenerator::group('admin.user_roles.index'))->toBe('User Roles');
});
