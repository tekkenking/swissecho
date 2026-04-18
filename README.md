# 📨 Swissecho — Laravel Multi-Channel Notification Package

## What is Swissecho?

**Swissecho** is a Laravel package that provides a unified, fluent API for sending messages across **multiple channels** and **multiple gateway providers**. Instead of writing separate integration code for each SMS provider, voice call service, or messaging platform, Swissecho lets you switch between them with a single method call.

### Supported Channels (Routes)

| Channel | Description | Supported Gateways |
|---|---|---|
| **SMS** | Traditional text messages | Termii, RouteMobile, SmsBroadcast (AU), TNZ (NZ), NigerianBulkSMS, Montnets, Wirepick |
| **Voice** | Voice OTP / voice calls | Termii, Textng.xyz |
| **WhatsApp** | WhatsApp messaging | KudiSMS |
| **Slack** | Slack notifications | Built-in Slack route |

### Key Features

- 🔀 **Multi-gateway** — Switch SMS providers per-request or per-country
- 🌍 **Geo-routing (Places)** — Automatically route messages to the correct gateway based on the recipient's country
- 🧪 **Mock mode** — In development, messages are logged to file or sent to email instead of hitting live APIs
- 🔔 **Laravel Notification integration** — Use it as a standard Laravel notification channel
- ⚡ **Direct sending** — Send messages without creating a Notification class
- 📣 **Events** — An `AfterSend` event is dispatched after every message, giving you full insight into requests and responses
- 🪝 **Webhooks** — Built-in webhook handling for provider callbacks (e.g., delivery reports)

---

## Installation

```bash
composer require tekkenking/swissecho
```

**Laravel 5.5+:** The package auto-discovers itself — no manual registration needed.

**Laravel < 5.5:** Add the service provider and facade manually:

```php
'providers' => [
    // ...
    Tekkenking\Swissecho\SwissechoServiceProvider::class,
],

'aliases' => [
    // ...
    'Swissecho' => Tekkenking\Swissecho\SwissechoFacade::class,
],
```

---

## Configuration

### Environment Variables

Add these to your `.env` file. Only configure the gateways you plan to use:

```dotenv
# ── Core Settings ──────────────────────────────────────────
SWISSECHO_ENABLED=false        # Set to true for live/production sending
SWISSECHO_SENDER=MyApp         # Default sender name
SWISSECHO_FAKE=log             # Mock mode: "log" (writes to file) or "mail" (sends email)
SWISSECHO_FAKE_MAIL=admin@example.com  # Email for mock mode when SWISSECHO_FAKE=mail
SWISSECHO_ROUTE=sms            # Default route/channel: sms, voice, whatsapp, slack

# ── Termii (SMS & Voice) ──────────────────────────────────
TERMII_API_KEY=your_api_key
TERMII_SENDER_ID=YourSender
TERMII_URL=https://api.ng.termii.com/api/sms/send

# ── RouteMobile ────────────────────────────────────────────
ROUTEMOBILE_USERNAME=your_username
ROUTEMOBILE_PASSWORD=your_password
ROUTEMOBILE_SENDER_ID=YourSender
ROUTEMOBILE_URL=https://api.routemobile.com/...

# ── SmsBroadcast (Australia) ───────────────────────────────
SMSBRC_DOTCOM_DOT_AU_USERNAME=your_username
SMSBRC_DOTCOM_DOT_AU_PASSWORD=your_password
SMSBRC_DOTCOM_DOT_AU_URL=https://api.smsbroadcast.com.au/...

# ── TNZ (New Zealand) ─────────────────────────────────────
TNZ_API_KEY=your_api_key
TNZ_URL=https://api.tnz.co.nz/...

# ── NigerianBulkSMS ───────────────────────────────────────
NIGERIANBULKSMS_USERNAME=your_username
NIGERIANBULKSM_PASSWORD=your_password
NIGERIANBULKSMS_URL=https://portal.nigeriabulksms.com/api/

# ── Montnets ──────────────────────────────────────────────
MONTNETS_SMS_URL=your_url
MONTNETS_SMS_USERNAME=your_username
MONTNETS_SMS_PASSWORD=your_password

# ── Wirepick ──────────────────────────────────────────────
WIREPICK_SMS_URL=your_url
WIREPICK_SMS_CLIENT=your_client
WIREPICK_SMS_PASSWORD=your_password
WIREPICK_SMS_AFFLIATE=your_affliate

# ── Textng.xyz (Voice) ────────────────────────────────────
TEXTNGXYZ_API_KEY=your_api_key

# ── KudiSMS (WhatsApp) ────────────────────────────────────
KUDISMS_API_KEY=your_api_key
KUDISMS_URL=your_url
```

### The Config File

You can publish and customize the full config at `config/swissecho.php`. The most important sections are:

