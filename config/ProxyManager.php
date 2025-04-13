<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);


class ProxyManager
{
    private const PROXY_FILE = __DIR__ . '/proxies.json';
    private const VALID_PROXY_TYPES = ['http', 'https', 'socks4', 'socks5'];
    private const MAX_FAILED_ATTEMPTS = 5;
    private $proxies = [];
    private $proxyIndex = [];

    public function __construct()
    {
        $this->loadProxies();
        $this->buildProxyIndex();
    }

    private function loadProxies(): void
    {
        if (file_exists(self::PROXY_FILE)) {
            $content = file_get_contents(self::PROXY_FILE);
            $this->proxies = json_decode($content, true) ?: [];
        } else {
            // Initialize with empty array if file doesn't exist
            $this->proxies = [];
            $this->saveProxies();
        }
    }

    private function saveProxies(): void
    {
        if (file_put_contents(self::PROXY_FILE, json_encode($this->proxies, JSON_PRETTY_PRINT)) === false) {
            error_log("Failed to write to " . self::PROXY_FILE);
            echo "Failed to write to " . self::PROXY_FILE;
            die('stopped');
        }
    }

    /**
     * Generate a unique ID for a proxy
     * 
     * @return string A unique ID
     */
    private function generateUniqueId(): string
    {
        return uniqid('proxy_', true);
    }

    /**
     * Build an efficient index of proxies for quick lookups
     */
    private function buildProxyIndex(): void
    {
        $this->proxyIndex = [];
        foreach ($this->proxies as $proxy) {
            $key = $this->generateProxyKey($proxy);
            if (!isset($this->proxyIndex[$key])) {
                $this->proxyIndex[$key] = [];
            }
            $this->proxyIndex[$key][] = $proxy['id'];
        }
    }

    /**
     * Generate a unique key for proxy indexing
     */
    private function generateProxyKey(array $proxy): string
    {
        return sprintf(
            '%s:%d:%s:%s',
            strtolower($proxy['ip']),
            $proxy['port'],
            strtolower($proxy['type'] ?? 'http'),
            $proxy['is_free'] ? '1' : '0'
        );
    }

    /**
     * Check if a proxy exists with the given parameters
     */
    public function proxyExists(string $ip, int $port, string $type = 'http', bool $isFree = true): bool
    {
        $key = sprintf(
            '%s:%d:%s:%s',
            strtolower($ip),
            $port,
            strtolower($type),
            $isFree ? '1' : '0'
        );
        return isset($this->proxyIndex[$key]) && !empty($this->proxyIndex[$key]);
    }

    /**
     * Add a proxy with duplicate checking
     * 
     * @param string $ip The IP address or hostname of the proxy
     * @param int $port The port number
     * @param string $type The proxy type (http, https, socks4, socks5)
     * @param string $username Optional username for authentication
     * @param string $password Optional password for authentication
     * @param bool $isFree Whether this is a free proxy (true) or paid proxy (false)
     * @param bool $auto_added Whether this proxy was automatically added (true) or manually added (false)
     * @return string|null The unique ID of the added proxy, or null if it already exists
     */
    public function addProxyIfNotExists(string $ip, int $port, string $type = 'http', string $username = '', string $password = '', bool $isFree = false, bool $auto_added = false): ?string
    {
        // If it's a free proxy, check for duplicates
        if ($isFree && $this->proxyExists($ip, $port, $type, true)) {
            return null;
        }

        // Add the proxy
        $id = $this->addProxy($ip, $port, $type, $username, $password, $isFree, $auto_added);

        // Update the index
        $key = sprintf(
            '%s:%d:%s:%s',
            strtolower($ip),
            $port,
            strtolower($type),
            $isFree ? '1' : '0'
        );
        if (!isset($this->proxyIndex[$key])) {
            $this->proxyIndex[$key] = [];
        }
        $this->proxyIndex[$key][] = $id;

        return $id;
    }

