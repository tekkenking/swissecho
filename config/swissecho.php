<?php

use Tekkenking\Swissecho\Routes\Slack\SlackRoute;
use Tekkenking\Swissecho\Routes\Sms\Gateways\Nigerianbulksms\Nigerianbulksms;
use Tekkenking\Swissecho\Routes\Sms\Gateways\Routemobile\RouteMobile;
use Tekkenking\Swissecho\Routes\Sms\Gateways\Smsbroadcast\SmsBroadCastDotComDotAu;
use Tekkenking\Swissecho\Routes\Sms\Gateways\Termii\Termii;
use Tekkenking\Swissecho\Routes\Sms\Gateways\Tnz\Tnz;
use Tekkenking\Swissecho\Routes\Voice\Gateways\Termii\TermiiVoiceCall;
use Tekkenking\Swissecho\Routes\Voice\Gateways\Textngxyz\TextngxyzVoiceCall;

return [

    'live'      =>  env('SWISSECHO_ENABLED', false),
    'sender'    =>  env('SWISSECHO_SENDER'),
    'fake'      =>  env('SWISSECHO_FAKE', 'log'), //mail
    'fake_mail'      =>  env('SWISSECHO_FAKE_MAIL', 'admin@example.com'),
    'route'     =>  env('SWISSECHO_ROUTE', 'sms'),

    'routes_options'    =>  [
        'sms'           =>  [
            'gateway_options'  =>  [
                'termii'        =>  [
                    'class'     =>  Termii::class,
                    'sender'    =>  env('TERMII_SENDER_ID'),
                    'channel'   =>  'dnd',
                    'url'       =>  env('TERMII_URL'),
                    'auth'      =>  [
                        'api_key'   =>  env('TERMII_API_KEY')
                    ],
                    'webhook'   => [
                        'secret'    => env('TERMI_WEBHOOK_SECRET', '89h98y2vn8y283929878'),
                        'handle'    => 'webhook'
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
                ],
                'smsbroadcast'  =>  [
                    'class' =>  SmsBroadCastDotComDotAu::class,
                    'auth'  =>  [
                        'username'  =>  env('SMSBRC_DOTCOM_DOT_AU_USERNAME'),
                        'password'  =>  env('SMSBRC_DOTCOM_DOT_AU_PASSWORD'),
                    ],
                    'url'   =>  env('SMSBRC_DOTCOM_DOT_AU_URL')
                ],
                'tnz'  =>  [
                    'class' =>  Tnz::class,
                    'auth'  =>  [
                        'api_key'   =>  env('TNZ_API_KEY')
                    ],
                    'url'   =>  env('TNZ_URL')
                ],
                'nigerianbulksms'   =>  [
                    'class' =>  Nigerianbulksms::class,
                    'auth'  =>  [
                        'username'  =>  env('NIGERIANBULKSMS_USERNAME'),
                        'password'  =>  env('NIGERIANBULKSM_PASSWORD'),
                    ],
                    'url'   =>  env('NIGERIANBULKSMS_URL', 'https://portal.nigeriabulksms.com/api/'),
                ],
                /*'vonage'    =>  [
                    'class' =>  \App\Libs\Vonage\Sms\Send::class,
                    'auth'  =>  [
                        'api_key'   =>  env('VONAGE_API_KEY'),
                        'api_token'   =>  env('VONAGE_API_TOKEN'),
                    ]
                ]*/
            ],
            'places'  =>  [
                'nga'   =>  [
                    'gateway'   => 'nigerianbulksms',
                    'phonecode' =>  '234'
                ],
                'aus'   =>  [
                    'gateway'   => 'smsbroadcast',
                    'phonecode' =>  '61'
                ],
                'nzl'   =>  [
                    'gateway'   => 'tnz',
                    'phonecode' =>  '64'
                ]
            ]
        ],

        'slack' => [
            'class' =>  SlackRoute::class,
            'auth'  =>  [
                //'api'   =>
            ]
        ],

        'voice'  =>  [
            'gateway_options'   =>  [

                'termii'        =>  [
                    'class'     =>  TermiiVoiceCall::class,
                    'auth'      =>  [
                        'api_key'   =>  env('TERMII_API_KEY')
                    ],
                    'url'       =>  'https://api.ng.termii.com/api/sms/otp/call'
                ],
                'textngxyz'     =>  [
                    'class'     =>  TextngxyzVoiceCall::class,
                    'auth'      =>  [
                        'api_key'   =>  env('TEXTNGXYZ_API_KEY')
                    ],
                    'url'       =>  'https://api.textng.xyz/voice-otp/',
                    'repeat_times'  =>  2
                ]
            ],
            'places'  =>  [
                'nga'   =>  [
                    'gateway'   => 'termii',
                    'phonecode' =>  '234'
                ]
            ]
        ],

    ]

];
