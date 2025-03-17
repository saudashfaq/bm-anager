<?php

require_once __DIR__ . '/../config.php';

function verifyBacklinksInParallel(array $backlinks, int $maxConcurrentRequests = 5)
{
    $results = [];
    $multiHandle = curl_multi_init();
    $curlHandles = [];
    $running = 0;
    $batch = [];

    // Process backlinks in batches
    foreach ($backlinks as $index => $backlink) {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $backlink['target_url'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (compatible; BacklinkChecker/1.0)',
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_PRIVATE => $index // Store backlink index for reference
        ]);

        curl_multi_add_handle($multiHandle, $ch);
        $curlHandles[$index] = $ch;
        $batch[$index] = $backlink;

        if (count($batch) >= $maxConcurrentRequests) {
            // Process current batch
            $results = array_merge($results, processBacklinkBatch($multiHandle, $curlHandles, $batch));

            // Reset for next batch
            $curlHandles = [];
            $batch = [];
        }
    }

    // Process remaining backlinks
    if (!empty($batch)) {
        $results = array_merge($results, processBacklinkBatch($multiHandle, $curlHandles, $batch));
    }

    curl_multi_close($multiHandle);
    return $results;
}

function processBacklinkBatch($multiHandle, $curlHandles, $batch)
{
    $results = [];
    $running = null;

    // Execute the multi handle
    do {
        $status = curl_multi_exec($multiHandle, $running);
        if ($running) {
            curl_multi_select($multiHandle);
        }
    } while ($running > 0 && $status === CURLM_OK);

    // Process the responses
    foreach ($curlHandles as $ch) {
        $index = curl_getinfo($ch, CURLINFO_PRIVATE);
        $backlink = $batch[$index];
        $content = curl_multi_getcontent($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        $result = [
            'backlink_id' => $backlink['id'],
            'campaign_id' => $backlink['campaign_id'],
            'status' => 'dead',
            'error' => null
        ];

        if ($httpCode === 200 && $content) {
            try {
                $dom = new DOMDocument();
                @$dom->loadHTML($content, LIBXML_NOERROR);

                $links = $dom->getElementsByTagName('a');
                foreach ($links as $link) {
                    $href = $link->getAttribute('href');
                    $text = trim($link->textContent);

                    $normalizedHref = rtrim(strtolower($href), '/');
                    $normalizedBacklinkUrl = rtrim(strtolower($backlink['backlink_url']), '/');

                    if ($normalizedHref === $normalizedBacklinkUrl && $text === $backlink['anchor_text']) {
                        $result['status'] = 'alive';
                        break;
                    }
                }
            } catch (Exception $e) {
                $result['error'] = "DOM parsing error: " . $e->getMessage();
            }
        } else {
            $result['error'] = "HTTP error: " . $httpCode;
        }

        $results[] = $result;
        curl_multi_remove_handle($multiHandle, $ch);
        curl_close($ch);
    }

    return $results;
}

// Fetch backlinks that need validation
$stmt = $pdo->prepare("
    SELECT b.id, b.backlink_url, b.target_url, b.anchor_text, 
           c.id as campaign_id, c.verification_frequency, c.last_checked
    FROM backlinks b
    JOIN campaigns c ON b.campaign_id = c.id
    WHERE b.status = 'pending' AND
        (c.last_checked IS NULL OR 
        (c.verification_frequency = 'daily' AND c.last_checked < NOW() - INTERVAL 1 DAY) OR
        (c.verification_frequency = 'twice_a_day' AND c.last_checked < NOW() - INTERVAL 12 HOUR) OR
        (c.verification_frequency = 'two_days' AND c.last_checked < NOW() - INTERVAL 2 DAY) OR
        (c.verification_frequency = 'weekly' AND c.last_checked < NOW() - INTERVAL 7 DAY) OR
        (c.verification_frequency = 'two_weeks' AND c.last_checked < NOW() - INTERVAL 14 DAY) OR
        (c.verification_frequency = 'monthly' AND c.last_checked < NOW() - INTERVAL 30 DAY))
    ORDER BY c.last_checked ASC, b.last_checked ASC
    LIMIT 20
");
$stmt->execute();
$backlinks = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Verify backlinks in parallel
$results = verifyBacklinksInParallel($backlinks);

// Process results and update database
foreach ($results as $result) {
    try {
        // Begin transaction
        $pdo->beginTransaction();

        // Update backlink status
        $stmt = $pdo->prepare("UPDATE backlinks SET status = ?, last_checked = NOW() WHERE id = ?");
        $stmt->execute([$result['status'], $result['backlink_id']]);

        // Log the verification
        $stmt = $pdo->prepare("INSERT INTO verification_logs (backlink_id, status, error_message) VALUES (?, ?, ?)");
        $stmt->execute([$result['backlink_id'], $result['status'], $result['error']]);

        // Update backlink verification log
        $stmt = $pdo->prepare("
            INSERT INTO backlink_verification_helper (campaign_id, last_run, pending_backlinks)
            SELECT ?, NOW(), COUNT(*) 
            FROM backlinks 
            WHERE campaign_id = ? AND status = 'pending'
            ON DUPLICATE KEY UPDATE 
                last_run = NOW(),
                pending_backlinks = (SELECT COUNT(*) FROM backlinks WHERE campaign_id = ? AND status = 'pending')
        ");
        $stmt->execute([$result['campaign_id'], $result['campaign_id'], $result['campaign_id']]);

        // Update campaign last_checked timestamp
        $stmt = $pdo->prepare("UPDATE campaigns SET last_checked = NOW() WHERE id = ?");
        $stmt->execute([$result['campaign_id']]);

        $pdo->commit();
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Error processing result for backlink ID {$result['backlink_id']}: " . $e->getMessage());
        continue;
    }
}

echo "Backlink verification job completed.\n";
