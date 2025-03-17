<?php

function generateCSRFToken()
{
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCSRFToken($token)
{
    if (!isset($_SESSION['csrf_token']) || !isset($token)) {
        return false;
    }
    return $_SESSION['csrf_token'] === $token;
}

function getCSRFToken(): string
{
    return $_SESSION['csrf_token'] ?? generateCSRFToken();
}

/*

function generateCSRFToken()
{
    // Initialize history if not set
    if (!isset($_SESSION['csrf_token_history'])) {
        $_SESSION['csrf_token_history'] = [];
    }

    // Move the current token to history before generating a new one
    if (isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token_history'][] = $_SESSION['csrf_token']; // Add to history
        if (count($_SESSION['csrf_token_history']) > 3) { // Keep only the last 3 tokens
            array_shift($_SESSION['csrf_token_history']);
        }
    }

    // Generate a new token
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

    return $_SESSION['csrf_token'];
}




function verifyCSRFToken($token)
{
    if (!isset($_SESSION['csrf_token']) || !isset($token)) {
        return false;
    }

    // Allow the token if it matches the current token OR any token in history
    if ($token === $_SESSION['csrf_token'] || in_array($token, $_SESSION['csrf_token_history'])) {
        generateCSRFToken(); // Generate a new token after successful verification
        return true;
    }

    return false;
}

function verifyCSRFToken($token)
{
    if (!isset($_SESSION['csrf_token']) || !isset($token)) {
        return false;
    }

    // Check if the token matches the current or previous CSRF token
    if ($token !== $_SESSION['csrf_token'] && (!isset($_SESSION['csrf_token_old']) || $token !== $_SESSION['csrf_token_old'])) {
        return false;
    }

    // CSRF is valid, update the token for the next request
    generateCSRFToken();

    return true;
}
*/