    /**
     * Add a new proxy to the pool
     * 
     * @param string $ip The IP address or hostname of the proxy
     * @param int $port The port number
     * @param string $type The proxy type (http, https, socks4, socks5)
     * @param string $username Optional username for authentication
     * @param string $password Optional password for authentication
     * @param bool $is_free Whether this is a free proxy (true) or paid proxy (false)
     * @param bool $auto_added Whether this proxy was automatically added (true) or manually added (false)
     * @throws InvalidArgumentException If the proxy type is invalid
     * @return string The unique ID of the added proxy
     */
    public function addProxy(string $ip, int $port, string $type = 'http', string $username = '', string $password = '', bool $is_free = false, bool $auto_added = false): string
    {
        if (!in_array($type, self::VALID_PROXY_TYPES)) {
            throw new InvalidArgumentException("Invalid proxy type. Must be one of: " . implode(', ', self::VALID_PROXY_TYPES));
        }

        $id = $this->generateUniqueId();
        $this->proxies[] = [
            'id' => $id,
            'ip' => $ip,
            'port' => $port,
            'type' => $type,
            'username' => $username,
            'password' => $password,
            'is_free' => $is_free,
            'usage' => [],
            'last_used' => null,
            'failed_attempts' => 0,
            'last_failed' => null,
            'auto_added' => $auto_added // Flag to indicate if proxy was added automatically
        ];
        $this->saveProxies();
        return $id;
    }

    /**
     * Add a proxy that was automatically scraped
     * 
     * @param string $ip The IP address or hostname of the proxy
     * @param int $port The port number
     * @param string $type The proxy type (http, https, socks4, socks5)
     * @param string $username Optional username for authentication
     * @param string $password Optional password for authentication
     * @param bool $is_free Whether this is a free proxy (true) or paid proxy (false)
     * @throws InvalidArgumentException If the proxy type is invalid
     * @return string The unique ID of the added proxy
     */
    public function addAutoScrapedProxy(string $ip, int $port, string $type = 'http', string $username = '', string $password = '', bool $is_free = false): string
    {
        if (!in_array($type, self::VALID_PROXY_TYPES)) {
            throw new InvalidArgumentException("Invalid proxy type. Must be one of: " . implode(', ', self::VALID_PROXY_TYPES));
        }

        $id = $this->generateUniqueId();
        $this->proxies[] = [
            'id' => $id,
            'ip' => $ip,
            'port' => $port,
            'type' => $type,
            'username' => $username,
            'password' => $password,
            'is_free' => $is_free,
            'usage' => [],
            'last_used' => null,
            'failed_attempts' => 0,
            'last_failed' => null,
            'auto_added' => true // Flag to indicate this proxy was added automatically
        ];
        $this->saveProxies();
        return $id;
    }

    /**
     * Record a failed attempt for a proxy
     * 
     * @param string $id The unique ID of the proxy
     * @return bool True if the proxy was removed due to too many failures, false otherwise
     */
    public function recordFailedAttempt(string $id): bool
    {
        foreach ($this->proxies as $key => $proxy) {
            if ($proxy['id'] === $id) {
                // Only track failures for free proxies
                if ($proxy['is_free']) {
                    $this->proxies[$key]['failed_attempts']++;
                    $this->proxies[$key]['last_failed'] = date('Y-m-d H:i:s');

                    // If a free proxy fails too many times, remove it
                    if ($this->proxies[$key]['failed_attempts'] >= self::MAX_FAILED_ATTEMPTS) {
                        error_log("Removing free proxy {$proxy['ip']}:{$proxy['port']} after {$this->proxies[$key]['failed_attempts']} failed attempts");
                        unset($this->proxies[$key]);
                        $this->proxies = array_values($this->proxies); // Reindex array
                        $this->saveProxies();
                        return true;
                    }
                }
                $this->saveProxies();
                return false;
            }
        }
        return false;
    }

