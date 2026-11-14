# 📨 Swissecho — Laravel Multi-Channel Notification Package

[![Latest Version on Packagist](https://img.shields.io/packagist/v/tekkenking/swissecho.svg?style=flat-square)](https://packagist.org/packages/tekkenking/swissecho)
[![Total Downloads](https://img.shields.io/packagist/dt/tekkenking/swissecho.svg?style=flat-square)](https://packagist.org/packages/tekkenking/swissecho)
[![PHP Version](https://img.shields.io/packagist/php-v/tekkenking/swissecho.svg?style=flat-square)](https://packagist.org/packages/tekkenking/swissecho)
[![License](https://img.shields.io/packagist/l/tekkenking/swissecho.svg?style=flat-square)](LICENSE)

## What is Swissecho?

**Swissecho** is a Laravel package that provides a unified, fluent API for sending messages across **multiple channels** and **multiple gateway providers**. Instead of writing separate integration code for each provider, you configure them all in one place and switch between them with a single method call.

```dotenv
# Required once: every send needs a place, which chooses the gateway
# and the dialling code. See "Geo-Routing" below.
SWISSECHO_DEFAULT_PLACE=nga

# The sender ID recipients see. Some gateways refuse to send without one.
SWISSECHO_SENDER=MyApp
```

```php
// One line — send an SMS through your default gateway
$result = swissecho()->quick('2348012345678', 'Your OTP code is 1234');

if ($result->success) {
    // $result->messageId is the provider's own ID for the message
} else {
    // $result->errorCode and $result->errorMessage say why
}

// Or take full control of channel, recipients, gateway and sender
swissecho()->route('sms', function ($ms) {
    return $ms->to('2348012345678')
              ->content('Your order has been shipped!');
})->gateway('termii')->sender('MyApp')->go();
```

`$result` has the same shape whichever provider handled the send — see [The Result](#the-result).

> Without a place configured, every send above raises a `SwissechoException`
> telling you the three ways to set one. Set `SWISSECHO_DEFAULT_PLACE`, or add
> `->place('nga')` to each send.

Switch `'sms'` to `'whatsapp'`, `'voice'`, `'slack'` or `'telegram'` and the rest of your code stays the same. In development, leave `SWISSECHO_ENABLED=false` and nothing reaches a live API — messages go to your log file or an inbox instead.

### Supported Channels (Routes)

| Channel | Description | Supported Gateways |
|---|---|---|
| **SMS** | Traditional text messages | Termii, RouteMobile, SmsBroadcast (AU), TNZ (NZ), NigerianBulkSMS, Montnets, Wirepick, Twilio, Vonage |
| **Voice** | Voice OTP / voice calls | Termii, Textng.xyz |
| **WhatsApp** | WhatsApp messaging | WhatsApp Cloud API (direct), KudiSMS |
| **Slack** | Slack notifications | Incoming Webhook, Bot Token (`chat.postMessage`) |
| **Telegram** | Telegram Bot messages | Telegram Bot API (`sendMessage`) |

### Key Features

- 🔀 **Multi-gateway** — Switch SMS providers per-request or per-country
- 🌍 **Geo-routing (Places)** — Automatically route messages to the correct gateway based on the recipient's country
- 🧪 **Mock mode** — In development, messages are logged to file or sent to email instead of hitting live APIs
- 🔔 **Laravel Notification integration** — Use it as a standard Laravel notification channel
- ⚡ **Direct sending** — Send messages without creating a Notification class
- ✅ **One result shape** — `go()` returns success, the provider's message ID, and the error code and reason, read from each provider's own reply format
- 📣 **Events** — An `AfterSend` event is dispatched after every message, giving you full insight into requests and responses
- 🪝 **Webhooks** — Built-in webhook handling for provider callbacks (e.g., delivery reports)

---

## Contents

- [What is Swissecho?](#what-is-swissecho)
- [Requirements](#requirements)
- [Installation](#installation)
- [Configuration](#configuration)
  - [The Default Gateway for a Route](#the-default-gateway-for-a-route)
- [Recipients and Sender IDs](#recipients-and-sender-ids)
- [Twilio and Vonage](#twilio-and-vonage)
- [WhatsApp Gateways](#whatsapp-gateways)
- [Transport, TLS and Timeouts](#transport-tls-and-timeouts)
- [Usage](#usage)
- [The Result](#the-result)
- [API Reference](#api-reference)
- [Mock Mode (Development & Testing)](#mock-mode-development--testing)
- [Events](#events)
- [Webhooks](#webhooks)
- [Helper Functions](#helper-functions)
- [Adding a Custom SMS Gateway](#-adding-a-custom-sms-gateway)
- [License](#license)

---

## Requirements

| Dependency | Supported Versions |
|---|---|
| **PHP** | ^8.2 |
| **Laravel** | 11.x, 12.x, 13.x |
| **PHP extension** | `ext-curl` |

---

## Installation

```bash
composer require tekkenking/swissecho
```

The package auto-discovers itself via Laravel's package auto-discovery — no manual registration needed.

---

## Configuration

### Environment Variables

Add these to your `.env` file. Only configure the gateways you plan to use:

```dotenv
# ── Core Settings ──────────────────────────────────────────
SWISSECHO_ENABLED=false        # Set to true for live/production sending
SWISSECHO_SENDER=MyApp         # Default sender ID, used when the message and the gateway set none
SWISSECHO_FAKE=log             # Mock mode: "log" (writes to file) or "mail" (sends email)
SWISSECHO_FAKE_MAIL=           # Required when SWISSECHO_FAKE=mail. An address you own.
SWISSECHO_ROUTE=sms            # Default route/channel: sms, voice, whatsapp, slack, telegram
SWISSECHO_SMS_GATEWAY=termii        # Default gateway for the sms route
SWISSECHO_VOICE_GATEWAY=termii      # Default gateway for the voice route
SWISSECHO_WHATSAPP_GATEWAY=kudisms  # Default gateway for the whatsapp route
SWISSECHO_DEFAULT_PLACE=nga    # Required unless every send sets ->place() or the notifiable has routeNotificationPlace()

# ── Transport ──────────────────────────────────────────────
SWISSECHO_VERIFY_TLS=true      # Verify the provider's TLS certificate. Keep this on.
SWISSECHO_TIMEOUT=30           # Seconds to wait for a response. 0 means wait forever.
SWISSECHO_CONNECT_TIMEOUT=10   # Seconds to wait for the connection itself
SWISSECHO_REDACT_SECRETS=true  # Mask credentials in the AfterSend event payload

# ── Termii (SMS & Voice) ──────────────────────────────────
TERMII_API_KEY=your_api_key
TERMII_SENDER_ID=YourSender
TERMII_URL=https://api.ng.termii.com/api/sms/send
TERMII_WEBHOOK_SECRET=         # Optional, for delivery-report callbacks

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
NIGERIANBULKSMS_PASSWORD=your_password
NIGERIANBULKSMS_URL=https://portal.nigeriabulksms.com/api/

# ── Montnets ──────────────────────────────────────────────
MONTNETS_SMS_URL=your_url
MONTNETS_SMS_USERNAME=your_username
MONTNETS_SMS_PASSWORD=your_password

# ── Wirepick ──────────────────────────────────────────────
WIREPICK_SMS_URL=your_url
WIREPICK_SMS_CLIENT=your_client
WIREPICK_SMS_PASSWORD=your_password
WIREPICK_SMS_AFFILIATE=your_affiliate

# ── Twilio ────────────────────────────────────────────────
TWILIO_ACCOUNT_SID=ACxxxxxxxx
TWILIO_AUTH_TOKEN=your_auth_token
TWILIO_FROM=+15005550006            # This gateway's default sender: a Twilio number
TWILIO_MESSAGING_SERVICE_SID=       # Optional. Used in place of TWILIO_FROM. A sender on the message still wins.
TWILIO_URL=                         # Optional. Full endpoint override, e.g. a proxy.

# ── Vonage ────────────────────────────────────────────────
VONAGE_API_KEY=your_api_key
VONAGE_API_SECRET=your_api_secret
VONAGE_FROM=MyApp
VONAGE_URL=                         # Optional. Defaults to https://rest.nexmo.com/sms/json

# ── Textng.xyz (Voice) ────────────────────────────────────
TEXTNGXYZ_API_KEY=your_api_key

# ── KudiSMS (WhatsApp) ────────────────────────────────────
KUDISMS_API_KEY=your_api_key
KUDISMS_URL=your_url
KUDISMS_TEMPLATE_CODE=         # Required. The template registered on your Kudisms account.

# ── WhatsApp Cloud API (direct, no reseller) ──────────────
WHATSAPP_PHONE_NUMBER_ID=      # From your WhatsApp app at developers.facebook.com
WHATSAPP_ACCESS_TOKEN=         # A system user access token
WHATSAPP_CLOUD_API_VERSION=v21.0   # Optional. Graph API version.
WHATSAPP_CLOUD_HOST=           # Optional. Defaults to https://graph.facebook.com
WHATSAPP_CLOUD_URL=            # Optional. Full endpoint override, e.g. a proxy.
WHATSAPP_PREVIEW_URL=false     # Render a preview card for a link in the body
WHATSAPP_TEMPLATE_NAME=        # Optional. See "WhatsApp gateways" below.
WHATSAPP_TEMPLATE_LANGUAGE=en_US

# ── Slack ─────────────────────────────────────────────────
# Option A — Incoming Webhook (no channel needed in code)
SLACK_WEBHOOK_URL=https://hooks.slack.com/services/T.../B.../...
# Option B — Bot Token (requires a channel ID or name)
SLACK_BOT_TOKEN=xoxb-your-bot-token
SLACK_DEFAULT_CHANNEL=#general
# SLACK_API_URL defaults to https://slack.com/api/chat.postMessage

# ── Telegram ──────────────────────────────────────────────
TELEGRAM_BOT_TOKEN=123456:ABC-your-telegram-bot-token
TELEGRAM_DEFAULT_CHAT_ID=123456789
# TELEGRAM_PARSE_MODE=HTML        # Optional: HTML or MarkdownV2
# TELEGRAM_API_URL=               # Optional: override the API URL
```

> **Renamed in this release.** `NIGERIANBULKSMS_PASSWORD`, `WIREPICK_SMS_AFFILIATE` and `TERMII_WEBHOOK_SECRET` were previously misspelled as `NIGERIANBULKSM_PASSWORD`, `WIREPICK_SMS_AFFLIATE` and `TERMI_WEBHOOK_SECRET`. The old names still work, so existing `.env` files keep running, but prefer the corrected ones.

### The Config File

Publish the config file to `config/swissecho.php` in your application so you can customize it:

```bash
php artisan vendor:publish --tag=swissecho-config
```

The most important sections are:

| Key | Purpose |
|---|---|
| `live` | `true` = send real messages; `false` = mock mode |
| `sender` | Default sender ID/name |
| `fake` | Mock strategy: "log" or "mail" |
| `route` | Default channel: `sms`, `voice`, `whatsapp`, `slack`, or `telegram` |
| `routes_options` | Per-channel gateway definitions and geo-routing rules |

### The Default Gateway for a Route

Each route with more than one gateway has a default, set per route:

```dotenv
SWISSECHO_SMS_GATEWAY=termii
SWISSECHO_VOICE_GATEWAY=termii
SWISSECHO_WHATSAPP_GATEWAY=kudisms
```

That default is used when the resolved `place` names no gateway of its own. **A place's gateway always wins**, which is the whole point of geo-routing — see the next section. And naming a gateway on the send wins over both:

```php
swissecho()->route('sms', fn ($ms) => $ms->to('...')->content('hi'))
    ->gateway('routemobile')   // beats the place and the route default
    ->go();
```

So the order of precedence is: the gateway named on the send, then the place's gateway, then the route's default, then the first gateway listed in that route's `gateway_options`.

Naming a gateway the route has no config for raises a `SwissechoException` listing the ones it does have.

> **Changed in 1.1.0.** These three variables are new. `SWISSECHO_ROUTE_GATEWAY` still works as a fallback for the whatsapp route, which is the only block it ever appeared in, but it never actually did anything before 1.1.0 — the route's `gateway` key was never read, so the default was whichever gateway happened to be listed first.

---

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

#### Setting a Default Place

If a notifiable doesn't set a place via `->place()` or `routeNotificationPlace()`, Swissecho needs a default place to fall back to. Configure one of the following:

```dotenv
SWISSECHO_DEFAULT_PLACE=gha  # Global default for all routes
```

Or, if you've published the config, set it per-route:

```php
'sms' => [
    'default_place' => 'aus',  // SMS will default to Australia
    'gateway_options' => [ /* ... */ ],
    'places' => [ /* ... */ ],
],
```

If no default is configured anywhere and the notifiable doesn't have `routeNotificationPlace()`, Swissecho throws a `SwissechoException` listing the available places and how to set a default.

---

## Recipients and Sender IDs

### Phone numbers

Recipients are put into international form for you, using the dialling code of the send's place. With place `nga`, all of these reach the gateway as `2348012345678`:

```php
->to('08012345678')
->to('2348012345678')
->to('+234 801 234 5678')
->to('(0801) 234-5678')
```

Several recipients go in one string, separated by commas, or as an array.

**Write a number from another country with a `+`.** `->to('+447911123456')` is sent as given whatever the place is. Without the `+` it cannot be told apart from a local number, so the place's code is added in front of it.

Formatting is done by libphonenumber where it recognises the number. It is never a gate: a number it does not recognise is still formatted the simple way and sent, and the provider decides.

### Sender IDs

The sender is taken from the first of these that is set:

1. `->sender()` on the message
2. the notifiable's `routeNotificationSmsSender()`, `routeNotificationVoiceSender()` or `routeNotificationWhatsappSender()`
3. the `sender` key on the gateway in `config/swissecho.php`
4. `SWISSECHO_SENDER`

A notifiable method that returns nothing is skipped, so the next in the list applies.

That order is what lets one application send on behalf of many businesses. The platform's own name is the default in `SWISSECHO_SENDER`, and each business's name comes from the notifiable, or is set on the message:

```php
class Customer extends Model
{
    use Notifiable;

    public function routeNotificationSmsSender(): ?string
    {
        return $this->business->sms_sender;   // "PartyShop", not the platform's name
    }

    public function routeNotificationPlace(): string
    {
        return $this->country;                // "nga", "usa", "gbr" - picks the gateway too
    }
}
```

With `nga` mapped to one gateway and `usa` to another under `places`, the same `$customer->notify(...)` goes out through the right provider for each customer's country, carrying the right business's name.

The sender is sent exactly as given. Two optional keys on any gateway control what is allowed:

```php
'termii' => [
    // ...
    'requires_sender'   => true,  // stop with a clear error if no sender is set anywhere
    'sender_max_length' => 11,    // refuse a longer sender instead of letting the provider reject it
],
```

`requires_sender` is on by default for `termii`, `routemobile` and `smsbroadcast`, whose providers reject a message without one. `sender_max_length` is off unless you set it; GSM allows 11 characters for an alphanumeric sender.

> **Changed in 1.3.0.** Senders used to be cut to 10 characters silently. They are now sent whole, so a sender longer than 10 characters that was being shortened will go out in full.

---

## Twilio and Vonage

Both are gateways on the `sms` route. Name one on a send, make it the route's default with `SWISSECHO_SMS_GATEWAY`, or map a place to it:

```php
// config/swissecho.php, under routes_options.sms.places
'usa' => ['gateway' => 'twilio', 'phonecode' => '1'],
'gbr' => ['gateway' => 'vonage', 'phonecode' => '44'],
```

Both make **one request per recipient**, so a send to three numbers produces three requests and three `AfterSend` events.

### `twilio`

```dotenv
TWILIO_ACCOUNT_SID=ACxxxxxxxx
TWILIO_AUTH_TOKEN=your_auth_token
TWILIO_FROM=+15005550006
```

```php
$result = swissecho()->route('sms', function ($ms) {
    return $ms->to('+14155552671')->content('Your code is 1234');
})->gateway('twilio')->go();

$result->messageId;   // Twilio's SID, e.g. SMxxxxxxxx
```

`TWILIO_FROM` is this gateway's default sender, and it is resolved like every other gateway's: a sender set on the message or supplied by the notifiable wins over it, and it wins over `SWISSECHO_SENDER`. If you set `TWILIO_MESSAGING_SERVICE_SID`, the Messaging Service is used in place of the default — but a sender chosen for the message still wins.

### `vonage`

```dotenv
VONAGE_API_KEY=your_api_key
VONAGE_API_SECRET=your_api_secret
VONAGE_FROM=MyApp
```

```php
$result = swissecho()->route('sms', function ($ms) {
    return $ms->to('+447911123456')->content('Your code is 1234');
})->gateway('vonage')->go();
```

`VONAGE_FROM` is this gateway's default sender; a sender set on the message or supplied by the notifiable wins over it.

Vonage answers HTTP 200 whether or not it accepted the message, so `$result->success` is read from the status inside its reply, and `$result->errorCode` is Vonage's own status code.

---

## WhatsApp Gateways

Two gateways serve the `whatsapp` route, and **they need completely different credentials**:

| Gateway | Needs | Default? |
|---|---|---|
| `kudisms` | `KUDISMS_API_KEY`, `KUDISMS_URL`, `KUDISMS_TEMPLATE_CODE` | yes |
| `directwhatsapp` | `WHATSAPP_PHONE_NUMBER_ID`, `WHATSAPP_ACCESS_TOKEN` | no |

`KUDISMS_TEMPLATE_CODE` applies to `kudisms` only. The Cloud API gateway has nothing to do with Kudisms and does not read it.

### Choosing the Cloud API gateway

Naming it on the send always works:

```php
swissecho()->route('whatsapp', fn ($ms) => $ms->to('2348012345678')->content('hi'))
    ->gateway('directwhatsapp')
    ->go();
```

To make it the default instead, **two settings matter, not one.** `SWISSECHO_WHATSAPP_GATEWAY` sets the route's default, but a `place` overrides it — and `nga` is mapped to `kudisms` out of the box:

```php
// config/swissecho.php
'whatsapp' => [
    'gateway' => env('SWISSECHO_WHATSAPP_GATEWAY', 'kudisms'),
    'places'  => [
        'nga' => ['phonecode' => '+234', 'gateway' => 'directwhatsapp'],  // change this too
    ],
],
```

Setting the variable alone leaves Nigerian numbers going through Kudisms. Alternatively drop the `gateway` key from the place and let the route default apply.

> **Note.** Set `SWISSECHO_WHATSAPP_GATEWAY`, not `SWISSECHO_ROUTE_GATEWAY`. The latter is kept working only as a fallback for this route, and naming an SMS gateway such as `termii` in either now raises a clear `SwissechoException` rather than failing on an undefined key.

Both send **one request per recipient**, because every WhatsApp API here takes a single recipient. A send to three numbers produces three requests and three `AfterSend` events, each naming the number it went to.

### `directwhatsapp` — the WhatsApp Cloud API

Meta's own endpoint, with no reseller in between. Create a WhatsApp app and number at [developers.facebook.com](https://developers.facebook.com), then:

```dotenv
WHATSAPP_PHONE_NUMBER_ID=123456789
WHATSAPP_ACCESS_TOKEN=EAAG...
```

```php
swissecho()->route('whatsapp', function ($ms) {
    return $ms->to('2348012345678')->content('Your order has shipped');
})->gateway('directwhatsapp')->go();
```

That posts to the documented endpoint:

```
POST https://graph.facebook.com/v21.0/{phone-number-id}/messages
Authorization: Bearer {access_token}
```

`WHATSAPP_CLOUD_API_VERSION` and `WHATSAPP_CLOUD_HOST` are configurable so a new Graph API version needs nothing but an environment change. `WHATSAPP_CLOUD_URL` replaces the whole endpoint, for a proxy.

#### The 24-hour window

Free-form text only reaches a user **within 24 hours of their last message to you**. Outside that window WhatsApp requires an approved template, and a text message is accepted by the API and never delivered — which looks like success everywhere except the recipient's phone.

Name a template and every send uses it, with the message body as its single body parameter:

```dotenv
WHATSAPP_TEMPLATE_NAME=order_update
WHATSAPP_TEMPLATE_LANGUAGE=en_US
```

```php
'template' => [
    'name'     => env('WHATSAPP_TEMPLATE_NAME'),
    'language' => env('WHATSAPP_TEMPLATE_LANGUAGE', 'en_US'),
],
```

### `kudisms`

A reseller. It requires the template code registered on your Kudisms account:

```dotenv
KUDISMS_API_KEY=your_api_key
KUDISMS_URL=your_url
KUDISMS_TEMPLATE_CODE=your_template_code
```

> **Changed in this release.** `KUDISMS_TEMPLATE_CODE` is new and required — the template code used to be hardcoded to a placeholder value, so every Kudisms WhatsApp send was rejected by the provider. Sends will throw until you set it.

---

## Transport, TLS and Timeouts

Every gateway request goes out with the same transport settings, applied centrally rather than per gateway:

```php
// config/swissecho.php
'transport' => [
    'verify_tls'      => env('SWISSECHO_VERIFY_TLS', true),
    'timeout'         => env('SWISSECHO_TIMEOUT', 30),
    'connect_timeout' => env('SWISSECHO_CONNECT_TIMEOUT', 10),
],
```

**Keep `verify_tls` on.** These requests carry your API keys, and some providers take an account password as a query parameter. With verification off, anything sitting between your app and the provider can present its own certificate and read them.

If one provider has a genuinely broken certificate, turn it off while you chase them — but understand that it applies to every gateway, not just that one.

A `timeout` of `0` means cURL waits forever, so a hung provider holds a PHP worker open for as long as the socket stays up — sends run inline in the request unless the notification is queued.

Two exceptions: Slack and Telegram substitute 30 and 10 seconds when the configured value is `0`, so they never wait forever. And the Termii voice gateway sets its own 30-second timeout, which a `0` here deliberately leaves in place.

---

## Usage

Swissecho can be used in **two ways**: directly (without a Notification class), or through Laravel's notification system.

### Access Methods

You have three ways to get a Swissecho instance:

```php
// 1. Global helper function
swissecho()

// 2. Laravel Facade
use Tekkenking\Swissecho\Facades\Swissecho;

Swissecho::

// 3. From the container
app('swissecho')
```

For Laravel facade calls, import `Tekkenking\Swissecho\Facades\Swissecho`.

Each of the three hands you a **fresh instance**. Build a send on it and call `go()`; nothing carries over to the next call.

---

### A) Direct Sending (Without Notification Classes)

#### Quick Send — One Liner

The simplest way to send a message. Uses the **default route** and **default gateway** from config:

```php
swissecho()->quick('2348012345678', 'Your OTP code is 1234');
```

#### Quick Send with a Specific Gateway

```php
swissecho()->gateway('routemobile')->quick('2348012345678', 'Your OTP code is 1234');
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
->to('2348011111111')       // fallback only: ignored if the callback set ->to()
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

**Incoming Webhook mode** (set `SLACK_WEBHOOK_URL` — no recipient needed):

```php
swissecho()->message('Deployment finished ✅')
    ->route('slack')
    ->go();
```

**Bot Token mode** (set `SLACK_BOT_TOKEN` — channel is required):

```php
swissecho()->message('Hello team!')
    ->to('#alerts')
    ->route('slack')
    ->go();
```

#### Sending via Telegram

```php
swissecho()->message('Server restarted 🔄')
    ->to('123456789')   // your Telegram chat_id
    ->route('telegram')
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
     * If omitted, defaults to ['sms', 'slack', 'whatsapp', 'telegram', 'voice'] -
     * so a toVoice() method also places a voice call unless you narrow this.
     */
    public function swissechoRoutes($notifiable): array
    {
        return ['sms'];
    }

    /**
     * Build the SMS message.
     * Method name follows the pattern: to{Route}  →  toSms, toVoice, toWhatsapp, toSlack, toTelegram
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
     *
     * This one WINS. If the model has a phone attribute, Option B is never
     * called, so do not define both and expect the method to decide.
     */

    /**
     * Option B: Define this method for custom logic, when there is no
     * phone attribute on the model.
     */
    public function routeNotificationPhone(): string
    {
        return $this->mobile_number;
    }

    /**
     * (Optional) Override the sender ID per user. Checked before the
     * gateway's configured sender. There is one per route:
     * routeNotificationSmsSender(), routeNotificationVoiceSender() and
     * routeNotificationWhatsappSender() - the WhatsApp one falls back to
     * the SMS one when it is absent.
     */
    public function routeNotificationSmsSender(): string
    {
        return $this->brand_sender_id;
    }

    /**
     * (Optional) Slack channel for this user.
     */
    public function routeNotificationSlackChannel(): string
    {
        return '#team-alerts';
    }

    /**
     * (Optional) Telegram chat_id for this user.
     */
    public function routeNotificationTelegramChatId(): string
    {
        return $this->telegram_chat_id;
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

## The Result

Every send returns a `SendResult`, in the same shape whichever provider handled it:

```php
$result = swissecho()->quick('2348012345678', 'Your OTP code is 1234');

if ($result->success) {
    // accepted by the provider
    $messageId = $result->messageId;
} else {
    logger()->warning('SMS failed', [
        'code'   => $result->errorCode,
        'reason' => $result->errorMessage,
    ]);
}
```

| Property | Array key | Type | Meaning |
|---|---|---|---|
| `success` | `success` | `bool` | The provider accepted the message |
| `messageId` | `message_id` | `string\|null` | The provider's own ID for it, when it returns one |
| `errorCode` | `error_code` | `string\|null` | The provider's error code, on failure |
| `errorMessage` | `error_message` | `string\|null` | The provider's reason, on failure |
| `providerRes` | `provider_res` | `mixed` | The provider's reply, untouched. Null in mock mode, or when nothing came back |
| `to` | `to` | `mixed` | The recipient or recipients |
| `route` | `route` | `string\|null` | `sms`, `voice`, `whatsapp`, `slack` or `telegram` |
| `gateway` | `gateway` | `string\|null` | The gateway that handled it |
| `mocked` | `mocked` | `bool` | Mock mode stood in for the provider |
| `results` | — | `SendResult[]` | One per request, when a send made several |

It also reads as an array — `$result['success']`, `$result['provider_res']` — and has `failed()`, `toArray()` and JSON serialisation. It cannot be changed once made.

**Accepted is not delivered.** `success` means the provider took the message. Whether it reached the handset is what the provider's delivery report says, which arrives through [Webhooks](#webhooks).

### Sends that make several requests

WhatsApp, voice, Twilio and Vonage take one recipient per request, so a send to three numbers makes three requests. The result you get back folds them together: `success` is true only if every request was accepted, the first failure supplies `errorCode` and `errorMessage`, and each request's own result is in `results`:

```php
foreach ($result->results as $one) {
    echo $one->to, $one->success ? ' accepted' : ' rejected: '.$one->errorMessage;
}
```

### Notifications

A notification can go out on several routes, so `send()` returns one result per route, keyed by route. Laravel passes that on as the response on its own `NotificationSent` event:

```php
use Illuminate\Notifications\Events\NotificationSent;

Event::listen(function (NotificationSent $event) {
    if ($event->channel === 'swissecho') {
        $sms = $event->response['sms'] ?? null;   // a SendResult
    }
});
```

### How each provider's reply is judged

A provider signals a rejection in its own way, and several do it inside an HTTP 200. Each gateway reads its own provider's reply:

| Gateway | Accepted when | Message ID from |
|---|---|---|
| `twilio` | HTTP 2xx | `sid` |
| `vonage` | every `messages[].status` is `"0"` | `message-id` |
| `termii` (SMS and voice) | HTTP 2xx | `message_id` |
| `nigerianbulksms` | no `error` in the body | none returned |
| `routemobile` | every item starts `1701` | third field |
| `smsbroadcast` | lines start `OK:`, not `BAD:` or `ERROR:` | third field |
| `directwhatsapp` | no `error` object | `messages[0].id` |
| `kudisms` | `error_code` is `"000"` | after the `\|` in `data` |
| `tnz` | `Result` is not `Failed` or `Unauthorized` | `MessageID` |
| `montnets` | `result` is `0` | `msgid` |
| `textngxyz` | `status` is not `ERROR` | `reference` |
| `wirepick` | HTTP status only | — |
| Slack | `ok` is true, or a webhook's HTTP 2xx | `ts` |
| Telegram | `ok` is true | `result.message_id` |

A rule only applies when the marker it knows is in the reply. Anything else is judged by HTTP status, where a 4xx or 5xx is a failure.

These rules come from each provider's documentation and are pinned by tests against the documented example replies. **They have not been checked against live accounts.** If a provider's reply ever disagrees with its documentation, `providerRes` holds exactly what came back.

---

## API Reference

### `swissecho()` — building and sending

`swissecho()`, the `Swissecho` facade and `app('swissecho')` each return a fresh sender.

| Method | Returns | What it does |
|---|---|---|
| `route(?string $route = null, string\|callable\|null $message = null)` | `$this` | Chooses the channel: `sms`, `voice`, `whatsapp`, `slack` or `telegram`. Null uses `SWISSECHO_ROUTE`. The second argument is the message, as a string or as a callback that receives a `SwissechoMessage` and returns it. Throws if the route does not exist. |
| `gateway(string $gateway)` | `$this` | Names the gateway for this send. Wins over the place's gateway and the route default. |
| `message(string\|callable $message)` | `$this` | Sets the message, as `route()`'s second argument does. |
| `to($recipient)` | `$this` | A fallback recipient, used only if the message callback set none. |
| `sender($sender)` | `$this` | A fallback sender, used only if the message callback set none. |
| `makeNotifiable($notifiable)` | `$this` | Gives a direct send an object to resolve the recipient, place and identifier from, as a notification has. |
| `go()` | `SendResult\|null` | Sends. Null only for a custom gateway that reports no result. |
| `quick($recipient, $message)` | `SendResult\|null` | Sends a message on the default route in one call. |
| `send($notifiable, Notification $notification)` | `array` | What Laravel calls for a notification. Returns one `SendResult` per route, keyed by route. |

The public properties `$to`, `$sender` and `$message` can be set directly in place of the methods of the same name.

### `SwissechoMessage` — the message

The builder a callback receives, and what a notification's `toSms()`, `toVoice()`, `toWhatsapp()`, `toSlack()` or `toTelegram()` returns. Every method returns the message.

| Method | Description | Example |
|---|---|---|
| `->line($text)` | Appends a line of text to the message body. Multiple calls add new lines. | `->line('Hello')` |
| `->content($text)` | Alias for `line()`. | `->content('Hello')` |
| `->to($recipient)` | Sets the recipient(s). Accepts a string, a comma-separated list or an array. | `->to('234801..., 234809...')` |
| `->sender($name)` | Sets the sender ID. Sent as given. | `->sender('MyApp')` |
| `->from($name)` | Alias for `sender()`. | `->from('MyApp')` |
| `->gateway($name)` | Names the gateway for this message. | `->gateway('termii')` |
| `->place($code)` | Sets the place (e.g. `nga`), which chooses the gateway and dialling code. | `->place('nga')` |
| `->phonecode($code)` | Sets the dialling code. Normally taken from the place. | `->phonecode('234')` |
| `->identifier($id)` | Attaches your own reference, carried through to the `AfterSend` event. | `->identifier($order->id)` |
| `->route($name)` | Sets the route on the message itself. | `->route('sms')` |
| `->get()` | Returns the message as an array. | |

### `SendResult` — the outcome

See [The Result](#the-result).

### Events

| Event | Dispatched | Properties |
|---|---|---|
| `Tekkenking\Swissecho\Events\AfterSend` | After every request, mock sends included | `$result` (`SendResult`), `$formattedResponse` (`array`), `$insightPayload` (`array`), `$identifier` |
| `Tekkenking\Swissecho\Events\WebhookReceived` | When a provider callback passes secret verification | `$route`, `$gateway`, `$payload`, `$headers` |

Detail in [Events](#events) and [Webhooks](#webhooks).

### What a notification defines

| Method | Purpose |
|---|---|
| `toSms($notifiable)`, `toVoice($notifiable)`, `toWhatsapp($notifiable)`, `toSlack($notifiable)`, `toTelegram($notifiable)` | Return the `SwissechoMessage` for that route. Defining one opts the notification in to the route. |
| `swissechoRoutes($notifiable)` | Optional. The routes to use, as an array. Without it every route with a `to…()` method is used. |

### What a notifiable can define

| Member | Used for |
|---|---|
| `phone` attribute | The recipient. Checked first. |
| `routeNotificationPhone()` | The recipient, when there is no `phone` attribute. |
| `routeNotificationPlace()` | The place for this recipient, e.g. `nga`. |
| `routeNotificationSmsSender()`, `routeNotificationVoiceSender()`, `routeNotificationWhatsappSender()` | The sender ID for that route. WhatsApp falls back to the SMS one. |
| `routeNotificationSlackChannel()` | The Slack channel. |
| `routeNotificationTelegramChatId()` | The Telegram chat ID. |
| `routeNotificationIdentifier()` | The identifier reported on the `AfterSend` event. Defaults to the class and primary key. |

### Configuration keys

`config/swissecho.php`, published with `php artisan vendor:publish --tag=swissecho-config`.

| Key | Env variable | Meaning |
|---|---|---|
| `live` | `SWISSECHO_ENABLED` | True sends for real. False is mock mode. |
| `sender` | `SWISSECHO_SENDER` | The sender ID when the message and gateway set none. |
| `route` | `SWISSECHO_ROUTE` | The default route. |
| `fake` | `SWISSECHO_FAKE` | Mock driver: `log` or `mail`. |
| `fake_mail` | `SWISSECHO_FAKE_MAIL` | Where the `mail` mock driver sends. |
| `redact_secrets` | `SWISSECHO_REDACT_SECRETS` | Mask credentials in the `AfterSend` payload. |
| `transport.verify_tls` | `SWISSECHO_VERIFY_TLS` | Verify the provider's certificate. |
| `transport.timeout` | `SWISSECHO_TIMEOUT` | Seconds to wait for a reply. |
| `transport.connect_timeout` | `SWISSECHO_CONNECT_TIMEOUT` | Seconds to wait for the connection. |
| `routes_options.{route}.gateway` | `SWISSECHO_SMS_GATEWAY`, `SWISSECHO_VOICE_GATEWAY`, `SWISSECHO_WHATSAPP_GATEWAY` | The route's default gateway. |
| `routes_options.{route}.default_place` | `SWISSECHO_DEFAULT_PLACE` | The place when a send names none. |
| `routes_options.{route}.places.{place}` | — | `phonecode` and, optionally, the `gateway` for that place. |
| `routes_options.{route}.gateway_options.{gateway}` | per gateway, see [Environment Variables](#environment-variables) | That gateway's settings. |

Keys any gateway block understands, besides its own credentials:

| Key | Meaning |
|---|---|
| `class` | The gateway class. |
| `sender` | The sender ID for this gateway. |
| `requires_sender` | Refuse to send when no sender is set anywhere. |
| `sender_max_length` | Refuse a sender longer than this. |
| `webhook` | `secret`, and optionally `class` and `handle`, for provider callbacks. |

### Writing a gateway

See [Adding a Custom SMS Gateway](#-adding-a-custom-sms-gateway). A gateway extends `BaseGateway` and provides:

| Method | Required | Purpose |
|---|---|---|
| `init(): mixed` | yes | Build the request payload. |
| `send($data)` | yes | Return a configured cURL handle. Swissecho executes it, applying TLS verification and timeouts. |
| `interpretResponse(mixed $body, int $httpCode): array` | no | Decide whether the provider accepted the message. Return `success`, and any of `message_id`, `error_code`, `error_message`. The default goes by HTTP status. |
| `checkDependencies(): array` | no | Classes the gateway needs, each as `[class, composer package]`. |

Add `use \Tekkenking\Swissecho\SendsPerRecipient;` to a gateway whose provider takes one recipient per request; `$this->currentRecipient` is then the recipient of the request being built.

### Exceptions

Everything the package throws is a `Tekkenking\Swissecho\SwissechoException`, with a message saying what to set. It is thrown before any request is made when: the route or gateway does not exist; no place can be determined, or the place is not configured or has no `phonecode`; there is no recipient or no message body; a gateway that requires a sender has none, or the sender is over the gateway's limit; a gateway's credentials are missing; or the mock driver is not `log` or `mail`.

A provider rejecting the message is not an exception. It is a `SendResult` whose `success` is false.

---

## Mock Mode (Development & Testing)

When `SWISSECHO_ENABLED=false` (the default), **no real API calls are made**. Instead, messages are captured by the mock system.

This applies in every environment, not just `local`. If you run staging with `SWISSECHO_ENABLED=false`, your mock log fills up there too.

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

`SWISSECHO_FAKE_MAIL` has no default and must be an address you own — the mock email contains the real recipient and the real message body. Sending without it throws rather than mailing the body somewhere you don't control.

An unrecognised `SWISSECHO_FAKE` value throws too, naming the valid drivers.

---

## Events

After every message send (including mock sends), Swissecho dispatches the **`AfterSend`** event:

```
Tekkenking\Swissecho\Events\AfterSend
```

### Event Properties

| Property | Type | Description |
|---|---|---|
| `$result` | `SendResult` | The outcome, as [The Result](#the-result) describes. The one to build on. |
| `$insightPayload` | `array` | Contains `request` (the payload sent to the gateway) and `response` (raw gateway response) |
| `$formattedResponse` | `array` | `success`, `message_id`, `error_code`, `error_message`, `provider_res` and `mocked`, alongside `from`, `to`, `body`, `route`, `gateway`, `identifier`, `timestamp`, and the older `status` and `partner_response` |
| `$identifier` | `string|null` | A short reference to the notifiable, e.g. `App\Models\User:42` |

#### Reading the outcome

`$event->result->success`, or `$formattedResponse['success']`, is true when the provider accepted the message. It is read from the provider's own reply, so a rejection reported inside an HTTP 200 counts as a failure:

```php
public function handle(AfterSend $event): void
{
    if ($event->result->failed()) {
        logger()->warning('Send failed', [
            'gateway' => $event->result->gateway,
            'code'    => $event->result->errorCode,
            'reason'  => $event->result->errorMessage,
        ]);
    }
}
```

`provider_res` is the provider's reply exactly as it arrived.

`status` and `partner_response` are still there for listeners written before 1.3.0. `status` now agrees with `success`. `partner_response` is the reply on success and, on failure, an array of `error`, `message`, `http_code` and `response`.

A `success` of true means the provider accepted the message. It is not a delivery receipt — for that you want the provider's own callback, which is what [Webhooks](#webhooks) is for.

#### Credentials are masked

`$insightPayload['request']` is the payload the gateway built, and for several providers that includes the credentials — an `api_key`, or a URL carrying `username=` and `password=`. Those values are masked before the event is dispatched:

```php
['api_key' => '[redacted]', 'phone_number' => '2348012345678']
?username=acme&password=[redacted]&message=Hi+there
```

Everything that is not a credential is left alone, so the record of what you sent stays useful. Set `SWISSECHO_REDACT_SECRETS=false` if you need the raw request to debug a provider — and remember that anything logging the event is then logging credentials, and a queued listener writes them into your jobs table.

#### The identifier

`$identifier` is the notifiable's class and primary key, not the notifiable itself. Choose your own value by defining `routeNotificationIdentifier()` on the notifiable:

```php
public function routeNotificationIdentifier()
{
    return 'order-'.$this->order_id;
}
```

An explicit `->identifier(...)` on the message builder always wins.

> **Changed in this release.** `$identifier` used to be the notifiable **object**, so a listener logging `$event->formattedResponse` wrote the whole model — email, phone, addresses — into its log context. If you relied on `$event->identifier->someAttribute`, use `routeNotificationIdentifier()` instead.

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

Providers send delivery reports and other callbacks to a route Swissecho registers for you:

```
POST /webhook/swissecho/route/{route}/{gateway}/{secret}
```

for example `https://yourapp.com/webhook/swissecho/route/sms/termii/your-secret-here`.

Configure a secret per gateway in `config/swissecho.php`:

```php
'termii' => [
    // ...
    'webhook' => [
        'secret' => env('TERMII_WEBHOOK_SECRET'),
    ],
],
```

The secret in the URL is compared against that value with `hash_equals`. A gateway with no `webhook` block returns 404, an unset secret returns 401, and a wrong secret returns 401 — nothing is dispatched in any of those cases.

### Handling a callback

A verified callback dispatches an event:

```php
Tekkenking\Swissecho\Events\WebhookReceived
```

| Property | Type | Description |
|---|---|---|
| `$route` | `string` | The route it arrived on, e.g. `sms` |
| `$gateway` | `string` | The gateway key, e.g. `termii` |
| `$payload` | `array` | The request body and query |
| `$headers` | `array` | The request headers |

It carries plain arrays rather than the `Request` object, so a **queued** listener can serialise it:

```php
<?php

namespace App\Listeners;

use Tekkenking\Swissecho\Events\WebhookReceived;

class RecordDeliveryReport
{
    public function handle(WebhookReceived $event): void
    {
        if ($event->gateway === 'termii') {
            DeliveryReport::create([
                'message_id' => $event->payload['message_id'] ?? null,
                'status'     => $event->payload['status'] ?? null,
            ]);
        }
    }
}
```

### Using a class instead of the event

Name one in the webhook block and it is called in place of the event:

```php
'webhook' => [
    'secret' => env('TERMII_WEBHOOK_SECRET'),
    'class'  => \App\Webhooks\TermiiCallback::class,
    'handle' => 'handle',   // the method to call, passed the Request
],
```

### Which Slack credential wins

If both `SLACK_WEBHOOK_URL` and `SLACK_BOT_TOKEN` are set, **the webhook wins**, and a webhook posts only to the channel it was created for. So `->to('#alerts')` is accepted and then ignored, and the message lands in the webhook's own channel. Configure the bot token alone if you need to choose the channel per message.

### Security note

The secret travels as a URL path segment, so it appears in web server access logs, Laravel's request logs and any exception report that captures the full URL. Rotate it if those logs are shared, and prefer the provider's own signature header where one exists.

> **Changed in this release.** The previous handler never worked: the route file was never registered, the config was read at route level rather than per gateway, and the secret was never actually compared. `'handle'` is also no longer a method on the gateway class — gateway classes need a message to construct, so they were never a viable place for this.

---

## Helper Functions

Swissecho provides global helper functions for phone number manipulation:

| Function | Description |
|---|---|
| `swissecho()` | Returns a fresh Swissecho sender |
| `addCountryCodeToPhoneNumber($phone, $code)` | Puts a number into digits-only international form for the given dialling code. A number written with `+` is kept as given. |
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

## 🔌 Adding a Custom SMS Gateway

Swissecho is designed to be easily extensible. If you need a gateway that isn't built in, you can wire up your own in **three steps** — no need to touch the package source at all.

---

### Step 1: Create Your Gateway Class

Create a folder anywhere in your project (e.g., `app/Sms/Gateways/MyProvider/`) and add a class inside it. The class **must**:

- Extend `Tekkenking\Swissecho\Routes\Sms\Gateways\BaseGateway`
- Implement two methods: `init()` and `send()`

```php
<?php

namespace App\Sms\Gateways\MyProvider;

use Tekkenking\Swissecho\Routes\Sms\Gateways\BaseGateway;

class MyProvider extends BaseGateway
{
    /**
     * Build and return the request payload that will be passed to send().
     *
     * Available properties (populated by BaseGateway from the message builder):
     *   $this->to      — array of recipient phone numbers
     *   $this->sender  — sender ID / name
     *   $this->body    — the SMS message text
     *   $this->config  — your gateway's config block from config/swissecho.php
     *
     * @return mixed  Any value you like — it will be passed directly to send().
     *                Typically a URL string, an array, or a Guzzle request object.
     */
    public function init(): mixed
    {
        // Example: build a query-string URL
        $url  = $this->config['url'];
        $url .= '?api_key='   . $this->config['auth']['api_key'];
        $url .= '&from='      . $this->sender;
        $url .= '&to='        . implode(',', $this->to);
        $url .= '&message='   . urlencode($this->body);

        return $url;
    }

    /**
     * Receive the value returned by init(), make the HTTP call,
     * and return a configured cURL handle.
     *
     * @param  mixed $data  Whatever init() returned.
     * @return \CurlHandle|bool  A ready-to-execute cURL handle.
     *                           Swissecho calls curl_exec() on it automatically.
     */
    public function send($data): \CurlHandle|bool
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $data);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // ⚠️ Only disable in local/testing environments; keep true in production
        return $ch;
    }
}
```

> **How it works under the hood:** after `send()` returns the cURL handle, Swissecho's `SwissechoGatewayTrait::execCurl()` calls `curl_exec()`, collects the response, formats it, and fires the `AfterSend` event.

---

### Step 2: Register the Gateway in the Config

Open `config/swissecho.php` and add your gateway to the `routes_options.sms.gateway_options` array:

```php
use App\Sms\Gateways\MyProvider\MyProvider; // ← import your class

'routes_options' => [
    'sms' => [
        'gateway_options' => [

            // ... existing gateways ...

            'myprovider' => [               // ← the key becomes the gateway "name"
                'class'  => MyProvider::class,
                'url'    => env('MYPROVIDER_URL'),
                'auth'   => [
                    'api_key' => env('MYPROVIDER_API_KEY'),
                ],
                // add any other keys your gateway needs — they will all be
                // available inside your class as $this->config['...']
            ],
        ],
    ],
],
```

Add the corresponding values to your `.env`:

```dotenv
MYPROVIDER_URL=https://api.myprovider.com/send
MYPROVIDER_API_KEY=your_api_key_here
```

---

### Step 3: Use Your Gateway

That's it — your gateway is now a first-class citizen in Swissecho. Use it exactly like any built-in gateway:

```php
// Direct / fluent sending
swissecho()->gateway('myprovider')->quick('2348012345678', 'Hello!');

// Or inside a route callback
swissecho()->route('sms', function ($ms) {
    return $ms->to('2348012345678')
              ->content('Your OTP is 9988')
              ->gateway('myprovider');
})->go();

// Or inside a Laravel Notification
public function toSms($notifiable): SwissechoMessage
{
    return (new SwissechoMessage())
        ->line('Your order has shipped!')
        ->gateway('myprovider');
}
```

You can also map it to a country in `places` for automatic geo-routing:

```php
'places' => [
    'zaf' => [                  // South Africa, for example
        'gateway'   => 'myprovider',
        'phonecode' => '27',
    ],
],
```

---

### `BaseGateway` Quick Reference

| Member | Type | Description |
|---|---|---|
| `$this->to` | `array` | Recipient phone numbers |
| `$this->sender` | `string` | Sender ID / name |
| `$this->body` | `string` | The message text |
| `$this->config` | `array` | Your gateway's full config block from `swissecho.php` |
| `init(): mixed` | abstract method | Build the request payload; return value is passed to `send()` |
| `send($data): \CurlHandle|bool` | abstract method | Set up and return a cURL handle; Swissecho executes it |
| `interpretResponse(mixed $body, int $httpCode): array` | optional override | Decide whether the provider accepted the message. Defaults to the HTTP status. |
| `$this->decodeJson($body)` | helper | The reply as an array, or null if it is not JSON |

### Telling Swissecho what your provider's reply means

By default a gateway's send counts as accepted unless the HTTP status is 4xx or 5xx. If your provider reports a rejection inside an HTTP 200, or returns a message ID worth keeping, override `interpretResponse()`:

```php
protected function interpretResponse(mixed $body, int $httpCode): array
{
    $data = $this->decodeJson($body);

    if ($data === null) {
        return parent::interpretResponse($body, $httpCode);   // not JSON: go by HTTP status
    }

    if (($data['status'] ?? null) !== 'sent') {
        return [
            'success'       => false,
            'error_code'    => $data['code'] ?? null,
            'error_message' => $data['reason'] ?? 'Rejected',
        ];
    }

    return ['success' => true, 'message_id' => $data['id'] ?? null];
}
```

That is what fills in the `SendResult` your callers get back from `go()`.

---

## License

This package is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).