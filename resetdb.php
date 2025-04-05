<?php
require_once __DIR__ . '/config/db.php';

try {
    // Create a new PDO connection

    // SQL Queries
    $queries = [
        "DELETE FROM verification_logs;",
        "DELETE FROM verification_errors;",
        "DELETE FROM backlink_verification_helper;",
        "UPDATE campaigns SET last_checked = NULL;",
        "UPDATE backlinks SET last_checked = NULL, status = 'pending';"
    ];

    // Execute each query
    foreach ($queries as $query) {
        $pdo->exec($query);
    }

    echo "Database reset successfully.";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