    /**
     * Reset failed attempts for a proxy after a successful use
     * 
     * @param string $id The unique ID of the proxy
     */
    public function resetFailedAttempts(string $id): void
    {
        foreach ($this->proxies as $key => $proxy) {
            if ($proxy['id'] === $id) {
                $this->proxies[$key]['failed_attempts'] = 0;
                $this->saveProxies();
                break;
            }
        }
    }

    /**
     * Remove a proxy by its unique ID
     * 
     * @param string $id The unique ID of the proxy to remove
     * @return bool True if the proxy was removed, false otherwise
     */
    public function removeProxyById(string $id): bool
    {
        $initialCount = count($this->proxies);

        // Find and remove the proxy in one pass
        foreach ($this->proxies as $key => $proxy) {
            if ($proxy['id'] === $id) {
                // Remove from the index first
                $indexKey = sprintf(
                    '%s:%d:%s:%s',
                    strtolower($proxy['ip']),
                    $proxy['port'],
                    strtolower($proxy['type'] ?? 'http'),
                    $proxy['is_free'] ? '1' : '0'
                );

                // Remove from proxies array
                unset($this->proxies[$key]);

                // Remove from index if it exists
                if (isset($this->proxyIndex[$indexKey])) {
                    unset($this->proxyIndex[$indexKey]);
                }

                // Reindex the array
                $this->proxies = array_values($this->proxies);

                // Save changes
                $this->saveProxies();

                return true;
            }
        }

        return false;
    }

    /**
     * Get a proxy by its unique ID
     * 
     * @param string $id The unique ID of the proxy
     * @return array|null The proxy data or null if not found
     */
    public function getProxyById(string $id): ?array
    {
        foreach ($this->proxies as $proxy) {
            if ($proxy['id'] === $id) {
                return $proxy;
            }
        }
        return null;
    }

    /**
     * Get all proxies with the same IP and port
     * 
     * @param string $ip The IP address
     * @param int $port The port number
     * @return array Array of proxies with matching IP and port
     */
    public function getProxiesByIpPort(string $ip, int $port): array
    {
        return array_filter($this->proxies, function ($proxy) use ($ip, $port) {
            return $proxy['ip'] === $ip && $proxy['port'] === $port;
        });
    }

    /**
     * Get a list of proxies suitable for scraping
     */
    public function getProxiesForScraping(): array
    {
        // First try to get proxies with fewer failed attempts
        return array_filter($this->proxies, function ($proxy) {
            return ($proxy['failed_attempts'] ?? 0) < 3;
        });
    }

    /**
     * Get a proxy for scraping, prioritizing free proxies
     * 
     * @return array|null A proxy to use for scraping, or null if none available
     */
    public function getProxyForScraping(): ?array
    {
        // First try to get a free proxy with fewer failed attempts
        $freeProxies = array_filter($this->proxies, function ($proxy) {
            return $proxy['is_free'] && $proxy['failed_attempts'] < 3;
        });

        if (!empty($freeProxies)) {
            // Sort by failed attempts (ascending)
            usort($freeProxies, function ($a, $b) {
                return $a['failed_attempts'] <=> $b['failed_attempts'];
            });
            return $freeProxies[0];
        }

        // If no suitable free proxies, try paid proxies
        $paidProxies = array_filter($this->proxies, function ($proxy) {
            return !$proxy['is_free'] && $proxy['failed_attempts'] < 3;
        });

        if (!empty($paidProxies)) {
            // Sort by failed attempts (ascending)
            usort($paidProxies, function ($a, $b) {
                return $a['failed_attempts'] <=> $b['failed_attempts'];
            });
            return $paidProxies[0];
        }

        // If all proxies have too many failures, just return the first one
        if (!empty($this->proxies)) {
            return $this->proxies[0];
        }

        return null;
    }

