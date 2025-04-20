<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/db.php';

//die('Reset DB is not allowed');
try {


    // SQL Queries
    $queries = [
        "DELETE FROM verification_logs;",
        "DELETE FROM verification_errors;",
        "DELETE FROM backlink_verification_helper;",
        "UPDATE campaigns SET last_checked = NULL;",
        "UPDATE backlinks SET last_checked = NULL, status = 'pending';",
        "DELETE FROM cron_jobs;"
    ];

    // Execute each query
    foreach ($queries as $query) {
        $pdo->exec($query);
    }

    clearCronLog(__DIR__ . '/logs/cron.log');
    echo "Database reset successfully and logs cleared from log file.";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}


function clearCronLog($logFilePath = __DIR__ . '/logs/cron.log')
{
    if (file_exists($logFilePath)) {
        // Truncate the file to 0 bytes
        file_put_contents($logFilePath, '');
    } else {
        echo "Log file not found: $logFilePath";
    }
}
