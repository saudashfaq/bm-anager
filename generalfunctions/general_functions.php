<?php
// general_functions.php

class BacklinkUtils
{
    private $pdo;

    public function __construct($pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Extracts the base domain from a URL.
     * @param string $url The URL to process.
     * @return string|null The base domain (e.g., 'example.com') or null if invalid.
     */
    public function getBaseDomain($url)
    {
        $parsedUrl = parse_url($url);
        if (!isset($parsedUrl['host'])) {
            return null; // Invalid URL
        }
        $host = $parsedUrl['host'];
        $host = preg_replace('/^www\./i', '', $host);
        return strtolower($host);
    }

    /**
     * Checks if a backlink is a duplicate based on its base domain.
     * @param int $campaignId The campaign ID.
     * @param string $baseDomain The base domain to check.
     * @return array|null The existing backlink if a duplicate is found, null otherwise.
     */
    public function checkForDuplicate($campaignId, $baseDomain)
    {
        // First, check for duplicates using the base_domain column (for backlinks that have it)
        $stmt = $this->pdo->prepare("
        SELECT id 
        FROM backlinks 
        WHERE campaign_id = ? 
        AND base_domain = ? 
        AND base_domain IS NOT NULL 
        LIMIT 1
    ");
        $stmt->execute([$campaignId, $baseDomain]);
        $existingBacklink = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($existingBacklink) {
            return $existingBacklink;
        }

        // If no duplicate is found using base_domain, fall back to checking backlink_url
        // for backlinks where base_domain is NULL
        $stmt = $this->pdo->prepare("
        SELECT id, backlink_url 
        FROM backlinks 
        WHERE campaign_id = ? 
        AND base_domain IS NULL
    ");
        $stmt->execute([$campaignId]);
        $backlinksWithoutBaseDomain = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($backlinksWithoutBaseDomain as $backlink) {
            $existingBaseDomain = $this->getBaseDomain($backlink['backlink_url']);
            if ($existingBaseDomain === $baseDomain) {
                // Update the base_domain for this backlink to avoid future fallbacks
                $updateStmt = $this->pdo->prepare("UPDATE backlinks SET base_domain = ? WHERE id = ?");
                $updateStmt->execute([$baseDomain, $backlink['id']]);
                return $backlink;
            }
        }

        return null; // No duplicate found
    }

    /**
     * Inserts a new backlink and handles duplicate status.
     * @param int $campaignId The campaign ID.
     * @param string $backlinkUrl The backlink URL.
     * @param string $targetUrl The target URL.
     * @param string $anchorText The anchor text.
     * @param int $userId The ID of the user creating the backlink.
     * @return int The ID of the newly inserted backlink.
     * @throws Exception If insertion fails.
     */
    public function insertBacklink($campaignId, $backlinkUrl, $targetUrl, $anchorText, $userId)
    {
        $baseDomain = $this->getBaseDomain($backlinkUrl);
        if ($baseDomain === null) {
            throw new Exception('Invalid backlink URL: Unable to extract domain');
        }

        // Check for duplicate
        $existingBacklink = $this->checkForDuplicate($campaignId, $baseDomain);
        $isDuplicate = $existingBacklink ? 'yes' : 'no';

        // Insert the new backlink
        $stmt = $this->pdo->prepare("
            INSERT INTO backlinks (
                campaign_id, backlink_url, target_url, anchor_text, 
                status, is_duplicate, created_by, created_at, base_domain
            ) VALUES (?, ?, ?, ?, 'pending', ?, ?, NOW(), ?)
        ");
        $stmt->execute([
            $campaignId,
            $backlinkUrl,
            $targetUrl,
            $anchorText,
            $isDuplicate,
            $userId,
            $baseDomain
        ]);

        $newBacklinkId = $this->pdo->lastInsertId();

        // If it's a duplicate, mark the existing backlink as a duplicate too
        if ($isDuplicate === 'yes') {
            $stmt = $this->pdo->prepare("UPDATE backlinks SET is_duplicate = 'yes' WHERE id = ?");
            $stmt->execute([$existingBacklink['id']]);
        }

        return $newBacklinkId;
    }

    /**
     * Updates the duplicate status of remaining backlinks after deletion.
     * @param int $campaignId The campaign ID.
     * @param string $baseDomain The base domain of the deleted backlink.
     */
    public function updateDuplicateStatusAfterDelete($campaignId, $baseDomain)
    {
        if ($baseDomain === null) {
            return; // Skip invalid entries
        }

        // Count remaining backlinks with the same base domain in the same campaign
        $stmt = $this->pdo->prepare("SELECT id FROM backlinks WHERE campaign_id = ? AND base_domain = ?");
        $stmt->execute([$campaignId, $baseDomain]);
        $remainingBacklinks = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $remainingCount = count($remainingBacklinks);

        if ($remainingCount === 1) {
            // If only one backlink remains with this base domain, remove its duplicate status
            $remainingId = $remainingBacklinks[0]['id'];
            $stmt = $this->pdo->prepare("UPDATE backlinks SET is_duplicate = 'no' WHERE id = ?");
            $stmt->execute([$remainingId]);
        }
        // If more than one backlink remains, their is_duplicate status remains 'yes'
        // If no backlinks remain, nothing to update
    }
}
