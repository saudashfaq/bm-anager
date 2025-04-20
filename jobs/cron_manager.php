<?php

/**
 * Cron Job Manager
 * 
 * This file manages the execution of scheduled jobs:
 * - BacklinkVerifier: Runs every 30 minutes
 * - ProxyScraperValidator: Runs every 6 hours
 * 
 * This file should be called by the system cron job every 5 minutes
 * It will check if each job is due to run and execute it accordingly
 */

// Set error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Define the base path
define('BASE_PATH', dirname(__DIR__));

// Include required files
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db.php';

// Set up logging
$logFile = BASE_PATH . '/logs/cron.log';
$logDir = dirname($logFile);

// Create logs directory if it doesn't exist
if (!is_dir($logDir)) {
    mkdir($logDir, 0755, true);
    error_log("Created logs directory: $logDir");
}

// Function to log messages
function logMessage($message, $type = 'INFO')
{
    global $logFile;
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[$timestamp][$type] $message" . PHP_EOL;
    file_put_contents($logFile, $logMessage, FILE_APPEND);
    error_log($message);
}

// Job configuration
$jobs = [
    'proxy_scraper_validator' => [
        'file' => 'ProxyScraperValidator.php',
        'interval' => 360, // minutes (6 hours)
        'enabled' => true,
        'description' => 'Scrapes and validates proxies'
    ],
    'backlink_verifier' => [
        'file' => 'BacklinkVerifier.php',
        'interval' => 30, // minutes
        'enabled' => true,
        'description' => 'Verifies backlinks status'
    ]
];

// Initialize jobs in the database if they don't exist
function initializeJobs($pdo)
{
    global $jobs;

    logMessage("Initializing jobs in database");

    // Insert default jobs if they don't exist
    $stmt = $pdo->prepare("
        INSERT IGNORE INTO cron_jobs (job_name, status) 
        VALUES (:job_name, 'pending')
    ");

    $count = 0;
    foreach ($jobs as $jobName => $jobConfig) {
        $stmt->execute(['job_name' => $jobName]);
        $count++;
    }

    logMessage("Initialized $count jobs in database");
}

// Get the next run time for a job
function getNextRunTime($lastRun, $interval)
{
    if ($lastRun === null) {
        return date('Y-m-d H:i:s');
    }

    $nextRun = strtotime($lastRun) + ($interval * 60);
    return date('Y-m-d H:i:s', $nextRun);
}

// Update job status
function updateJobStatus($pdo, $jobName, $status, $lastRun = null)
{
    global $jobs;

    $nextRun = null;
    if ($lastRun !== null) {
        $nextRun = getNextRunTime($lastRun, $jobs[$jobName]['interval']);
    }

    $stmt = $pdo->prepare("
        UPDATE cron_jobs 
        SET status = :status, 
            last_run = COALESCE(:last_run, last_run),
            next_run = COALESCE(:next_run, next_run)
        WHERE job_name = :job_name
    ");

    $stmt->execute([
        'status' => $status,
        'last_run' => $lastRun,
        'next_run' => $nextRun,
        'job_name' => $jobName
    ]);

    logMessage("Updated job '$jobName' status to '$status', last_run: " . ($lastRun ?? 'unchanged') . ", next_run: " . ($nextRun ?? 'unchanged'));
}

// Execute a job
function executeJob($pdo, $jobName, $jobConfig)
{
    $jobFile = BASE_PATH . '/jobs/' . $jobConfig['file'];

    logMessage("Attempting to execute job: $jobName ({$jobConfig['description']})");

    if (!file_exists($jobFile)) {
        $errorMsg = "Job file not found: $jobFile";
        logMessage($errorMsg, 'ERROR');
        updateJobStatus($pdo, $jobName, 'error');
        return false;
    }

    try {
        // Update status to running
        logMessage("Starting job: $jobName");
        updateJobStatus($pdo, $jobName, 'running', date('Y-m-d H:i:s'));

        // Execute the job
        $startTime = microtime(true);
        require_once $jobFile;
        $endTime = microtime(true);
        $executionTime = round($endTime - $startTime, 2);

        // Update status to completed
        logMessage("Job '$jobName' completed successfully in {$executionTime} seconds");
        updateJobStatus($pdo, $jobName, 'completed', date('Y-m-d H:i:s'));
        return true;
    } catch (Exception $e) {
        $errorMsg = "Error executing job $jobName: " . $e->getMessage();
        logMessage($errorMsg, 'ERROR');
        updateJobStatus($pdo, $jobName, 'error', date('Y-m-d H:i:s'));
        return false;
    }
}

// Main execution
try {
    logMessage("Cron manager started");

    // Initialize jobs in the database
    initializeJobs($pdo);

    // Get current jobs status
    $stmt = $pdo->query("SELECT * FROM cron_jobs");
    $jobStatuses = $stmt->fetchAll(PDO::FETCH_ASSOC);

    logMessage("Retrieved " . count($jobStatuses) . " job statuses from database");

    $jobStatusMap = [];
    foreach ($jobStatuses as $status) {
        $jobStatusMap[$status['job_name']] = $status;
    }

    // Check and execute each job if it's due
    $jobsExecuted = 0;
    $jobsSkipped = 0;

    foreach ($jobs as $jobName => $jobConfig) {
        if (!$jobConfig['enabled']) {
            logMessage("Job '$jobName' is disabled, skipping");
            $jobsSkipped++;
            continue;
        }

        $status = $jobStatusMap[$jobName] ?? null;
        $now = time();

        // Log job status
        if ($status) {
            $lastRun = $status['last_run'] ? date('Y-m-d H:i:s', strtotime($status['last_run'])) : 'never';
            $nextRun = $status['next_run'] ? date('Y-m-d H:i:s', strtotime($status['next_run'])) : 'unknown';
            logMessage("Job '$jobName' - Last run: $lastRun, Next run: $nextRun, Current status: {$status['status']}");
        } else {
            logMessage("Job '$jobName' has never run before");
        }

        // If job has never run or it's time to run it again
        if (
            $status === null ||
            $status['last_run'] === null ||
            strtotime($status['next_run']) <= $now
        ) {
            // Execute the job
            $result = executeJob($pdo, $jobName, $jobConfig);
            if ($result) {
                $jobsExecuted++;
            }
        } else {
            $timeUntilNextRun = strtotime($status['next_run']) - $now;
            $minutesUntilNextRun = round($timeUntilNextRun / 60);
            logMessage("Job '$jobName' is not due yet. Will run in approximately $minutesUntilNextRun minutes");
            $jobsSkipped++;
        }
    }

    logMessage("Cron manager completed. Executed: $jobsExecuted jobs, Skipped: $jobsSkipped jobs");
    echo "Cron manager executed successfully at " . date('Y-m-d H:i:s') . "\n";
} catch (Exception $e) {
    $errorMsg = "Cron manager error: " . $e->getMessage();
    logMessage($errorMsg, 'ERROR');
    echo "Error: " . $e->getMessage() . "\n";
}
