<?php

return [
    'max_rows' => (int) env('IMPORT_MAX_ROWS', 5000),
    'max_file_kb' => (int) env('IMPORT_MAX_FILE_KB', 2048),
    'raw_retention_days' => (int) env('IMPORT_RAW_RETENTION_DAYS', 7),
];
