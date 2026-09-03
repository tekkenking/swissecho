<?php

use Tekkenking\Swissecho\Routes\Slack\SlackRoute;
use Tekkenking\Swissecho\Routes\Sms\Gateways\Montnets\Montnets;
use Tekkenking\Swissecho\Routes\Sms\Gateways\Nigerianbulksms\Nigerianbulksms;
use Tekkenking\Swissecho\Routes\Sms\Gateways\Routemobile\RouteMobile;
use Tekkenking\Swissecho\Routes\Sms\Gateways\Smsbroadcast\SmsBroadCastDotComDotAu;
use Tekkenking\Swissecho\Routes\Sms\Gateways\Termii\Termii;
use Tekkenking\Swissecho\Routes\Sms\Gateways\Tnz\Tnz;
use Tekkenking\Swissecho\Routes\Sms\Gateways\Wirepick\Wirepick;
use Tekkenking\Swissecho\Routes\Telegram\TelegramRoute;
use Tekkenking\Swissecho\Routes\Voice\Gateways\Termii\TermiiVoiceCall;
use Tekkenking\Swissecho\Routes\Voice\Gateways\Textngxyz\TextngxyzVoiceCall;
use Tekkenking\Swissecho\Routes\Whatsapp\Gateways\Kudisms\KudismsWhatsapp;

return [

    'live'      =>  env('SWISSECHO_ENABLED', false),
    'sender'    =>  env('SWISSECHO_SENDER'),
    'fake'      =>  env('SWISSECHO_FAKE', 'log'), //mail
    'fake_mail'      =>  env('SWISSECHO_FAKE_MAIL', 'admin@example.com'),
    'route'     =>  env('SWISSECHO_ROUTE', 'sms'),

    'routes_options'    =>  [
        'sms'           =>  [
            'default_place'    =>  env('SWISSECHO_DEFAULT_PLACE', null),
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
                        'secret'    => env('TERMI_WEBHOOK_SECRET'),
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
                'montnets'        =>  [
                    'class'     =>  Montnets::class,
                    'url'       =>  env('MONTNETS_SMS_URL'),
                    'auth'      =>  [
                        'username'  =>  env('MONTNETS_SMS_USERNAME'),
                        'password'  =>  env('MONTNETS_SMS_PASSWORD'),
                    ]
                ],
                'wirepick' => [
                    'class' => Wirepick::class,
                    'url' => env('WIREPICK_SMS_URL'),
                    'client' => env('WIREPICK_SMS_CLIENT'),
                    'password' => env('WIREPICK_SMS_PASSWORD'),
                    'affliate' => env('WIREPICK_SMS_AFFLIATE'),
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
                'gha'   =>  [
                    'gateway'   => 'wirepick',
                    'phonecode' =>  '233'
                ],
                'ken'   =>  [
                    'gateway'  => 'wirepick',
                    'phonecode' => '254'
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
            'class'           => SlackRoute::class,
            'auth'            => [
                'webhook' => env('SLACK_WEBHOOK_URL'),
                'token'   => env('SLACK_BOT_TOKEN'),
            ],
            'default_channel' => env('SLACK_DEFAULT_CHANNEL'),
            'url'             => env('SLACK_API_URL', 'https://slack.com/api/chat.postMessage'),
        ],

        'telegram' => [
            'class'           => TelegramRoute::class,
            'auth'            => [
                'token' => env('TELEGRAM_BOT_TOKEN'),
            ],
            'default_chat_id' => env('TELEGRAM_DEFAULT_CHAT_ID'),
            'parse_mode'      => env('TELEGRAM_PARSE_MODE'), // HTML, MarkdownV2, etc.
            'url'             => env('TELEGRAM_API_URL'),
        ],

        'voice'  =>  [
            'default_place'     =>  env('SWISSECHO_DEFAULT_PLACE', null),
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

        'whatsapp'  =>  [

            'gateway'        => env('SWISSECHO_ROUTE_GATEWAY', 'kudisms'),
            'default_place'  => env('SWISSECHO_DEFAULT_PLACE', null),

            'gateway_options'  =>  [
                'kudisms'    =>  [
                    'class' =>  KudismsWhatsapp::class,
                    //'sender' =>  env('KUDISMS_WHATSAPP_SENDER_ID'),
                    'url'    =>  env('KUDISMS_URL'),
                    'auth'  =>  [
                        'api_key'   =>  env('KUDISMS_API_KEY')
                    ]
                ]
            ],

            'places'    =>  [
                'nga'   =>  [
                    'phonecode' =>  '+234',
                    'gateway'   =>  'kudisms'
                ]
            ]

        ],

    ]

];
