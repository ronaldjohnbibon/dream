<?php

return [
    'retention_days'          => (int) env('SYSTEM_LOG_RETENTION_DAYS', 180),
    'activity_retention_days' => (int) env('SYSTEM_LOG_ACTIVITY_RETENTION_DAYS', 730),
    'security_retention_days' => (int) env('SYSTEM_LOG_SECURITY_RETENTION_DAYS', 730),
];
