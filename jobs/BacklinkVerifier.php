<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/proxies.json';
require_once __DIR__ . '/../config/ProxyManager.php';

class BacklinkVerifier
{
    private const USER_AGENTS = [
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
        'Mozilla/5.0 (Macintosh; Intel Mac OS X 14_2_1) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.2 Safari/605.1.15',
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:121.0) Gecko/20100101 Firefox/121.0'
    ];
    private const BATCH_SIZE = 5;
    private const TIMEOUT = 30;
    private const MIN_DELAY = 1; // Minimum seconds between requests
    private const MAX_DELAY = 5; // Maximum seconds between requests
    private const MAX_RETRIES = 3; // Maximum number of retries per backlink

    private $pdo;
    private $proxyManager;

    public function __construct($pdo)
    {
        $this->pdo = $pdo;
        $this->proxyManager = new ProxyManager();
    }

    public function run(): void
    {
        try {
            $backlinks = $this->fetchPendingBacklinks();

            if (empty($backlinks)) {
                echo "No backlinks to verify!\n";
                return;
            }

            $results = $this->verifyBacklinks($backlinks);
            $this->processVerificationResults($results);
            echo "Backlink verification job completed.\n";
        } catch (Exception $e) {
            echo "Error: " . $e->getMessage() . "\n";
            error_log("Verification process failed: " . $e->getMessage());
            echo "Backlink verification job failed.\n";
        }
    }

    private function fetchPendingBacklinks(): array
    {
        $query = "
            SELECT b.id, b.backlink_url, b.target_url, b.anchor_text, 
                c.id AS campaign_id, c.verification_frequency, c.base_url
            FROM backlinks b
            INNER JOIN campaigns c ON b.campaign_id = c.id
            LEFT JOIN backlink_verification_helper h ON c.id = h.campaign_id
            WHERE b.status = 'pending'
            AND c.status = 'enabled'
            AND NOT EXISTS (
                SELECT 1 FROM verification_logs vl 
                WHERE vl.backlink_id = b.id 
                AND DATE(vl.created_at) = CURDATE()
            )
            AND (
                h.campaign_id IS NULL
                OR (
                    h.pending_backlinks > 0
                    AND (h.last_run < CASE c.verification_frequency
                            WHEN 'daily' THEN NOW() - INTERVAL 1 DAY
                            WHEN 'weekly' THEN NOW() - INTERVAL 7 DAY
                            WHEN 'every_two_weeks' THEN NOW() - INTERVAL 14 DAY
                            WHEN 'monthly' THEN NOW() - INTERVAL 30 DAY
                            ELSE NOW()
                        END OR h.last_run IS NULL)
                )
            )
            ORDER BY COALESCE(h.last_run, '2000-01-01') ASC, 
                    COALESCE(h.pending_backlinks, 9999) DESC, 
                    b.last_checked ASC
            LIMIT 20
        ";

        $stmt = $this->pdo->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function verifyBacklinks(array $backlinks): array
    {
        $results = [];

        foreach ($backlinks as $backlink) {
            $attempt = 1;
            $result = null;
            $usedProxies = []; // Track used proxies as ip:port strings

            while ($attempt <= self::MAX_RETRIES && !$result) {
                echo "Verifying backlink ID: {$backlink['id']} (URL: {$backlink['backlink_url']}) - Attempt $attempt\n";

                try {
                    $result = $this->verifySingleBacklink($backlink, $attempt, $usedProxies);
                } catch (Exception $e) {
                    $this->logError($backlink['id'], null, 'http', $e->getMessage(), $attempt);
                    echo "Error on attempt $attempt: " . $e->getMessage() . "\n";
                    $attempt++;
                    $result = null; // Ensure we retry
                    sleep(rand(self::MIN_DELAY, self::MAX_DELAY)); // Delay before retry
                }
            }

            if (!$result) {
                $result = [
                    'backlink_id' => $backlink['id'],
                    'campaign_id' => $backlink['campaign_id'],
                    'is_active' => false,
                    'target_url_found' => false,
                    'anchor_text_found' => false,
                    'error' => 'All retry attempts failed'
                ];
            }

            $results[] = $result;
        }

        return $results;
    }

    private function verifySingleBacklink(array $backlink, int $attempt, array &$usedProxies): array
    {
        static $proxyFailures = [];

        $ch = curl_init();
        $randomUserAgent = self::USER_AGENTS[array_rand(self::USER_AGENTS)];

        $proxy = $this->proxyManager->getLeastUsedProxy($backlink['target_url'], $usedProxies);
        if (!$proxy) {
            throw new Exception("No available proxies to use for backlink ID: {$backlink['id']}");
        }
        $proxy_ip = trim($proxy['ip']);
        $proxy_port = trim($proxy['port']);
        $proxy_type = $proxy['type'] ?? 'http';

        $proxyString = $proxy_ip . ':' . $proxy_port;
        $proxyKey = "$proxy_ip:$proxy_port";
        $usedProxies[] = $proxyKey;

        echo "Using proxy: $proxyKey (Type: " . strtoupper($proxy_type) . ") for backlink ID: {$backlink['id']}\n";

        // Set proxy authentication if credentials exist
        if (!empty($proxy['username']) && !empty($proxy['password'])) {
            curl_setopt($ch, CURLOPT_PROXYUSERPWD, "{$proxy['username']}:{$proxy['password']}");
        }

        // Set proxy type
        $proxyType = match (strtolower($proxy_type)) {
            'https' => CURLPROXY_HTTPS,
            'socks4' => CURLPROXY_SOCKS4,
            'socks5' => CURLPROXY_SOCKS5,
            default => CURLPROXY_HTTP
        };

        curl_setopt_array($ch, [
            CURLOPT_URL => $backlink['backlink_url'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => self::TIMEOUT,
            CURLOPT_USERAGENT => $randomUserAgent,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_ENCODING => 'gzip, deflate',
            CURLOPT_HTTPHEADER => [
                'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
                'Accept-Language: en-US,en;q=0.5',
                'Connection: keep-alive',
                'Upgrade-Insecure-Requests: 1',
                'Cache-Control: max-age=0'
            ],
            CURLOPT_COOKIEFILE => '',
            CURLOPT_CONNECTTIMEOUT => rand(5, 15),
            CURLOPT_PROXY => $proxyString,
            CURLOPT_PROXYTYPE => $proxyType,
            CURLOPT_VERBOSE => true,
            CURLOPT_STDERR => fopen('php://stderr', 'w'),
        ]);

        $content = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);

        curl_close($ch);

        if ($curlError) {
            echo '<pre>';
            print_r([$curlError, $httpCode]);
            echo '<hr>';
            $proxyFailures[$proxyKey] = ($proxyFailures[$proxyKey] ?? 0) + 1;
            $this->logError($backlink['id'], $proxyKey, 'proxy', "Proxy error: $curlError (Proxy: $proxyKey)", $attempt);

            if ($proxyFailures[$proxyKey] >= 3) {
                echo "Removing proxy $proxyKey after 3 failures\n";
                $this->proxyManager->removeProxy($proxy['ip'], $proxy['port']);
                unset($proxyFailures[$proxyKey]);
            }

            throw new Exception("Proxy error with proxy $proxyKey: $curlError");
        }

        if ($httpCode !== 200) {
            $errorMessage = "HTTP error: $httpCode";
            $this->logError($backlink['id'], $proxyKey, 'http', $errorMessage, $attempt);
            throw new Exception($errorMessage);
        }

        if (!$content) {
            $errorMessage = "No content received from backlink URL";
            $this->logError($backlink['id'], $proxyKey, 'http', $errorMessage, $attempt);
            throw new Exception($errorMessage);
        }
        /*
        echo '<br><pre><hr> Content Starts:';
        print_r($content);
        echo '<br>Content Ends.<br><hr>';
        */

        return $this->checkBacklinkPresence($content, $backlink, $proxyKey);
    }

    private function checkBacklinkPresence(string $content, array $backlink, string $proxyKey): array
    {
        $result = [
            'backlink_id' => $backlink['id'],
            'campaign_id' => $backlink['campaign_id'],
            'is_active' => false,
            'target_url_found' => false,
            'anchor_text_found' => false,
            'error' => null
        ];

        try {
            $dom = new DOMDocument();
            @$dom->loadHTML(mb_convert_encoding($content, 'HTML-ENTITIES', 'UTF-8'), LIBXML_NOERROR);
            $links = $dom->getElementsByTagName('a');

            $linkToSearch = $backlink['target_url'] ?? $backlink['base_url'];
            $normalizedTarget = $this->normalizeUrl($linkToSearch);
            $anchorText = preg_replace('/\s+/', ' ', trim($backlink['anchor_text'] ?? ''));

            $targetFound = false;
            $anchorFound = false;

            foreach ($links as $link) {
                $href = $this->normalizeUrl($link->getAttribute('href'));
                $text = preg_replace('/\s+/', ' ', trim($link->textContent));

                if ($href === $normalizedTarget) {
                    $targetFound = true;
                    $result['target_url_found'] = true;

                    if (!empty($anchorText) && stripos($text, $anchorText) !== false) {
                        $anchorFound = true;
                        $result['anchor_text_found'] = true;
                    }

                    break;
                }
            }

            if (!$targetFound) {
                echo "Target URL ({$normalizedTarget}) not found in content for backlink ID: {$backlink['id']}\n";
            } elseif (!$anchorFound && !empty($anchorText)) {
                echo "Anchor text ({$anchorText}) not found for backlink ID: {$backlink['id']}, but target URL was found\n";
            }

            $result['is_active'] = $targetFound;
        } catch (Exception $e) {
            $errorMessage = "DOM parsing error: " . $e->getMessage();
            $this->logError($backlink['id'], null, 'dom', $errorMessage, 1);
            $result['error'] = $errorMessage;
            echo "DOM parsing error for backlink ID: {$backlink['id']}: $errorMessage\n";
        }

        return $result;
    }

    /**
     * Normalize URL by removing scheme (http/https) and www.
     */
    private function normalizeUrl(string $url): string
    {
        $url = strtolower(trim($url));

        // Remove http://, https://, and www.
        $url = preg_replace('/^(https?:\/\/)?(www\.)?/', '', $url);

        // Remove trailing slash
        return rtrim($url, '/');
    }

    private function logError(int $backlinkId, ?string $proxyKey, string $errorType, string $errorMessage, int $attempt): void
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO verification_errors (backlink_id, proxy_key, error_type, error_message, attempt, created_at) 
         VALUES (?, ?, ?, ?, ?, NOW())"
        );
        $stmt->execute([$backlinkId, $proxyKey, $errorType, $errorMessage, $attempt]);
    }

    private function processVerificationResults(array $results): void
    {
        foreach ($results as $result) {
            try {
                $this->pdo->beginTransaction();

                $this->updateBacklinkStatus($result);
                $this->logVerification($result);
                $this->updateVerificationLog($result['campaign_id']);
                $this->updateCampaignTimestamp($result['campaign_id']);

                $this->pdo->commit();
            } catch (Exception $e) {
                $this->pdo->rollBack();
                error_log("Error processing backlink ID {$result['backlink_id']}: " . $e->getMessage());
                echo "Error processing backlink ID {$result['backlink_id']}: " . $e->getMessage() . "\n";
            }
        }
    }

    private function updateBacklinkStatus(array $result): void
    {

        $status = $result['is_active'] ? 'alive' : 'dead';
        $anchor_text_found = $result['anchor_text_found'] ?? FALSE;
        $stmt = $this->pdo->prepare(
            "UPDATE backlinks SET status = ?, anchor_text_found = ?, last_checked = NOW() WHERE id = ?"
        );
        $stmt->execute([$status, $anchor_text_found, $result['backlink_id']]);
    }

    private function logVerification(array $result): void
    {
        $status = $result['is_active'] ? 'alive' : 'dead';
        $stmt = $this->pdo->prepare(
            "INSERT INTO verification_logs (backlink_id, status, error_message, created_at) 
             VALUES (?, ?, ?, NOW())"
        );
        $stmt->execute([$result['backlink_id'], $status, $result['error']]);
    }

    private function updateVerificationLog(int $campaignId): void
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO backlink_verification_helper (campaign_id, last_run, pending_backlinks)
            SELECT ?, NOW(), COUNT(*)
            FROM backlinks 
            WHERE campaign_id = ? AND status = 'pending'
            ON DUPLICATE KEY UPDATE 
                last_run = NOW(),
                pending_backlinks = VALUES(pending_backlinks)
        ");
        $stmt->execute([$campaignId, $campaignId]);
    }

    private function updateCampaignTimestamp(int $campaignId): void
    {
        $stmt = $this->pdo->prepare(
            "UPDATE campaigns SET last_checked = NOW() WHERE id = ?"
        );
        $stmt->execute([$campaignId]);
    }
}

// Execute the job
$verifier = new BacklinkVerifier($pdo);
$verifier->run();