    public function getLeastUsedProxy(string $baseUrl, array $excludeProxies = []): ?array
    {
        // Check if there are any proxies in the pool
        if (empty($this->proxies)) {
            error_log("No proxies available in the pool for baseUrl: $baseUrl");
            return null;
        }

        // Initialize usage count for this baseUrl for all proxies first
        foreach ($this->proxies as &$proxy) {
            if (!isset($proxy['usage'][$baseUrl])) {
                $proxy['usage'][$baseUrl] = 0;
            }
        }
        unset($proxy);

        // Create a unique identifier for each proxy using ip:port and filter out excluded proxies
        $availableProxies = [];
        foreach ($this->proxies as $proxy) {
            $proxyKey = "{$proxy['ip']}:{$proxy['port']}";
            if (in_array($proxyKey, $excludeProxies)) {
                continue; // Skip this proxy if it's in the exclude list
            }
            $availableProxies[] = $proxy;
        }

        // Check if there are any available proxies after exclusion
        if (empty($availableProxies)) {
            error_log("No available proxies after excluding: " . implode(', ', $excludeProxies));
            return null;
        }

        // Find the proxy with the least usage for this baseUrl
        $selectedProxy = null;
        foreach ($availableProxies as $proxy) {
            if ($selectedProxy === null) {
                $selectedProxy = $proxy;
            } else {
                if ($proxy['usage'][$baseUrl] < $selectedProxy['usage'][$baseUrl]) {
                    $selectedProxy = $proxy;
                }
            }
        }

        // Double-check that a proxy was selected
        if ($selectedProxy === null) {
            error_log("Failed to select a proxy for baseUrl: $baseUrl after filtering");
            return null;
        }

        // Increment usage count for the selected proxy in the main proxies array
        $selectedProxyKey = "{$selectedProxy['ip']}:{$selectedProxy['port']}";
        foreach ($this->proxies as &$proxy) {
            $proxyKey = "{$proxy['ip']}:{$proxy['port']}";
            if ($proxyKey === $selectedProxyKey) {
                $proxy['usage'][$baseUrl]++;
                $proxy['last_used'] = date('Y-m-d H:i:s');
                break; // Exit loop once the proxy is found
            }
        }
        unset($proxy);

        // Save the updated proxies to the file
        $this->saveProxies();

        return $selectedProxy;
    }

    public function removeProxy(string $ip, int $port): bool
    {

        $initialCount = count($this->proxies);
        $ip = strtolower(trim($ip)); // Normalize the IP for comparison
        $portStr = (string)$port; // Convert the port to a string for comparison

        // Log the attempt to remove the proxy
        error_log("Attempting to remove proxy: $ip:$portStr");

        // Filter out the proxy with matching ip and port
        $this->proxies = array_filter($this->proxies, function ($proxy) use ($ip, $portStr) {
            $proxyIp = strtolower(trim($proxy['ip']));
            $proxyPort = (string)$proxy['port']; // Ensure port is a string for comparison
            $match = $proxyIp === $ip && $proxyPort === $portStr;
            if ($match) {
                error_log("Found matching proxy to remove: $proxyIp:$proxyPort");
            }
            return !$match;
        });

        // Reindex the array
        $this->proxies = array_values($this->proxies);

        // Check if a proxy was removed
        $wasRemoved = count($this->proxies) < $initialCount;
        if (!$wasRemoved) {
            error_log("No proxy found with IP: $ip and Port: $portStr");
        }

        // Save the updated proxies to the file
        try {
            $this->saveProxies();
            error_log("Successfully saved proxies after removing $ip:$portStr");
        } catch (Exception $e) {
            error_log("Failed to save proxies after removing $ip:$portStr: " . $e->getMessage());
            // Optionally, you can throw an exception or return false here
            return false;
        }

        return $wasRemoved;
    }

    public function getProxyType(string $ip, int $port): ?string
    {
        foreach ($this->proxies as $proxy) {
            if ($proxy['ip'] === $ip && $proxy['port'] === $port) {
                return $proxy['type'] ?? 'http';
            }
        }
        return null;
    }

    /**
     * Get a paid proxy from the pool
     * 
     * @return array|null The proxy data or null if no paid proxy is found
     */
    public function getPaidProxy(): ?array
    {
        foreach ($this->proxies as $proxy) {
            if (empty($proxy['is_free'])) {
                return $proxy;
            }
        }
        return null;
    }

