<?php

use Tekkenking\Swissecho\Webhooks\Foundation;


//Example url: https://blablad.com/webhook/swissecho/route/sms/termii/kjsdfu923hjhds9893ioe
Route::any('webhook/swissecho/route/{route}/{class}/{secret}', [Foundation::class, 'handle'])->name('swissecho.webhook');


