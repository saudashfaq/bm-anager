<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

class ProxyScraperValidator
{
    private const SOURCES = [
        'proxyscrape' => 'https://api.proxyscrape.com/v2/?request=getproxies&protocol=http&timeout=10000&country=all&ssl=all&anonymity=all',
        'freeproxylist' => 'https://free-proxy-list.net/',
        'geonode' => 'https://proxylist.geonode.com/api/proxy-list?limit=100&page=1&sort_by=lastChecked&sort_type=desc&protocols=http%2Chttps',
        'spysone' => 'http://spys.one/free-proxy-list/ALL/'
    ];

    private $proxyManager;
    private $currentProxy;

    public function __construct(ProxyManager $proxyManager)
    {
        $this->proxyManager = $proxyManager;
        error_log("[ProxyScraperValidator] Initialized with ProxyManager");
    }

    public function scrapeAndValidate(): array
    {
        error_log("[ProxyScraperValidator] Starting scrape process");
        $validProxies = [];
        $totalProxies = 0;

        // Get a proxy for scraping
        $this->currentProxy = $this->proxyManager->getProxyForScraping();
        if ($this->currentProxy) {
            error_log("[ProxyScraperValidator] Using proxy for scraping: {$this->currentProxy['ip']}:{$this->currentProxy['port']}");
        } else {
            error_log("[ProxyScraperValidator] No proxy available for scraping, proceeding without proxy");
        }

        foreach (self::SOURCES as $source => $url) {
            error_log("[ProxyScraperValidator] Processing source: $source");
            try {
                $proxies = $this->scrapeSource($url, $source);
                $totalProxies += count($proxies);
                error_log("[ProxyScraperValidator] Found " . count($proxies) . " proxies from $source");

                // Add all proxies to the valid list since we're trusting source statistics
                $validProxies = array_merge($validProxies, $proxies);
            } catch (Exception $e) {
                error_log("[ProxyScraperValidator] Error processing $source: " . $e->getMessage());
            }
        }

        error_log("[ProxyScraperValidator] Scraping completed. Total proxies found: $totalProxies");
        return $validProxies;
    }

