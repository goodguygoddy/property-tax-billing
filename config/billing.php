<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Fee rates
    |--------------------------------------------------------------------------
    |
    | The fee is a percentage of the tax savings for a property. Clients with
    | at least "volume_threshold" properties enrolled get the volume rate on
    | every property, regardless of outcome.
    |
    */

    'standard_rate' => '0.30',
    'volume_rate' => '0.25',
    'volume_threshold' => 3,

    /*
    |--------------------------------------------------------------------------
    | Fee limits
    |--------------------------------------------------------------------------
    |
    | Applied after the fee has been rounded to the cent. A fee below the
    | minimum is waived to $0; a fee above the maximum is capped.
    |
    */

    'minimum_fee' => '10.00',
    'maximum_fee' => '2500.00',

];
