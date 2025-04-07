<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

class ProxyScraperValidator
{
    private const SOURCES = [
        'proxyscrape' => 'https://api.proxyscrape.com/v2/?request=getproxies&protocol=http&timeout=10000&country=all&ssl=all&anonymity=all',
        'freeproxylist' => 'https://free-proxy-list.net/',
        'geonode' => 'https://proxylist.geonode.com/api/proxy-list?limit=100&page=1&sort_by=lastChecked&sort_type=desc&protocols=http%2Chttps',
        'proxylistdownload' => 'https://www.proxy-list.download/api/v1/get?type=http'
    ];

    private const MAX_RETRIES = 3;

    private $proxyManager;
    private $currentProxy;
    private $statistics = [];

    public function __construct(ProxyManager $proxyManager)
    {
        $this->proxyManager = $proxyManager;
        error_log("[ProxyScraperValidator] Initialized with ProxyManager");
    }

    public function scrapeAndValidate(): array
    {
        error_log("[ProxyScraperValidator] Starting proxy scraping process...");
        $validProxies = [];
        $totalProxies = 0;
        $this->statistics = [];

        $this->currentProxy = $this->proxyManager->getProxyForScraping();
        if ($this->currentProxy) {
            error_log("[ProxyScraperValidator] Using proxy for scraping: {$this->currentProxy['ip']}:{$this->currentProxy['port']}");
        } else {
            error_log("[ProxyScraperValidator] No proxy available for scraping, proceeding without proxy");
        }

        foreach (self::SOURCES as $source => $url) {
            try {
                $proxies = $this->scrapeSource($url, $source);
                $totalProxies += count($proxies);

                $this->statistics[$source] = [
                    'total' => count($proxies),
                    'valid' => 0
                ];

                $validProxies = array_merge($validProxies, $proxies);
            } catch (Exception $e) {
                $this->statistics[$source] = [
                    'total' => 0,
                    'valid' => 0,
                    'error' => $e->getMessage()
                ];
            }
        }

        $processedProxies = $this->processBatch($validProxies);
        $this->displayStatistics();

        return $processedProxies;
    }

    private function scrapeSource(string $url, string $source): array
    {
        $attempts = 0;
        $usedProxies = [];
        $lastError = null;

        while ($attempts < self::MAX_RETRIES) {
            $attempts++;

            $headers = [];
            if ($source === 'geonode') {
                $headers = [
                    'Accept: application/json',
                    'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36'
                ];
            }

            $this->currentProxy = $this->getNextProxy($usedProxies);

            if ($this->currentProxy) {
                error_log("[ProxyScraperValidator] Attempt $attempts for $source using proxy: {$this->currentProxy['ip']}:{$this->currentProxy['port']}");
                $usedProxies[] = $this->currentProxy['id'];
            } else {
                error_log("[ProxyScraperValidator] Attempt $attempts for $source without proxy (no available proxies)");
            }

            $ch = curl_init();

            try {
                curl_setopt_array($ch, [
                    CURLOPT_URL => $url,
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_TIMEOUT => 30,
                    CURLOPT_FOLLOWLOCATION => true,
                    CURLOPT_SSL_VERIFYPEER => false,
                    CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36',
                    CURLOPT_HTTPHEADER => $headers
                ]);

                if ($this->currentProxy) {
                    $proxyUrl = "{$this->currentProxy['type']}://{$this->currentProxy['ip']}:{$this->currentProxy['port']}";
                    curl_setopt($ch, CURLOPT_PROXY, $proxyUrl);

                    if (!empty($this->currentProxy['username']) && !empty($this->currentProxy['password'])) {
                        curl_setopt($ch, CURLOPT_PROXYUSERPWD, "{$this->currentProxy['username']}:{$this->currentProxy['password']}");
                    }
                }

                if ($source === 'geonode') {
                    $response = curl_exec($ch);
                    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

                    error_log("[ProxyScraperValidator] Geonode HTTP Code: $httpCode");
                    error_log("[ProxyScraperValidator] Geonode Response Length: " . strlen($response));
                } else {
                    $response = curl_exec($ch);
                    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                }

                if ($response === false) {
                    $error = curl_error($ch);
                    if ($this->currentProxy) {
                        $this->proxyManager->recordFailedAttempt($this->currentProxy['id']);
                    }
                    throw new Exception("Failed to fetch $url: $error");
                }

                if ($httpCode !== 200) {
                    if ($this->currentProxy) {
                        $this->proxyManager->recordFailedAttempt($this->currentProxy['id']);
                    }
                    throw new Exception("HTTP error $httpCode for $url");
                }

                if ($this->currentProxy) {
                    $this->proxyManager->resetFailedAttempts($this->currentProxy['id']);
                }

                $proxies = match ($source) {
                    'proxyscrape' => $this->parseProxyScrape($response),
                    'freeproxylist' => $this->parseFreeProxyList($response),
                    'geonode' => $this->parseGeonode($response),
                    'proxylistdownload' => $this->parseProxyListDownload($response),
                    default => []
                };

                error_log("[ProxyScraperValidator] Successfully scraped $source (attempt $attempts): Found " . count($proxies) . " proxies");
                return $proxies;
            } catch (Exception $e) {
                $lastError = $e;
                error_log("[ProxyScraperValidator] Failed to scrape $source (attempt $attempts): " . $e->getMessage());

                if ($attempts >= self::MAX_RETRIES) {
                    throw new Exception("Failed to scrape $source after " . self::MAX_RETRIES . " attempts. Last error: " . $lastError->getMessage());
                }
            } finally {
                curl_close($ch);
            }
        }

        throw new Exception("Failed to scrape $source after " . self::MAX_RETRIES . " attempts. Last error: " . $lastError->getMessage());
    }

