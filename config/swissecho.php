<?php

use Tekkenking\Swissecho\Routes\Sms\Gateways\Montnets\Montnets;
use Tekkenking\Swissecho\Routes\Sms\Gateways\Nigerianbulksms\Nigerianbulksms;
use Tekkenking\Swissecho\Routes\Sms\Gateways\Routemobile\RouteMobile;
use Tekkenking\Swissecho\Routes\Sms\Gateways\Smsbroadcast\SmsBroadCastDotComDotAu;
use Tekkenking\Swissecho\Routes\Sms\Gateways\Termii\Termii;
use Tekkenking\Swissecho\Routes\Sms\Gateways\Tnz\Tnz;
use Tekkenking\Swissecho\Routes\Sms\Gateways\Wirepick\Wirepick;
use Tekkenking\Swissecho\Routes\Voice\Gateways\Termii\TermiiVoiceCall;
use Tekkenking\Swissecho\Routes\Voice\Gateways\Textngxyz\TextngxyzVoiceCall;
use Tekkenking\Swissecho\Routes\Whatsapp\Gateways\DirectWhatsapp\DirectWhatsapp;
use Tekkenking\Swissecho\Routes\Whatsapp\Gateways\Kudisms\KudismsWhatsapp;

return [

    'live'      =>  env('SWISSECHO_ENABLED', false),
    'sender'    =>  env('SWISSECHO_SENDER'),
    //Mask gateway credentials in the AfterSend event payload. Turn this off
    //only if you need the raw outbound request for debugging, and make sure
    //nothing logs or queues the event while it is off.
    'redact_secrets' => env('SWISSECHO_REDACT_SECRETS', true),

    'fake'      =>  env('SWISSECHO_FAKE', 'log'), //mail
    'fake_mail'      =>  env('SWISSECHO_FAKE_MAIL'),
    'route'     =>  env('SWISSECHO_ROUTE', 'sms'),

    //Applied to every gateway request. verify_tls should stay true: these
    //requests carry API keys, and some gateways carry an account password.
    //Turn it off only for a provider with a genuinely broken certificate,
    //and only while you chase them about it.
    'transport' => [
        'verify_tls'      => env('SWISSECHO_VERIFY_TLS', true),
        'timeout'         => env('SWISSECHO_TIMEOUT', 30),
        'connect_timeout' => env('SWISSECHO_CONNECT_TIMEOUT', 10),
    ],

    'routes_options'    =>  [
        'sms'           =>  [
            //The gateway used when the resolved place names none of its own.
            'gateway'          =>  env('SWISSECHO_SMS_GATEWAY', 'termii'),
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
                        'secret'    => env('TERMII_WEBHOOK_SECRET', env('TERMI_WEBHOOK_SECRET')),
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
                        'password'  =>  env('NIGERIANBULKSMS_PASSWORD', env('NIGERIANBULKSM_PASSWORD')),
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
                    'affiliate' => env('WIREPICK_SMS_AFFILIATE', env('WIREPICK_SMS_AFFLIATE')),
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
            'auth'            => [
                'webhook' => env('SLACK_WEBHOOK_URL'),
                'token'   => env('SLACK_BOT_TOKEN'),
            ],
            'default_channel' => env('SLACK_DEFAULT_CHANNEL'),
            'url'             => env('SLACK_API_URL', 'https://slack.com/api/chat.postMessage'),
        ],

        'telegram' => [
            'auth'            => [
                'token' => env('TELEGRAM_BOT_TOKEN'),
            ],
            'default_chat_id' => env('TELEGRAM_DEFAULT_CHAT_ID'),
            'parse_mode'      => env('TELEGRAM_PARSE_MODE'), // HTML, MarkdownV2, etc.
            'url'             => env('TELEGRAM_API_URL'),
        ],

        'voice'  =>  [
            'gateway'           =>  env('SWISSECHO_VOICE_GATEWAY', 'termii'),
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

            //SWISSECHO_ROUTE_GATEWAY is kept as a fallback: it only ever
            //appeared in this block, so it is the one route it could have
            //been meant for.
            'gateway'        => env('SWISSECHO_WHATSAPP_GATEWAY', env('SWISSECHO_ROUTE_GATEWAY', 'kudisms')),
            'default_place'  => env('SWISSECHO_DEFAULT_PLACE', null),

            'gateway_options'  =>  [
                //WhatsApp Cloud API - Meta's own endpoint, no reseller.
                //Create the app and the number at developers.facebook.com,
                //then set the phone number id and a system user access token.
                'directwhatsapp' => [
                    'class'           =>  DirectWhatsapp::class,

                    //Leave 'url' empty to build the documented endpoint,
                    //https://graph.facebook.com/{version}/{phone-number-id}/messages
                    //Set it only to pin a proxy or a different host.
                    'url'             =>  env('WHATSAPP_CLOUD_URL'),
                    'api_host'        =>  env('WHATSAPP_CLOUD_HOST', 'https://graph.facebook.com'),
                    'api_version'     =>  env('WHATSAPP_CLOUD_API_VERSION', 'v21.0'),
                    'phone_number_id' =>  env('WHATSAPP_PHONE_NUMBER_ID'),

                    //Whether WhatsApp renders a preview card for a link in
                    //the message body.
                    'preview_url'     =>  env('WHATSAPP_PREVIEW_URL', false),

                    'auth'  =>  [
                        'access_token'  =>  env('WHATSAPP_ACCESS_TOKEN')
                    ],

                    //Free-form text only reaches a user inside the 24 hour
                    //customer service window. Outside it WhatsApp requires
                    //an approved template: name one here and every send uses
                    //it, with the message body as its single body parameter.
                    'template'  =>  [
                        'name'      =>  env('WHATSAPP_TEMPLATE_NAME'),
                        'language'  =>  env('WHATSAPP_TEMPLATE_LANGUAGE', 'en_US')
                    ]
                ],

                'kudisms'    =>  [
                    'class' =>  KudismsWhatsapp::class,
                    //'sender' =>  env('KUDISMS_WHATSAPP_SENDER_ID'),
                    'url'    =>  env('KUDISMS_URL'),

                    //The template code registered on your Kudisms account.
                    //There is no sensible default: Kudisms rejects a send
                    //whose template it does not recognise.
                    'template_code' =>  env('KUDISMS_TEMPLATE_CODE'),
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
