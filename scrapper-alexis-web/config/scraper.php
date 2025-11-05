<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Python Scraper Directory
    |--------------------------------------------------------------------------
    |
    | The absolute path to the Python scraper directory. This should be the
    | parent directory of the Laravel web app. You can set this via the
    | SCRAPER_PYTHON_PATH environment variable.
    |
    */
    'python_path' => env('SCRAPER_PYTHON_PATH', dirname(base_path())),

    /*
    |--------------------------------------------------------------------------
    | Logs Directory
    |--------------------------------------------------------------------------
    |
    | The directory where Python scraper logs are stored, relative to the
    | Python scraper path.
    |
    */
    'logs_dir' => env('SCRAPER_LOGS_DIR', 'scrapper-alexis/logs'),

    /*
    |--------------------------------------------------------------------------
    | Data Directory
    |--------------------------------------------------------------------------
    |
    | The directory where scraped data and images are stored, relative to
    | the Python scraper path.
    |
    */
    'data_dir' => env('SCRAPER_DATA_DIR', 'scrapper-alexis/data'),

    /*
    |--------------------------------------------------------------------------
    | Log File Patterns
    |--------------------------------------------------------------------------
    |
    | Define the log file naming patterns for different scrapers.
    |
    */
    'log_files' => [
        'facebook' => 'relay_agent_' . date('Ymd') . '.log',
        'twitter' => 'twitter_cron.log',
        'page-poster' => 'page_poster_' . date('Ymd') . '.log',
        'image-generator' => 'image_generator_' . date('Ymd') . '.log',
        'execution' => 'cron_execution.log',
    ],
];