    private function parseProxyScrape(string $content): array
    {
        error_log("[ProxyScraperValidator] Parsing ProxyScrape content");
        $proxies = [];
        $lines = explode("\n", trim($content));

        foreach ($lines as $line) {
            $parts = explode(':', trim($line));
            if (count($parts) === 2) {
                $proxies[] = [
                    'ip' => $parts[0],
                    'port' => (int)$parts[1],
                    'type' => 'http',
                    'is_free' => true,
                    'source' => 'proxyscrape'
                ];
            }
        }

        error_log("[ProxyScraperValidator] Found " . count($proxies) . " proxies from ProxyScrape");
        return $proxies;
    }

    private function parseFreeProxyList(string $content): array
    {
        error_log("[ProxyScraperValidator] Parsing FreeProxyList content");
        $proxies = [];

        if (preg_match('/<table[^>]*class="table[^"]*"[^>]*>(.*?)<\/table>/s', $content, $matches)) {
            $table = $matches[1];
            preg_match_all('/<tr[^>]*>.*?<td[^>]*>(\d+\.\d+\.\d+\.\d+)<\/td>.*?<td[^>]*>(\d+)<\/td>.*?<td[^>]*>(\d+)<\/td>.*?<td[^>]*>(\d+)<\/td>.*?<td[^>]*>(\d+)<\/td>.*?<td[^>]*>(\d+)<\/td>.*?<\/tr>/s', $table, $matches, PREG_SET_ORDER);

            foreach ($matches as $match) {
                $ip = $match[1];
                $port = (int)$match[2];
                $uptime = (int)$match[3];
                $latency = (int)$match[4];
                $successRate = (int)$match[5];

                if ($uptime >= 80 && $latency <= 1000 && $successRate >= 60) {
                    $proxies[] = [
                        'ip' => $ip,
                        'port' => $port,
                        'type' => 'http',
                        'is_free' => true,
                        'uptime' => $uptime,
                        'latency' => $latency,
                        'success_rate' => $successRate,
                        'source' => 'freeproxylist'
                    ];
                }
            }
        }

        error_log("[ProxyScraperValidator] Found " . count($proxies) . " proxies from FreeProxyList with good statistics");
        return $proxies;
    }

    private function parseGeonode(string $content): array
    {
        $proxies = [];
        $data = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log("[ProxyScraperValidator] Geonode JSON Error: " . json_last_error_msg());
            return $proxies;
        }

        error_log("[ProxyScraperValidator] Geonode Data Structure: " . print_r($data, true));

        if (isset($data['data']) && is_array($data['data'])) {
            foreach ($data['data'] as $proxy) {
                if (isset($proxy['ip'], $proxy['port'])) {
                    $uptime = $proxy['upTime'] ?? 0;
                    $latency = $proxy['responseTime'] ?? 9999;

                    if ($uptime >= 50 && $latency <= 5000) {
                        $proxies[] = [
                            'ip' => $proxy['ip'],
                            'port' => (int)$proxy['port'],
                            'type' => strtolower($proxy['protocols'][0] ?? 'http'),
                            'is_free' => true,
                            'uptime' => $uptime,
                            'latency' => $latency,
                            'source' => 'geonode'
                        ];
                    }
                }
            }
        } else {
            error_log("[ProxyScraperValidator] Geonode: No 'data' array found in response");
        }

