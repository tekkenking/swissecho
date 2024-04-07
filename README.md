# Swissecho Laravel SMS Notification Channel

Swissecho is a Laravel SMS notification channel package that provides a convenient way to send SMS messages through various gateways. This package is designed to be flexible, allowing you to customize the message content, recipient, sender, and gateway.

## Installation

To install the Swissecho package, simply require it via Composer:

```bash
composer require tekkenking/swissecho
```

## Laravel Version Compatibility

For Laravel 5.5 and above, the package should be automatically discovered.
For Laravel versions below 5.5, you may need to add the service provider to your config/app.php file:

```php
'providers' => [
    // ...
    Tekkenking\Swissecho\SwissechoServiceProvider::class,
],
```

## Basic Usage

The following are different ways to use the Swissecho package:

### General Usage

```php
    Swissecho::route('sms', function($ms) {
        return $ms->to('XXXXXXXXXXXX, XXXXXXXXXXXX ')
            ->content('Wonders shall never end')
            ->line('Hello world');
            //->gateway('routemobile') //optional
            //->sender('SimbooBiz'); //optional
    })
    ->to('XXXXXXXXXXXX') //optional
    ->sender('Smart') //optional
    ->gateway('termii') //optional
    ->go();
```

### Use Case 1 (SMS)

```php
    swissecho()->route('sms', function($ms) {
        return $ms->to('XXXXXXXXXXXX, XXXXXXXXXXXX ')
            ->content('Wonders shall never end')
            ->line('Hello world');
            //->gateway('routemobile') //optional
            //->sender('SimbooBiz'); //optional
    })
    ->to('XXXXXXXXXXXX') //optional
    ->sender('Smart') //optional
    ->gateway('termii') //optional
    ->go();
```

### Use Case 2

```php
    swissecho()->quick('XXXXXXXXXXXX', "My name is bola");
```

### Use Case 3 (Slack)

```php
    swissecho()->message("Hello world")
    ->to('XXXXXXXXXXXX')
    //->sender('Raimi')
    //->gateway('termii')
    ->route('slack')
    ->go();
```

### Use Case 4 (Vonage)

```php
    swissecho()->gateway('vonage')->quick('XXXXXXXXXXXX', "My name is bola");
```

### Use Case 5 (Termii)

```php
    $sw = swissecho();
    //$sw->mockNotifiable($user);
    $sw->gateway('termii');
    $sw->to = "XXXXXXXXXXXX";
    $sw->sender = "AXIX";
    $sw->message = "The world is a beauty place created by GOD";
    $sw->go();
```

Feel free to customize the examples based on your specific use case and requirements.

## License

This package is open-sourced software licensed under the MIT license.
