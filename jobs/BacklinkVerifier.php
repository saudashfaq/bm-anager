<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);


require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/proxies.json';
class BacklinkVerifier
{
    // Updated to mimic a real browser user agent (rotates randomly)
    private const USER_AGENTS = [
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
        'Mozilla/5.0 (Macintosh; Intel Mac OS X 14_2_1) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.2 Safari/605.1.15',
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:121.0) Gecko/20100101 Firefox/121.0'
    ];
    private const BATCH_SIZE = 5;
    private const TIMEOUT = 30;
    private const MIN_DELAY = 1; // Minimum seconds between requests
    private const MAX_DELAY = 5; // Maximum seconds between requests

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
            //var_dump($backlinks);

            if (empty($backlinks)) {
                echo "No backlinks to verify.\n";
                return;
            }

            $results = $this->verifyBacklinks($backlinks);
            //var_dump($results);
            $this->processVerificationResults($results);
            echo "Backlink verification job completed.\n";
        } catch (Exception $e) {
            echo $e->getMessage();
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
                h.campaign_id IS NULL  -- First run: no helper data, include all pending backlinks
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
        $multiHandle = curl_multi_init();
        $results = [];

        foreach (array_chunk($backlinks, self::BATCH_SIZE) as $batch) {
            $curlHandles = $this->initializeCurlHandles($batch);
            $batchResults = $this->processCurlBatch($multiHandle, $curlHandles, $batch);
            $results = array_merge($results, $batchResults);

            foreach ($curlHandles as $ch) {
                curl_multi_remove_handle($multiHandle, $ch);
                curl_close($ch);
            }

            // Add human-like delay between batches
            //sleep(rand(self::MIN_DELAY, self::MAX_DELAY));
        }

        curl_multi_close($multiHandle);
        return $results;
    }

    private function initializeCurlHandles(array $batch): array
    {
        $curlHandles = [];
        $randomUserAgent = self::USER_AGENTS[array_rand(self::USER_AGENTS)];

        foreach ($batch as $index => $backlink) {
            $ch = curl_init();

            // Get proxy for this URL
            $proxy = $this->proxyManager->getLeastUsedProxy($backlink['target_url']);
            $proxyString = "http://{$proxy['ip']}:{$proxy['port']}";

            // Set proxy authentication if credentials exist
            if (!empty($proxy['username']) && !empty($proxy['password'])) {
                curl_setopt($ch, CURLOPT_PROXYUSERPWD, "{$proxy['username']}:{$proxy['password']}");
            }

            curl_setopt_array($ch, [
                CURLOPT_URL => $backlink['backlink_url'],
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_TIMEOUT => self::TIMEOUT,
                CURLOPT_USERAGENT => $randomUserAgent,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
                CURLOPT_ENCODING => 'gzip, deflate, br',
                CURLOPT_HTTPHEADER => [
                    'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
                    'Accept-Language: en-US,en;q=0.5',
                    'Connection: keep-alive',
                    'Upgrade-Insecure-Requests: 1',
                    'Cache-Control: max-age=0'
                ],
                CURLOPT_COOKIEFILE => '',
                CURLOPT_PRIVATE => $index,
                CURLOPT_CONNECTTIMEOUT => rand(5, 15),
                // Proxy settings
                CURLOPT_PROXY => $proxyString,
                CURLOPT_PROXYTYPE => CURLPROXY_HTTP,
            ]);

            $curlHandles[$index] = $ch;
        }

        return $curlHandles;
    }

    private function processCurlBatch($multiHandle, array $curlHandles, array $batch): array
    {
        foreach ($curlHandles as $ch) {
            curl_multi_add_handle($multiHandle, $ch);
        }

        do {
            $status = curl_multi_exec($multiHandle, $running);
            if ($running) {
                curl_multi_select($multiHandle);
                // Add small random delay to mimic human behavior
                usleep(rand(100000, 500000)); // 0.1 to 0.5 seconds
            }
        } while ($running > 0 && $status === CURLM_OK);

        return $this->processCurlResponses($curlHandles, $batch);
    }

    private function processCurlResponses(array $curlHandles, array $batch): array
    {
        $results = [];

        foreach ($curlHandles as $ch) {
            $index = curl_getinfo($ch, CURLINFO_PRIVATE);
            $backlink = $batch[$index];
            $content = curl_multi_getcontent($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            //print_r([$index, $backlink, $content, $httpCode]);
            //die('stopped at line 177');
            $result = [
                'backlink_id' => $backlink['id'],
                'campaign_id' => $backlink['campaign_id'],
                'status' => 'dead',
                'error' => $httpCode !== 200 ? "HTTP error: $httpCode" : null
            ];

            if ($httpCode === 200 && $content) {
                $result = $this->checkBacklinkPresence($content, $backlink, $result);
            }

            $results[] = $result;
        }

        return $results;
    }

    private function checkBacklinkPresence(string $content, array $backlink, array $result): array
    {
        try {
            $dom = new DOMDocument();
            @$dom->loadHTML($content, LIBXML_NOERROR);
            $links = $dom->getElementsByTagName('a');

            //if target url is missing then campaign's base url will be searched
            $link_to_search = $backlink['target_url'] ?? $backlink['base_url'];
            $normalizedBacklinkUrl = rtrim(strtolower($link_to_search), '/');

            foreach ($links as $link) {
                $href = rtrim(strtolower($link->getAttribute('href')), '/');
                $text = trim($link->textContent);

                //print_r(['db link' => $normalizedBacklinkUrl, 'wikipedia link' => $link]);
                //echo 'remove continue at line210';
                //continue;


                //commented out the text comparison in the following if condition
                //&& $text === $backlink['anchor_text']
                if ($href === $normalizedBacklinkUrl) {
                    $result['status'] = 'alive';
                    $result['error'] = null;
                    break;
                }
            }
        } catch (Exception $e) {
            $result['error'] = "DOM parsing error: " . $e->getMessage();
        }

        return $result;
    }

    private function processVerificationResults(array $results): void
    {
        foreach ($results as $result) {
            try {
                //print_r($result);
                $this->pdo->beginTransaction();

                $this->updateBacklinkStatus($result);
                $this->logVerification($result);
                $this->updateVerificationLog($result['campaign_id']);
                $this->updateCampaignTimestamp($result['campaign_id']);

                $this->pdo->commit();
            } catch (Exception $e) {
                $this->pdo->rollBack();
                error_log("Error processing backlink ID {$result['backlink_id']}: " . $e->getMessage());
            }
        }
    }

    private function updateBacklinkStatus(array $result): void
    {
        $stmt = $this->pdo->prepare(
            "UPDATE backlinks SET status = ?, last_checked = NOW() WHERE id = ?"
        );
        $stmt->execute([$result['status'], $result['backlink_id']]);
    }

    private function logVerification(array $result): void
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO verification_logs (backlink_id, status, error_message, created_at) 
             VALUES (?, ?, ?, NOW())"
        );
        $stmt->execute([$result['backlink_id'], $result['status'], $result['error']]);
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