    /**
     * Get all free proxies from the pool
     * 
     * @return array Array of free proxies
     */
    public function getFreeProxies(): array
    {
        return array_filter($this->proxies, function ($proxy) {
            return !empty($proxy['is_free']);
        });
    }

    /**
     * Get all automatically added free proxies from the pool
     * 
     * @return array Array of automatically added free proxies
     */
    public function getAutoAddedFreeProxies(): array
    {
        return array_filter($this->proxies, function ($proxy) {
            return !empty($proxy['is_free']) && !empty($proxy['auto_added']);
        });
    }

    /**
     * Get all manually added free proxies from the pool
     * 
     * @return array Array of manually added free proxies
     */
    public function getManuallyAddedFreeProxies(): array
    {
        return array_filter($this->proxies, function ($proxy) {
            return !empty($proxy['is_free']) && empty($proxy['auto_added']);
        });
    }

    /**
     * Update free proxies based on newly scraped ones
     * This method will:
     * 1. Get all automatically added free proxies
     * 2. Remove those that are not in the newly scraped list
     * 3. Add new free proxies from the scraped list
     * 
     * @param array $newFreeProxies Array of newly scraped free proxies, each with 'ip', 'port', 'type', 'username', 'password'
     * @return array Array of removed proxy IDs
     */
    public function updateAutoAddedFreeProxies(array $newFreeProxies): array
    {
        // Get current auto-added free proxies
        $currentAutoAddedFreeProxies = $this->getAutoAddedFreeProxies();

        // Create a map of current auto-added free proxies for easy lookup
        $currentAutoAddedMap = [];
        foreach ($currentAutoAddedFreeProxies as $proxy) {
            $key = $this->generateProxyKey($proxy);
            $currentAutoAddedMap[$key] = $proxy;
        }

        // Create a map of new free proxies for easy lookup
        $newFreeProxiesMap = [];
        foreach ($newFreeProxies as $proxy) {
            $key = sprintf(
                '%s:%d:%s:1', // 1 indicates it's a free proxy
                strtolower($proxy['ip']),
                $proxy['port'],
                strtolower($proxy['type'] ?? 'http')
            );
            $newFreeProxiesMap[$key] = $proxy;
        }

        // Find proxies to remove (in current but not in new)
        $proxiesToRemove = [];
        foreach ($currentAutoAddedMap as $key => $proxy) {
            if (!isset($newFreeProxiesMap[$key])) {
                $proxiesToRemove[] = $proxy['id'];
            }
        }

        // Remove proxies that are no longer in the new list
        $removedIds = [];
        foreach ($proxiesToRemove as $id) {
            if ($this->removeProxyById($id)) {
                $removedIds[] = $id;
            }
        }

        // Add new proxies that aren't in the current list
        foreach ($newFreeProxies as $proxy) {
            $key = sprintf(
                '%s:%d:%s:1',
                strtolower($proxy['ip']),
                $proxy['port'],
                strtolower($proxy['type'] ?? 'http')
            );

            if (!isset($currentAutoAddedMap[$key])) {
                $this->addAutoScrapedProxy(
                    $proxy['ip'],
                    $proxy['port'],
                    $proxy['type'] ?? 'http',
                    $proxy['username'] ?? '',
                    $proxy['password'] ?? '',
                    true
                );
            }
        }

        return $removedIds;
    }

    /**
     * Get all paid proxies from the pool
     * 
     * @return array Array of paid proxies
     */
    public function getPaidProxies(): array
    {
        return array_filter($this->proxies, function ($proxy) {
            return empty($proxy['is_free']);
        });
    }
}

// Example initialization (you can modify this part)
//$proxyManager = new ProxyManager();
// Uncomment and add your proxies
// $proxyManager->addProxy('192.168.1.1', 8080, 'user', 'pass');
// $proxyManager->addProxy('10.0.0.1', 3128);