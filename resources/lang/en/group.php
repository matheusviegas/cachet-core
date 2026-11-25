<?php

return [
    'resource_label' => 'Group|Groups',
    'defaults' => [
        'administrators' => 'Administrators',
        'read_only' => 'Read only',
    ],
    'form' => [
        'name_label' => 'Name',
        'permissions_label' => 'Permissions',
        'permissions_helper' => 'Members can do everything their groups allow. Managing or deleting a resource also lets members view it.',
        'default_helper' => 'Default groups follow the list of permissions and cannot be changed.',
    ],
    'list' => [
        'headers' => [
            'name' => 'Name',
            'permissions' => 'Permissions',
            'users' => 'Members',
        ],
    ],
    'users' => [
        'title' => 'Members',
    ],
];
