<?php

/*
|--------------------------------------------------------------------------
| AAuth Permission Definitions (Template - UI Reference)
|--------------------------------------------------------------------------
|
| This config is OPTIONAL and used for UI display only.
| Code works defensively: uses description/parameters if present, skips if not.
| The actual permission control is in the database (role_permission.parameters).
|
| Structure:
| 'resource' => [
|     'action' => [
|         'key' => 'resource.action',           // Required: unique permission key
|         'description' => 'Human readable',    // Optional: for UI display
|         'parameters' => [                     // Optional: for parametric permissions
|             'param_name' => [
|                 'type' => 'integer|array|boolean',
|                 'default' => null,
|                 'description' => 'Param description',
|             ],
|         ],
|     ],
| ],
|
*/

return [
    // Example: Posts permissions
    // 'posts' => [
    //     'view' => [
    //         'key' => 'posts.view',
    //     ],
    //     'edit' => [
    //         'key' => 'posts.edit',
    //         'description' => 'Edit posts',
    //         'parameters' => [
    //             'max_edits_per_day' => [
    //                 'type' => 'integer',
    //                 'default' => null,
    //                 'description' => 'Maximum edits per day',
    //             ],
    //             'allowed_statuses' => [
    //                 'type' => 'array',
    //                 'default' => ['draft', 'published', 'archived'],
    //                 'description' => 'Which statuses can be edited',
    //             ],
    //         ],
    //     ],
    //     'delete' => [
    //         'key' => 'posts.delete',
    //         'description' => 'Delete posts',
    //         'parameters' => [
    //             'can_force_delete' => [
    //                 'type' => 'boolean',
    //                 'default' => false,
    //                 'description' => 'Can permanently delete',
    //             ],
    //         ],
    //     ],
    // ],
];