| Key | Purpose |
|---|---|
| `live` | `true` = send real messages; `false` = mock mode |
| `sender` | Default sender ID/name |
| `fake` | Mock strategy: "log" or "mail" |
| `route` | Default channel: `sms`, `voice`, `whatsapp`, or `slack` |
| `routes_options` | Per-channel gateway definitions and geo-routing rules |

### Geo-Routing with `places`

Each route (SMS, voice, WhatsApp) has a `places` map that **automatically picks the right gateway** based on the recipient's country:

```php
'sms' => [
    'gateway_options' => [ /* ... */ ],
    'places' => [
        'nga' => [                          // Nigeria
            'gateway'   => 'nigerianbulksms',
            'phonecode' => '234'
        ],
        'gha' => [                          // Ghana
            'gateway'   => 'wirepick',
            'phonecode' => '233'
        ],
        'aus' => [                          // Australia
            'gateway'   => 'smsbroadcast',
            'phonecode' => '61'
        ],
        'nzl' => [                          // New Zealand
            'gateway'   => 'tnz',
            'phonecode' => '64'
        ],
    ]
],
```

The phone code is automatically prepended to phone numbers (stripping leading `0` or `+`).

---

## Usage

Swissecho can be used in **two ways**: directly (without a Notification class), or through Laravel's notification system.

### Access Methods

You have three ways to get a Swissecho instance:

```php
// 1. Global helper function
swissecho()

// 2. Laravel Facade
Swissecho::

// 3. From the container
app('swissecho')
```

---

### A) Direct Sending (Without Notification Classes)

#### Quick Send — One Liner

The simplest way to send a message. Uses the **default route** and **default gateway** from config:

```php
swissecho()->quick('2348012345678', 'Your OTP code is 1234');
```

#### Quick Send with a Specific Gateway

```php
swissecho()->gateway('vonage')->quick('2348012345678', 'Your OTP code is 1234');
```

#### Fluent Builder — Full Control

```php
swissecho()->route('sms', function ($ms) {
    return $ms->to('2348012345678, 2348098765432')  // comma-separated recipients
              ->content('Your order has been shipped!')
              ->line('Track it at https://example.com/track');  // appends a new line
              // ->gateway('routemobile')   // override gateway inside callback
              // ->sender('MyBrand')        // override sender inside callback
})
->to('2348011111111')       // optional: additional/fallback recipient
->sender('MyApp')           // optional: override sender
->gateway('termii')         // optional: override gateway
->go();                     // 🚀 sends the message
```

#### Property-Based Sending

You can also set properties directly on the Swissecho instance:

```php
$sw = swissecho();
$sw->gateway('termii');
$sw->to = '2348012345678';
$sw->sender = 'MyApp';
$sw->message = 'The world is a beautiful place created by GOD';
$sw->go();
```

#### Sending via WhatsApp

```php
swissecho()->route('whatsapp', function ($ms) {
    return $ms->to('2348012345678')
              ->content('Hello from WhatsApp!');
})->go();
```

#### Sending via Slack

```php
swissecho()->message('Hello team!')
    ->to('CHANNEL_ID')
    ->route('slack')
    ->go();
```

#### Sending via Voice Call

```php
swissecho()->route('voice', function ($ms) {
    return $ms->to('2348012345678')
              ->content('Your OTP is 5 6 7 8');
})->gateway('termii')->go();
```

---

### B) Laravel Notification Channel Integration

Swissecho integrates with Laravel's built-in notification system. Create a notification class and define a `toSms` (or `toVoice`, `toWhatsapp`, `toSlack`) method:

#### Step 1: Create the Notification

```php
<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;
use Tekkenking\Swissecho\SwissechoMessage;

class OrderShipped extends Notification
{
    /**
     * The delivery channels.
     */
    public function via($notifiable): array
    {
        return ['swissecho'];
    }

    /**
     * (Optional) Tell Swissecho which routes to use.
     * If omitted, defaults to ['sms', 'slack', 'whatsapp'].
     */
    public function swissechoRoutes($notifiable): array
    {
        return ['sms'];
    }

    /**
     * Build the SMS message.
     * Method name follows the pattern: to{Route}  →  toSms, toVoice, toWhatsapp, toSlack
     */
    public function toSms($notifiable): SwissechoMessage
    {
        return (new SwissechoMessage())
            ->line('Hi ' . $notifiable->name . '!')
            ->line('Your order has been shipped.')
            ->sender('MyStore');
    }
}
```

#### Step 2: Make Your User Model "Notifiable"

Swissecho pulls the phone number from the notifiable model. Implement one of these:

```php
class User extends Authenticatable
{
    use Notifiable;

    /**
     * Option A: Have a `phone` attribute on the model (e.g. column in DB).
     * Swissecho checks $notifiable->phone automatically.
     */

    /**
     * Option B: Define this method for custom logic.
     */
    public function routeNotificationPhone(): string
    {
        return $this->mobile_number;
    }

    /**
     * (Optional) Tell Swissecho the recipient's country for geo-routing.
     * Return a 3-letter ISO code matching a key in config places.
     */
    public function routeNotificationPlace(): string
    {
        return 'nga'; // Nigeria
    }
}
```