    private function scrapeSource(string $url, string $source): array
    {
        error_log("[ProxyScraperValidator] Starting scrape from URL: $url");
        $proxies = [];
        $ch = curl_init();

        try {
            curl_setopt_array($ch, [
                CURLOPT_URL => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 30,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36'
            ]);

            if ($this->currentProxy) {
                error_log("[ProxyScraperValidator] Setting up proxy for request: {$this->currentProxy['ip']}:{$this->currentProxy['port']}");
                $proxyUrl = "{$this->currentProxy['type']}://{$this->currentProxy['ip']}:{$this->currentProxy['port']}";
                curl_setopt($ch, CURLOPT_PROXY, $proxyUrl);

                if (!empty($this->currentProxy['username']) && !empty($this->currentProxy['password'])) {
                    curl_setopt($ch, CURLOPT_PROXYUSERPWD, "{$this->currentProxy['username']}:{$this->currentProxy['password']}");
                }
            }

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

            if ($response === false) {
                $error = curl_error($ch);
                error_log("[ProxyScraperValidator] cURL error for $url: $error");
                if ($this->currentProxy) {
                    $this->proxyManager->recordFailedAttempt($this->currentProxy['id']);
                }
                throw new Exception("Failed to fetch $url: $error");
            }

            if ($httpCode !== 200) {
                error_log("[ProxyScraperValidator] HTTP error for $url: $httpCode");
                if ($this->currentProxy) {
                    $this->proxyManager->recordFailedAttempt($this->currentProxy['id']);
                }
                throw new Exception("HTTP error $httpCode for $url");
            }

            error_log("[ProxyScraperValidator] Successfully fetched content from $url");

            // Reset failed attempts if the request was successful
            if ($this->currentProxy) {
                $this->proxyManager->resetFailedAttempts($this->currentProxy['id']);
            }

            switch ($source) {
                case 'proxyscrape':
                    $proxies = $this->parseProxyScrape($response);
                    break;
                case 'freeproxylist':
                    $proxies = $this->parseFreeProxyList($response);
                    break;
                case 'geonode':
                    $proxies = $this->parseGeonode($response);
                    break;
                case 'spysone':
                    $proxies = $this->parseSpysOne($response);
                    break;
            }

            error_log("[ProxyScraperValidator] Parsed " . count($proxies) . " proxies from $source");
        } catch (Exception $e) {
            error_log("[ProxyScraperValidator] Error scraping $source: " . $e->getMessage());
            throw $e;
        } finally {
            curl_close($ch);
        }

        return $proxies;
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
                    'is_free' => true
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

                // Only include proxies with good statistics
                if ($uptime >= 80 && $latency <= 1000 && $successRate >= 60) {
                    $proxies[] = [
                        'ip' => $ip,
                        'port' => $port,
                        'type' => 'http',
                        'is_free' => true,
                        'uptime' => $uptime,
                        'latency' => $latency,
                        'success_rate' => $successRate
                    ];
                }
            }
        }

        error_log("[ProxyScraperValidator] Found " . count($proxies) . " proxies from FreeProxyList with good statistics");
        return $proxies;
    }

    private function parseGeonode(string $content): array
    {
        error_log("[ProxyScraperValidator] Parsing Geonode content");
        $proxies = [];
        $data = json_decode($content, true);

        if (isset($data['data']) && is_array($data['data'])) {
            foreach ($data['data'] as $proxy) {
                if (isset($proxy['ip'], $proxy['port'])) {
                    // Check if the proxy has good statistics
                    $uptime = $proxy['uptime'] ?? 0;
                    $latency = $proxy['latency'] ?? 9999;
                    $successRate = $proxy['success_rate'] ?? 0;

                    // Only include proxies with good statistics
                    if ($uptime >= 80 && $latency <= 1000 && $successRate >= 60) {
                        $proxies[] = [
                            'ip' => $proxy['ip'],
                            'port' => (int)$proxy['port'],
                            'type' => strtolower($proxy['protocols'][0] ?? 'http'),
                            'is_free' => true,
                            'uptime' => $uptime,
                            'latency' => $latency,
                            'success_rate' => $successRate
                        ];
                    }
                }
            }
        }

        error_log("[ProxyScraperValidator] Found " . count($proxies) . " proxies from Geonode with good statistics");
        return $proxies;
    }

    private function parseSpysOne(string $content): array
    {
        error_log("[ProxyScraperValidator] Parsing SpysOne content");
        $proxies = [];

        if (preg_match_all('/<tr[^>]*onmouseover[^>]*>.*?<td[^>]*>(\d+\.\d+\.\d+\.\d+)<\/td>.*?<td[^>]*>(\d+)<\/td>.*?<td[^>]*>(\d+)<\/td>.*?<td[^>]*>(\d+)<\/td>.*?<\/tr>/s', $content, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $ip = $match[1];
                $port = (int)$match[2];
                $uptime = (int)$match[3];
                $latency = (int)$match[4];

                // Only include proxies with good statistics
                if ($uptime >= 80 && $latency <= 1000) {
                    $proxies[] = [
                        'ip' => $ip,
                        'port' => $port,
                        'type' => 'http',
                        'is_free' => true,
                        'uptime' => $uptime,
                        'latency' => $latency
                    ];
                }
            }
        }

        error_log("[ProxyScraperValidator] Found " . count($proxies) . " proxies from SpysOne with good statistics");
        return $proxies;
    }
}

//TODO: if (basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'])) {

require_once __DIR__ . '/../config/ProxyManager.php';
$proxyManager = new ProxyManager();
$proxyValidator = new ProxyScraperValidator($proxyManager);
$validProxies = $proxyValidator->scrapeAndValidate();

// Add each valid proxy to the ProxyManager
foreach ($validProxies as $proxy) {
    try {
        error_log("[ProxyScraperValidator] Adding proxy: {$proxy['ip']}:{$proxy['port']}");
        $proxyManager->addProxy(
            $proxy['ip'],
            $proxy['port'],
            $proxy['type'] ?? 'http',
            '', // username
            '', // password
            true // is_free
        );
    } catch (Exception $e) {
        error_log("[ProxyScraperValidator] Failed to add proxy {$proxy['ip']}:{$proxy['port']}: " . $e->getMessage());
    }
}

error_log("[ProxyScraperValidator] Added " . count($validProxies) . " proxies to the proxy pool");
//TODO: }
