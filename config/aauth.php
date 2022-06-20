<?php
// config for Aurora/AAuth
return [
    'permissions' => [
            'system' => [
                // example system permission
                // key => translation
                'edit_something' => 'aauth/system.edit_something',
                'create_something' => 'aauth/system.create_something',
            ],
            'organization' => [
                // example organization permission
                'edit_something' => 'aauth/organization.edit_site_name',
                'create_something' => 'aauth/organization.edit_site_name',
            ],
        ],
];