#### Step 3: Send the Notification

```php
$user->notify(new OrderShipped());
```

---

## SwissechoMessage API Reference

The `SwissechoMessage` class is the message builder used in callbacks and notification methods:

| Method | Description | Example |
|---|---|---|
| `->line($text)` | Appends a line of text to the message body. Multiple calls add new lines. | `->line('Hello')` |
| `->content($text)` | Alias for `line()`. | `->content('Hello')` |
| `->to($recipient)` | Sets the recipient(s). Accepts a string or comma-separated list. | `->to('234801..., 234809...')` |
| `->sender($name)` | Sets the sender ID (max 10 characters). | `->sender('MyApp')` |
| `->from($name)` | Alias for `sender()`. | `->from('MyApp')` |
| `->gateway($name)` | Overrides the gateway for this message. | `->gateway('termii')` |
| `->place($code)` | Sets the country/place code (e.g., 'nga'). Overrides auto-detection. | `->place('nga')` |
| `->phonecode($code)` | Manually sets the phone country code (e.g., '234'). | `->phonecode('234')` |
| `->identifier($id)` | Attaches an identifier (e.g., user ID) to the message for tracking. | `->identifier($user->id)` |
| `->route($name)` | Sets the route/channel on the message itself. | `->route('sms')` |

---

## Mock Mode (Development & Testing)

When `SWISSECHO_ENABLED=false` (the default), **no real API calls are made**. Instead, messages are captured by the mock system.

### Mock via Log (Default)

Messages are written to `storage/logs/swissecho_mock.log`:

```dotenv
SWISSECHO_FAKE=log
```

The log includes: sender, recipient, message body, route, gateway, gateway class, country, and phone code.

### Mock via Email

Messages are emailed to the configured address:

```dotenv
SWISSECHO_FAKE=mail
SWISSECHO_FAKE_MAIL=developer@example.com
```

---

## Events

After every message send (including mock sends), Swissecho dispatches the **`AfterSend`** event:

```
Tekkenking\Swissecho\Events\AfterSend
```

### Event Properties

| Property | Type | Description |
|---|---|---|
| `$insightPayload` | `array` | Contains `request` (the payload sent to the gateway) and `response` (raw gateway response) |
| `$formattedResponse` | `array` | Structured response with `status`, `partner_response`, `from`, `to`, `body`, `route`, `gateway`, `identifier`, `timestamp` |
| `$identifier` | `mixed` | The identifier attached to the message (e.g., user ID) |

### Listening to the Event

```php
// app/Providers/EventServiceProvider.php
protected $listen = [
    \Tekkenking\Swissecho\Events\AfterSend::class => [
        \App\Listeners\LogSmsDelivery::class,
    ],
];
```

```php
<?php
// app/Listeners/LogSmsDelivery.php

namespace App\Listeners;

use Tekkenking\Swissecho\Events\AfterSend;

class LogSmsDelivery
{
    public function handle(AfterSend $event): void
    {
        // $event->formattedResponse['status']   — true/false
        // $event->formattedResponse['to']       — recipient array
        // $event->formattedResponse['gateway']  — which gateway was used
        // $event->insightPayload['request']     — raw request payload
        // $event->insightPayload['response']    — raw response from provider

        logger()->info('SMS sent', $event->formattedResponse);
    }
}
```

---

## Webhooks

Swissecho includes a built-in webhook handler for receiving delivery reports or callbacks from gateway providers. The webhook system validates a secret key and routes the request to the appropriate gateway class.

Configure webhooks per gateway in `config/swissecho.php`:

```php
'{$gateway}' => [
    // ...
    'webhook' => [
        'secret' => env('TERMI_WEBHOOK_SECRET', 'your-secret-here'),
        'handle' => 'webhook'   // method name on the gateway class
    ]
],
```

---

## Helper Functions

Swissecho provides global helper functions for phone number manipulation:

| Function | Description |
|---|---|
| `swissecho()` | Returns the Swissecho singleton instance |
| `addCountryCodeToPhoneNumber($phone, $code)` | Prepends a country code (e.g., '234') to a phone number, stripping leading `0` or `+` |
| `removeCountryCodeFromPhoneNumber($phone, $code)` | Strips a country code prefix from a phone number |
| `convertPhoneNumberToArray($phone)` | Splits a comma-separated phone string into an array |

### Examples

```php
addCountryCodeToPhoneNumber('08012345678', '234');
// Returns: "2348012345678"

removeCountryCodeFromPhoneNumber('2348012345678', '234');
// Returns: "8012345678"

convertPhoneNumberToArray('2348012345678, 2348098765432');
// Returns: ["2348012345678", "2348098765432"]
```

---

## Requirements

- **PHP** ≥ 8.1
- **Laravel** 5.5+ (auto-discovery) or any version with manual provider registration

## License

This package is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).