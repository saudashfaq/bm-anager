<?php

// Function to extract base domain from a URL
function getBaseDomain($url)
{
    $host = parse_url($url, PHP_URL_HOST);
    if (!$host) return null;
    // Remove 'www.' if present
    $host = preg_replace('/^www\./', '', $host);
    // Split the host into parts
    $parts = explode('.', $host);
    $numParts = count($parts);
    if ($numParts < 2) return $host;
    // Handle common TLDs with two parts (e.g., .co.uk)
    $tld = $parts[$numParts - 1];
    $sld = $parts[$numParts - 2];
    if ($numParts >= 3 && in_array($tld, ['uk', 'au', 'jp']) && in_array($sld, ['co', 'org', 'gov'])) {
        return $parts[$numParts - 3] . '.' . $sld . '.' . $tld;
    }
    return $sld . '.' . $tld;
}

// Function to fetch all base domains for a campaign
function getCampaignBaseDomains($pdo, $campaign_id)
{
    $stmt = $pdo->prepare("SELECT DISTINCT SUBSTRING_INDEX(SUBSTRING_INDEX(backlink_url, '://', -1), '/', 1) AS base_domain FROM backlinks WHERE campaign_id = ?");
    $stmt->execute([$campaign_id]);
    $baseDomains = $stmt->fetchAll(PDO::FETCH_COLUMN);
    // Convert to a set-like array for faster lookup
    $baseDomainSet = [];
    foreach ($baseDomains as $domain) {
        $baseDomain = getBaseDomain('https://' . $domain); // Normalize using getBaseDomain
        if (!empty($baseDomain)) {
            $baseDomainSet[$baseDomain] = true;
        }
    }
    return $baseDomainSet;
}

// Function to check if a backlink is a duplicate based on a set of base domains
function isDuplicateInBaseDomains($backlink_url, $baseDomains)
{
    $baseDomain = getBaseDomain($backlink_url);
    if (!empty($baseDomain)) {
        return isset($baseDomains[$baseDomain]) ? 'yes' : 'no';
    }
    return 'no';
}

// Function to check if a backlink is a duplicate based on base domain
function checkDuplicateBaseDomain($pdo, $campaign_id, $backlink_url, $current_backlink_id = 0)
{
    $campaignBaseDomains = getCampaignBaseDomains($pdo, $campaign_id);
    return isDuplicateInBaseDomains($backlink_url, $campaignBaseDomains);
}
