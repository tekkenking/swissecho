<?php

use Tekkenking\Swissecho\Routes\Sms\Gateways\Routemobile\RouteMobile;
use Tekkenking\Swissecho\Routes\Sms\Gateways\Termii\Termii;

return [


    'live'      => env('SWISSECHO_ENABLED', false),
    'sender'    => env('SWISSECHO_SENDER'),

    'fake'  =>  env('SWISSECHO_FAKE', 'log'),

    'route'     =>  env('SWISSECHO_ROUTE', 'sms'),

    'places'    =>  [
        'nga'   =>  [
            'termii'
        ]
    ],

    'routes_options'  =>  [

        'sms'   =>  [

            'gateway'   => env('SWISSECHO_ROUTE_GATEWAY', 'termii'),

            'gateway_options'  =>  [
                'termii'    =>  [
                    'class' =>  Termii::class,
                    'sender' =>  env('TERMII_SENDER_ID'),
                    'channel'   =>  'dnd',
                    'url'       =>  env('TERMII_URL'),
                    'auth'  =>  [
                        'api_key'   =>  env('TERMII_API_KEY')
                    ]
                ],

                'routemobile'   =>  [
                    'class'     =>  RouteMobile::class,
                    'sender'    =>  env('ROUTEMOBILE_SENDER_ID'),
                    'url'       =>  env('ROUTEMOBILE_URL'),
                    'auth'      =>  [
                        'username'  =>  env('ROUTEMOBILE_USERNAME'),
                        'password'  =>  env('ROUTEMOBILE_PASSWORD')
                    ]
                ]
            ]
        ],

        'slack' => [
            'class' =>  \Tekkenking\Swissecho\Routes\SlackRoute::class,
            'auth'  =>  [
                //'api'   =>
            ]
        ]

    ]

];
