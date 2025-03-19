<?php
// File: ValidationHelper.php

class ValidationHelper
{
    private $errors = [];
    private $data = [];

    /**
     * Constructor to initialize data
     * @param array $data Form data to validate
     */
    public function __construct(array $data = [])
    {
        $this->data = $data;
    }

    /**
     * Get all validation errors
     * @return array
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * Check if validation passed
     * @return bool
     */
    public function passes(): bool
    {
        return empty($this->errors);
    }

    /**
     * Validate required fields
     * @param string $field
     * @param string $customMessage
     * @return self
     */
    public function required(string $field, ?string $customMessage = null): self
    {
        if (!isset($this->data[$field]) || empty($this->data[$field]) || empty(trim($this->data[$field]))) {
            $message = $customMessage ?? "The {$field} field is required";
            $this->errors[$field][] = $message;
        }
        return $this;
    }

    /**
     * Validate email format
     * @param string $field
     * @param string $customMessage
     * @return self
     */
    public function email(string $field, ?string $customMessage = null): self
    {
        if (isset($this->data[$field]) && !empty($this->data[$field])) {
            if (!filter_var($this->data[$field], FILTER_VALIDATE_EMAIL)) {
                $message = $customMessage ?? "The {$field} must be a valid email address";
                $this->errors[$field][] = $message;
            }
        }
        return $this;
    }

    /**
     * Validate minimum length
     * @param string $field
     * @param int $length
     * @param string $customMessage
     * @return self
     */
    public function minLength(string $field, int $length, ?string $customMessage = null): self
    {
        if (isset($this->data[$field]) && !empty($this->data[$field])) {
            if (strlen($this->data[$field]) < $length) {
                $message = $customMessage ?? "The {$field} must be at least {$length} characters";
                $this->errors[$field][] = $message;
            }
        }
        return $this;
    }

    /**
     * Validate maximum length
     * @param string $field
     * @param int $length
     * @param string $customMessage
     * @return self
     */
    public function maxLength(string $field, int $length, ?string $customMessage = null): self
    {
        if (isset($this->data[$field]) && !empty($this->data[$field])) {
            if (strlen($this->data[$field]) > $length) {
                $message = $customMessage ?? "The {$field} must not exceed {$length} characters";
                $this->errors[$field][] = $message;
            }
        }
        return $this;
    }

    /**
     * Validate numeric value
     * @param string $field
     * @param string $customMessage
     * @return self
     */
    public function numeric(string $field, ?string $customMessage = null): self
    {
        if (isset($this->data[$field]) && !empty($this->data[$field])) {
            if (!is_numeric($this->data[$field])) {
                $message = $customMessage ?? "The {$field} must be a number";
                $this->errors[$field][] = $message;
            }
        }
        return $this;
    }

    /**
     * Validate if value is integer
     * @param string $field
     * @param string $customMessage
     * @return self
     */
    public function integer(string $field, ?string $customMessage = null): self
    {
        if (isset($this->data[$field]) && !empty($this->data[$field])) {
            if (!filter_var($this->data[$field], FILTER_VALIDATE_INT)) {
                $message = $customMessage ?? "The {$field} must be an integer";
                $this->errors[$field][] = $message;
            }
        }
        return $this;
    }

    /**
     * Validate if value matches a regular expression
     * @param string $field
     * @param string $pattern
     * @param string $customMessage
     * @return self
     */
    public function regex(string $field, string $pattern, ?string $customMessage = null): self
    {
        if (isset($this->data[$field]) && !empty($this->data[$field])) {
            if (!preg_match($pattern, $this->data[$field])) {
                $message = $customMessage ?? "The {$field} format is invalid";
                $this->errors[$field][] = $message;
            }
        }
        return $this;
    }

    /**
     * Validate if value is in array of allowed values
     * @param string $field
     * @param array $allowedValues
     * @param string $customMessage
     * @return self
     */
    public function in(string $field, array $allowedValues, ?string $customMessage = null): self
    {
        if (isset($this->data[$field]) && !empty($this->data[$field])) {
            if (!in_array($this->data[$field], $allowedValues)) {
                $message = $customMessage ?? "The selected {$field} is invalid";
                $this->errors[$field][] = $message;
            }
        }
        return $this;
    }

    /**
     * Validate if two fields match
     * @param string $field
     * @param string $fieldToMatch
     * @param string $customMessage
     * @return self
     */
    public function matches(string $field, string $fieldToMatch, ?string $customMessage = null): self
    {
        if (isset($this->data[$field]) && isset($this->data[$fieldToMatch])) {
            if ($this->data[$field] !== $this->data[$fieldToMatch]) {
                $message = $customMessage ?? "The {$field} does not match {$fieldToMatch}";
                $this->errors[$field][] = $message;
            }
        }
        return $this;
    }

