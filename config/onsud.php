<?php

return [
    /*
    |--------------------------------------------------------------------------
    | ONSUD Development Mode Filters
    |--------------------------------------------------------------------------
    |
    | When APP_ENV is not 'production', the import command automatically
    | applies these filters to avoid importing the full 41M record dataset.
    | Set postcode_filter to the postcode area closest to your dev region,
    | or use lad_filter with a specific LAD GSS code (e.g. E06000054).
    |
    */

    'dev_postcode_filter' => env('ONSUD_DEV_POSTCODE_FILTER', 'SN'),
    'dev_record_limit' => (int) env('ONSUD_DEV_RECORD_LIMIT', 5000),
    'dev_lad_filter' => env('ONSUD_DEV_LAD_FILTER', null),
];
