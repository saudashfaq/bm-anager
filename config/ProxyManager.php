<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);


class ProxyManager
{
    private const PROXY_FILE = __DIR__ . '/proxies.json';
    private $proxies = [];

    public function __construct()
    {
        $this->loadProxies();
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
    public function getLeastUsedProxy(string $baseUrl): array
    {
        if (empty($this->proxies)) {
            throw new Exception("No proxies available in the pool");
        }

        // Initialize usage count for this URL if not exists
        foreach ($this->proxies as &$proxy) {
            if (!isset($proxy['usage'][$baseUrl])) {
                $proxy['usage'][$baseUrl] = 0;
            }
        }
        unset($proxy);

        // Find proxy with least usage for this URL
        $selectedProxy = array_reduce($this->proxies, function ($carry, $proxy) use ($baseUrl) {
            if ($carry === null) {
                return $proxy;
            }
            return $proxy['usage'][$baseUrl] < $carry['usage'][$baseUrl] ? $proxy : $carry;
        });

        // Increment usage count
        foreach ($this->proxies as &$proxy) {
            if ($proxy['ip'] === $selectedProxy['ip'] && $proxy['port'] === $selectedProxy['port']) {
                $proxy['usage'][$baseUrl]++;
                $proxy['last_used'] = date('Y-m-d H:i:s');
            }
        }
        unset($proxy);

        $this->saveProxies();
        return $selectedProxy;
    }

    // Method to add proxies manually or through initialization
    public function addProxy(string $ip, int $port, string $username = '', string $password = ''): void
    {
        $this->proxies[] = [
            'ip' => $ip,
            'port' => $port,
            'username' => $username,
            'password' => $password,
            'usage' => [],
            'last_used' => null
        ];
        print_r($this->proxies);
        $this->saveProxies();
    }

    public function removeProxy(string $ip, int $port): bool
    {
        $initialCount = count($this->proxies);
        $this->proxies = array_filter($this->proxies, function ($proxy) use ($ip, $port) {
            return !($proxy['ip'] === $ip && $proxy['port'] === $port);
        });
        $this->proxies = array_values($this->proxies); // Reindex array
        $this->saveProxies();
        return count($this->proxies) < $initialCount;
    }
}

// Example initialization (you can modify this part)
//$proxyManager = new ProxyManager();
// Uncomment and add your proxies
// $proxyManager->addProxy('192.168.1.1', 8080, 'user', 'pass');
// $proxyManager->addProxy('10.0.0.1', 3128);