    /**
     * Validate URL format
     * @param string $field
     * @param string $customMessage
     * @return self
     */
    public function url(string $field, ?string $customMessage = null): self
    {
        if (isset($this->data[$field]) && !empty($this->data[$field])) {
            $url = trim($this->data[$field]);

            // Step 1: Normalize the URL by adding a scheme if missing (for validation purposes)
            $normalizedUrl = $url;
            if (!preg_match('#^https?://#i', $url)) {
                // If no scheme is provided, prepend http:// to validate (we'll remove it later)
                $normalizedUrl = 'http://' . $url;
            }

            // Step 2: Validate the URL structure using filter_var
            if (!filter_var($normalizedUrl, FILTER_VALIDATE_URL)) {
                $message = $customMessage ?? "The {$field} must be a valid URL";
                $this->errors[$field][] = $message;
                return $this;
            }

            // Step 3: Parse the URL to check components
            $parsedUrl = parse_url($normalizedUrl);

            // Check for host (must exist and contain a dot for a valid domain)
            if (!isset($parsedUrl['host']) || strpos($parsedUrl['host'], '.') === false) {
                $message = $customMessage ?? "The {$field} must include a valid domain with a TLD";
                $this->errors[$field][] = $message;
                return $this;
            }

            // Extract the TLD and validate it
            $host = $parsedUrl['host'];
            $hostParts = explode('.', $host);
            $tld = end($hostParts);

            // Basic TLD length check (e.g., at least 2 characters)
            if (strlen($tld) < 2) {
                $message = $customMessage ?? "The {$field} must have a valid TLD";
                $this->errors[$field][] = $message;
                return $this;
            }

            // Step 4: Normalize the URL for storage (remove scheme and www.)
            $cleanedHost = preg_replace('#^www\.#i', '', $host); // Remove www.
            $path = isset($parsedUrl['path']) ? $parsedUrl['path'] : '';
            $query = isset($parsedUrl['query']) ? '?' . $parsedUrl['query'] : '';
            $fragment = isset($parsedUrl['fragment']) ? '#' . $parsedUrl['fragment'] : '';

            // Construct the cleaned URL (host + path + query + fragment)
            $cleanedUrl = $cleanedHost . $path . $query . $fragment;

            // Update the data with the cleaned URL
            $this->data[$field] = $cleanedUrl;
        }

        return $this;
    }

    /**
     * Validate if URL matches base URL (checks if URL starts with base URL)
     * @param string $field
     * @param string $baseField
     * @param string $customMessage
     * @return self
     */
    public function matchesBaseUrl(string $field, string $baseField, ?string $customMessage = null): self
    {
        if (isset($this->data[$field]) && isset($this->data[$baseField])) {
            $url = $this->data[$field];
            $baseUrl = $this->data[$baseField];

            // Ensure both are valid URLs first
            if (!filter_var($url, FILTER_VALIDATE_URL) || !filter_var($baseUrl, FILTER_VALIDATE_URL)) {
                $message = $customMessage ?? "The {$field} or {$baseField} is not a valid URL";
                $this->errors[$field][] = $message;
                return $this;
            }

            // Check if URL starts with base URL
            if (strpos($url, $baseUrl) !== 0) {
                $message = $customMessage ?? "The {$field} must start with the base URL in {$baseField}";
                $this->errors[$field][] = $message;
            }
        }
        return $this;
    }

    /**
     * Sanitize input data
     * @param string $field
     * @return string
     */
    public function sanitize(string $field): string
    {
        if (isset($this->data[$field])) {
            $value = $this->data[$field];
            return htmlspecialchars($value !== '' ? trim($value) : $value, ENT_QUOTES, 'UTF-8');
        }
        return '';
    }
}


// Example usage
/*
$validator = new ValidationHelper($_POST);

$validator->required('website_url')
    ->url('website_url', 'Please enter a valid URL')
    ->required('base_url')
    ->url('base_url')
    ->matchesBaseUrl('website_url', 'base_url', 'Website URL must start with the base URL');

if ($validator->passes()) {
    echo "URL validation passed!";
} else {
    $errors = $validator->getErrors();
    foreach ($errors as $field => $messages) {
        foreach ($messages as $message) {
            echo "<p>Error in {$field}: {$message}</p>";
        }
    }
}
    */