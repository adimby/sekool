<?php

return [
    'sms_enabled' => (bool) env('FANABE_SMS_ENABLED', false),
    'workflow' => [
        'max_daily_actions' => 50,
        'default_daily_actions' => 20,
        'quiet_hours_start' => 20,
        'quiet_hours_end' => 7,
        'timezone' => 'Indian/Antananarivo',
    ],
];
