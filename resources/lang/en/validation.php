<?php
return [
    'custom' => [
        'delivery_type' => [
            'required' => 'Delivery type is required'
        ],
        'vehicle_type' => [
            'required' => 'Vehicle type is required'
        ],

        'shortcut_name.required' => [
            'Shortcut name is required.'
        ],
        'shortcut_name.string' => [
            'Shortcut name must be a string.'
        ],
        'shortcut_name.max' => [
            'Shortcut name must not be more than 5 characters.'
        ],
        'shortcut_name.regex' => [
            'Shortcut name must contain only uppercase English letters (A-Z).'
        ],

    ]
];