        error_log("[ProxyScraperValidator] Found " . count($proxies) . " proxies from Geonode");
        return $proxies;
    }

    private function parseProxyListDownload(string $content): array
    {
        error_log("[ProxyScraperValidator] Parsing ProxyListDownload content");
        $proxies = [];
        $lines = explode("\n", trim($content));

        foreach ($lines as $line) {
            $parts = explode(':', trim($line));
            if (count($parts) === 2) {
                $proxies[] = [
                    'ip' => $parts[0],
                    'port' => (int)$parts[1],
                    'type' => 'http',
                    'is_free' => true,
                    'source' => 'proxylistdownload'
                ];
            }
        }

        error_log("[ProxyScraperValidator] Found " . count($proxies) . " proxies from ProxyListDownload");
        return $proxies;
    }

    private function displayStatistics(): void
    {
        error_log("\n[ProxyScraperValidator] === Proxy Scraping Results ===");
        error_log(str_repeat('=', 50));
        error_log(sprintf("%-15s | %-10s | %-10s", 'Source', 'Found', 'Added'));

        $totalFound = 0;
        $totalAdded = 0;

        foreach ($this->statistics as $source => $stats) {
            error_log(sprintf(
                "%-15s | %-10d | %-10d",
                $source,
                $stats['total'],
                $stats['valid']
            ));
            $totalFound += $stats['total'];
            $totalAdded += $stats['valid'];

            if (isset($stats['error'])) {
                error_log("  Error: {$stats['error']}");
            }
        }

        error_log(str_repeat('-', 50));
        error_log(sprintf("%-15s | %-10d | %-10d", 'TOTAL', $totalFound, $totalAdded));
        error_log(str_repeat('=', 50) . "\n");
    }

    private function processBatch(array $proxies): array
    {
        $addedProxies = [];
        $batchSize = 100;
        $batches = array_chunk($proxies, $batchSize);

        foreach ($batches as $batch) {
            foreach ($batch as $proxy) {
                try {
                    $id = $this->proxyManager->addProxyIfNotExists(
                        $proxy['ip'],
                        $proxy['port'],
                        $proxy['type'] ?? 'http',
                        '',
                        '',
                        true
                    );

                    if ($id !== null) {
                        $proxy['id'] = $id;
                        $addedProxies[] = $proxy;

                        $sourceKey = $this->findSourceByUrl($proxy['source'] ?? '');
                        if ($sourceKey && isset($this->statistics[$sourceKey])) {
                            $this->statistics[$sourceKey]['valid']++;
                        }
                    }
                } catch (Exception $e) {
                    error_log("[ProxyScraperValidator] Critical error: " . $e->getMessage());
                }
            }
        }

        return $addedProxies;
    }

    private function findSourceByUrl(string $url): ?string
    {
        foreach (self::SOURCES as $key => $sourceUrl) {
            if (strpos($url, $key) !== false) {
                return $key;
            }
        }
        return null;
    }

    private function getNextProxy(array $usedProxyIds): ?array
    {
        $allProxies = $this->proxyManager->getProxiesForScraping();

        foreach ($allProxies as $proxy) {
            if (!in_array($proxy['id'], $usedProxyIds)) {
                return $proxy;
            }
        }

        return null;
    }
}

require_once __DIR__ . '/../config/ProxyManager.php';
$proxyManager = new ProxyManager();
$proxyValidator = new ProxyScraperValidator($proxyManager);
$validProxies = $proxyValidator->scrapeAndValidate();

foreach ($validProxies as $proxy) {
    try {
        if ($proxy['is_free']) {
            $existingProxies = $proxyManager->getProxiesByIpPort($proxy['ip'], $proxy['port']);
            $duplicateFound = false;
            foreach ($existingProxies as $existingProxy) {
                if (
                    $existingProxy['is_free'] &&
                    ($existingProxy['type'] ?? 'http') === ($proxy['type'] ?? 'http')
                ) {
                    $duplicateFound = true;
                    error_log("[ProxyScraperValidator] Skipping duplicate free proxy: {$proxy['ip']}:{$proxy['port']}");
                    break;
                }
            }

            if ($duplicateFound) {
                continue;
            }
        }

        error_log("[ProxyScraperValidator] Adding proxy: {$proxy['ip']}:{$proxy['port']}");
        $proxyManager->addProxy(
            $proxy['ip'],
            $proxy['port'],
            $proxy['type'] ?? 'http',
            '',
            '',
            true
        );
    } catch (Exception $e) {
        error_log("[ProxyScraperValidator] Failed to add proxy {$proxy['ip']}:{$proxy['port']}: " . $e->getMessage());
    }
}

error_log("[ProxyScraperValidator] Added " . count($validProxies) . " proxies to the proxy pool");
