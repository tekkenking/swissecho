# Changelog

All notable changes to this package are recorded here. Versions follow [Semantic Versioning](https://semver.org/).

## 1.3.0 — unreleased

### Added

- **A result from every send.** `go()` and `quick()` return a `SendResult` with `success`, `messageId`, `errorCode`, `errorMessage` and `providerRes`, the provider's reply untouched. It reads as an array too, under `success`, `message_id`, `error_code`, `error_message` and `provider_res`. `send()` returns one per route. The `AfterSend` event carries it as `$result`, and the same keys are added to `formattedResponse`.
- **Each provider's reply is read by its own rule.** Success used to mean an HTTP status under 400, so a rejection inside an HTTP 200 — which SMS Broadcast, NigeriaBulkSMS, RouteMobile, Vonage and others do — was reported as a successful send. Gateways now override `interpretResponse()` with their provider's documented format; any without a rule keep the HTTP check.
- **Twilio and Vonage** SMS gateways.
- `requires_sender` and `sender_max_length` on any gateway's config.
- Recipients are formatted by libphonenumber, which handles spaces, brackets and dashes in a number and countries whose rules differ, such as Italy keeping its leading 0.
- CI on PHP 8.2 to 8.4 against Laravel 11 to 13, and PHPStan at level 5.
- A full API reference in the README.

### Changed

- **`swissecho()`, the facade and `app('swissecho')` return a fresh instance each time.** The binding was a singleton, which is how one send's state reached the next.
- **Senders are sent as given.** They used to be cut to 10 characters silently, one short of the 11 GSM allows, and that mangled phone-number senders. A sender over 10 characters that was being shortened now goes out whole.
- **A gateway that needs a sender refuses to send without one.** `termii`, `routemobile` and `smsbroadcast` now stop with a clear error when no sender is set anywhere, where they used to send an empty one and be rejected.
- **`status` on the `AfterSend` event can now be false for an HTTP 200.** It agrees with `success`, which is read from the provider's reply.
- The minimum PHP version is stated as 8.2. It always was in practice: Laravel 11 requires it.
- Direct sends and notification sends now run through one preparation path, so a direct send given a notifiable gets an identifier as a notification does.

### Deprecated

- `partner_response` and `status` in `formattedResponse`. They still work; use `provider_res` and `success`.
- `BaseRoute::msgBuilderInitForSendViaNotification()` and `msgBuilderInitForDirectSend()`. Both call `prepareAndSend()`.

### Not verified against live providers

The response rules, and the Twilio, Vonage and WhatsApp Cloud API gateways, follow each provider's documentation and are pinned by tests against the documented example replies. None has been exercised against a live account.

## 1.2.0 — 2026-11-14

### Fixed

- A number already carrying a country code was corrupted when the place was another country: `+447911123456` under `nga` became `234447911123456`.
- Slack discarded a requested channel when a webhook was also configured, and reported the channel as delivered. The bot token is now used when a channel is requested.
- Slack reported success in mock mode for a configuration that throws live.
- `makeNotifiable()` had no effect on a direct send.
- Slack and Telegram carried an empty request payload in mock mode, and reported a sender neither can send.
- `->gateway()` on a Slack or Telegram send was accepted and ignored. It now throws.
- Every send example in the README threw on a fresh install, because none set a place. The documented examples now run as tests.

### Removed

- `src/config.php`, a second copy of the config that nothing loaded.
- `BaseRouteInterface`, `coldBoot()`, and the route-level `class` keys for slack and telegram, none of which anything used.

## 1.1.1 — 2026-11-14

### Fixed

- **A message could be delivered to the wrong recipient.** A notifiable given to `makeNotifiable()` outlived its send and was used for the next one.
- A bare `go()` after a notification re-sent that notification.
- **Credentials reached the `AfterSend` event in clear for Termii and Montnets**, whose JSON payloads the redaction did not handle. Rotate those credentials if you log that event.
- Voice called only the first of several recipients.
- A gateway named inside a notification with `->gateway()` was ignored.
- A message with no body raised a `TypeError`, which `catch (Exception)` does not catch. It now raises `SwissechoException`.

## 1.1.0 — 2026-11-14

### Added

- `SWISSECHO_SMS_GATEWAY`, `SWISSECHO_VOICE_GATEWAY` and `SWISSECHO_WHATSAPP_GATEWAY` set each route's default gateway.

### Fixed

- The route's `gateway` config key was never read, so `SWISSECHO_ROUTE_GATEWAY` did nothing and a route's default was whichever gateway was listed first.
- Naming a gateway the route has no config for now raises an exception listing the ones it has.

## 1.0.1 — 2026-11-14

### Fixed

- Documentation only: `SWISSECHO_ROUTE_GATEWAY` was described with an example that broke WhatsApp sends, and switching to the Cloud API gateway was described as needing one setting when it needs two.

## 1.0.0 — 2026-11-14

First stable release.

### Fixed

- The voice route threw on every notification send, and cast OTP codes to integers, dropping leading zeros.
- Send state leaked between sends, so a later send could inherit an earlier one's gateway, and a notification restricted to one channel restricted the next.
- A rejected send was reported as successful: success was judged by `curl_errno` alone. HTTP 4xx and 5xx now count as failures.
- Gateway credentials were dispatched in the `AfterSend` payload. They are masked.
- The webhook feature did not work: its route was never registered, its config was read at the wrong level and its secret was never compared.
- Mock mode did nothing outside the `local` environment, and package errors were logged at `debug` and otherwise swallowed.
- TLS verification was switched off on five gateways, and none had a timeout.
- The Textngxyz, SMS Broadcast, NigeriaBulkSMS and Wirepick gateways each had a defect that broke or corrupted sends.
- The sender was resolved before the place chose the gateway, and a trailing comma in a recipient list sent a message to the bare country code.

### Added

- A `directwhatsapp` gateway on the WhatsApp Cloud API.
- A `transport` config block for TLS verification and timeouts.
- `routeNotificationIdentifier()` on a notifiable.

### Changed

- `$event->identifier` on `AfterSend` is a string such as `App\Models\User:42`, where it was the notifiable object.
- `KUDISMS_TEMPLATE_CODE` is required for the `kudisms` gateway, and `SWISSECHO_FAKE_MAIL` for the `mail` mock driver.
