<?php
/**
 * AIService - Server-side NLP Logic for IRMS
 * Performs keyword-based analysis to audit incident categorizations and urgency.
 */
class AIService {
    private static $dictionary = [
        'Fire Incident'     => ['sunog', 'apoy', 'usok', 'fire', 'smoke', 'sparks', 'electrical', 'kuryente', 'liyab', 'bumbero', 'flame'],
        'Crime / Theft'     => ['magnanakaw', 'holdap', 'thief', 'robbery', 'robber', 'baril', 'gun', 'weapon', 'saksak', 'stabbing', 'away', 'gulo', 'fight', 'riot', 'snatch', 'snatcher'],
        'Medical Emergency' => ['hinimatay', 'fainted', 'unconscious', 'dugo', 'blood', 'bleeding', 'sugat', 'injury', 'accident', 'aksidente', 'nahihilo', 'labor', 'stroke', 'seizure'],
        'Flood'             => ['baha', 'flood', 'bagyo', 'typhoon', 'storm', 'puno', 'tumbang', 'landslide', 'lindol', 'earthquake'],
        'Power Outage'      => ['tubig', 'leak', 'tulo', 'linya', 'kable', 'poste', 'meralco', 'maynilad', 'blackout', 'brownout'],
        'Road Accident'     => ['traffic', 'trapik', 'road', 'daan', 'lubak', 'pothole', 'harang', 'obstruction', 'parked'],
        'Missing Person'    => ['nawawala', 'missing', 'lost person', 'hindi umuwi', 'child']
    ];

    private static $emergencies = [
        'patay', 'death', 'dead', 'agaw buhay', 'hindi makahinga', 'breathless', 
        'active shooter', 'major fire', 'trapped', 'mass casualty'
    ];

    /**
     * Analyzes incident text and returns suggestions.
     */
    public static function analyze($title, $description) {
        $text = strtolower($title . ' ' . $description);
        $scores = [];
        $isCritical = false;

        // Check for emergency keywords
        foreach (self::$emergencies as $ew) {
            if (str_contains($text, $ew)) {
                $isCritical = true;
                break;
            }
        }

        // Score categories
        foreach (self::$dictionary as $cat => $keywords) {
            $scores[$cat] = 0;
            foreach ($keywords as $kw) {
                if (str_contains($text, $kw)) {
                    $scores[$cat]++;
                }
            }
        }

        arsort($scores);
        $bestMatch = key($scores);
        $score = current($scores);

        return [
            'suggested_category' => ($score > 0) ? $bestMatch : null,
            'is_critical'        => $isCritical,
            'confidence'         => $score
        ];
    }

    /**
     * Generates a 1-sentence executive summary.
     */
    public static function generateSummary($category, $location, $description) {
        $desc = trim($description);
        $cleanDesc = preg_replace('/[\r\n\t]+/', ' ', $desc);
        
        // Grab the first 100 characters or the first sentence
        $snippet = $cleanDesc;
        if (($pos = strpos($cleanDesc, '.')) !== false && $pos > 10) {
            $snippet = substr($cleanDesc, 0, $pos + 1);
        } else {
            $snippet = mb_strimwidth($cleanDesc, 0, 100, "...");
        }

        // Location cleaning (try to get the first part of the address)
        $locParts = explode(',', $location);
        $shortLoc = trim($locParts[0]);

        $summary = "{$category} reported at {$shortLoc}. {$snippet}";
        
        return mb_strimwidth($summary, 0, 240, "...");
    }

    /**
     * Finds a potential duplicate incident based on proximity, category, and time.
     * Returns the duplicate_of ID if found, otherwise Null.
     */
    public static function detectPotentialDuplicate($pdo, $lat, $lng, $categoryId, $currentId) {
        if (!$lat || !$lng) return null;

        // Approx 500m window in degrees
        $coordsWindow = 0.005; 
        
        $stmt = $pdo->prepare("
            SELECT id FROM incidents 
            WHERE category_id = ? 
              AND status NOT IN ('rejected', 'closed')
              AND id != ?
              AND reported_at >= DATE_SUB(NOW(), INTERVAL 12 HOUR)
              AND latitude BETWEEN ? AND ?
              AND longitude BETWEEN ? AND ?
            ORDER BY reported_at ASC
            LIMIT 1
        ");

        $stmt->execute([
            $categoryId,
            $currentId,
            $lat - $coordsWindow, $lat + $coordsWindow,
            $lng - $coordsWindow, $lng + $coordsWindow
        ]);

        return $stmt->fetchColumn() ?: null;
    }

    /**
     * Generates a formal, government-standard English report.
     */
    public static function generateFormalReport($category, $location, $description, $severity) {
        $locParts = explode(',', $location);
        $shortLoc = trim($locParts[0] ?? 'the designated location');
        
        // High-level terminology mapping
        $formalTerms = [
            'Fire' => 'structural conflagration',
            'Crime/Peace & Order' => 'alleged civilian altercation or public safety hazard',
            'Medical Emergency' => 'urgent medical distress incident',
            'Accident' => 'vehicular or civilian accident occurrence',
            'Natural Disaster' => 'environmental hazard or natural calamity',
            'Others' => 'miscellaneous public safety concern'
        ];

        $term = $formalTerms[$category] ?? 'public safety incident';
        $urgentText = match($severity) {
            'critical' => 'Designated as a Critical emergency requiring immediate tactical intervention.',
            'high' => 'Classified as High Urgency, prioritizing rapid response protocols.',
            'medium' => 'Evaluated as Standard Priority for routine dispatch.',
            default => 'Noted with Standard operational priority.'
        };

        // Attempt to extract more technical detail from description
        $desc = strtolower($description);
        $detail = "Reports indicate a situation requiring local authority presence.";
        if (str_contains($desc, 'sunog') || str_contains($desc, 'apoy')) $detail = "Incident involves persistent structural thermal elements.";
        if (str_contains($desc, 'magnanakaw') || str_contains($desc, 'nakawan')) $detail = "Subject involved in suspected unauthorized property acquisition.";
        if (str_contains($desc, 'aksidente') || str_contains($desc, 'banggaan')) $detail = "Occurrence involves mechanical kinetic impact between varied entities.";

        $report = "OFFICIAL INCIDENT DISCLOSURE:\n\n";
        $report .= "Primary dispatch was alerted to a suspected {$term} situated within the vicinity of {$shortLoc}. ";
        $report .= "Upon evaluation, the event was {$urgentText} ";
        $report .= "{$detail} ";
        $report .= "All available departmental resources have been coordinated for immediate situational containment and resolution.";

        return $report;
    }

    /**
     * Unified Chat Entry Point - Multi-Tier Fallback (Groq -> Gemini -> Simulation)
     */
    public static function askAI($userQuery, $recentIncidents = []) {
        require_once __DIR__ . '/../config/ai_config.php';
        
        // Tier 1: GROQ (Ultra Fast)
        if (defined('GROQ_API_KEY') && !empty(GROQ_API_KEY)) {
            $response = self::askGroq($userQuery, GROQ_API_KEY, $recentIncidents);
            if ($response) return $response;
        }

        // Tier 2: GEMINI (Secondary)
        if (defined('GEMINI_API_KEY') && !empty(GEMINI_API_KEY)) {
            $response = self::askGemini($userQuery, GEMINI_API_KEY, $recentIncidents);
            if ($response) return $response;
        }

        // Tier 3: SMART SIMULATION (100% Offline Fallback)
        return self::simulatePersona($userQuery, $recentIncidents);
    }

    /**
     * TIER 1: GROQ Implementation (Llama-3.3-70b-versatile)
     */
    private static function askGroq($userQuery, $apiKey, $recentIncidents = []) {
        $url = "https://api.groq.com/openai/v1/chat/completions";
        
        $situationReport = "No recent incidents.";
        if (!empty($recentIncidents)) {
            $situationReport = "LIVE SITUATION REPORT:\n";
            foreach($recentIncidents as $inc) {
                $loc = explode(',', $inc['location'])[0];
                $situationReport .= "- {$inc['category']} at {$loc} ({$inc['status']})\n";
            }
        }

        $systemPrompt = "You are the QC-ALERTO AI Assistant (Expert Mode). Address user as 'QCitizen'. Tone: Professional, Localized (Quezon City), Helpful. Use 'Po/Opo'.
        
        CRITICAL LOGIC RULE: 
        - If the user asks about reporting OUTSIDE Quezon City (e.g., 'pwede ba sa labas ng qc', 'hindi sa qc'), you MUST start with 'Hindi po', 'Paumanhin po', or 'Pasensya na po'. 
        - NEVER say 'Opo' or other affirmative fillers if the answer is effectively 'No' or 'Not allowed'.
        
        INTERNAL MANUAL:
        - Report: /public/report.php / public/anonymous_report.php
        - Track: /public/track.php
        - Features: Ban Hammer (Anti-Troll), SLA Monitoring, Resource Assignment.
        - Rule: Quezon City area ONLY.
        {$situationReport}";

        $data = [
            "model" => "llama-3.3-70b-versatile",
            "messages" => [
                ["role" => "system", "content" => $systemPrompt],
                ["role" => "user", "content" => $userQuery]
            ],
            "temperature" => 0.7
        ];

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $apiKey
        ]);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $result = json_decode($response, true);
        return $result['choices'][0]['message']['content'] ?? null;
    }

    /**
     * TIER 2: GEMINI Implementation
     */
    private static function askGemini($userQuery, $apiKey, $recentIncidents = []) {
        // Use a simplified version for secondary fallback
        $model = "models/gemini-1.5-flash";
        $url = "https://generativelanguage.googleapis.com/v1beta/{$model}:generateContent?key=" . $apiKey;

        $systemContent = "You are the QC-ALERTO AI. Address as QCitizen. Use Po/Opo. 
        LOGIC RULE: Never say 'Opo' if the answer is 'No' (e.g. reporting outside QC). Support only Quezon City.
        Help with: " . $userQuery;

        $data = [
            "contents" => [["parts" => [["text" => $systemContent]]]],
            "generationConfig" => ["temperature" => 0.7, "maxOutputTokens" => 800]
        ];

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $result = json_decode($response, true);
        return $result['candidates'][0]['content']['parts'][0]['text'] ?? null;
    }

    private static function simulatePersona($query, $recentIncidents = []) {
        $q = strtolower($query);

        if (str_contains($q, 'paano') || str_contains($q, 'gumagana')) {
            return "Ang QC-ALERTO IRMS ay gumagana sa 4 steps: Report -> Verify -> Dispatch -> Resolve. Maaari kayong mag-report sa /public/report.php, QCitizen!";
        }

        if (str_contains($q, 'sunog') || str_contains($q, 'insidente')) {
            if (!empty($recentIncidents)) {
                $res = "Heto po ang latest updates, QCitizen:\n";
                foreach(array_slice($recentIncidents, 0, 3) as $inc) {
                    $loc = explode(',', $inc['location'])[0];
                    $res .= "• {$inc['category']} sa vicinity ng {$loc}\n";
                }
                return $res;
            }
            return "Wala po kaming namo-monitor na major incidents sa ngayon, QCitizen.";
        }

        if (str_contains($q, 'hindi sa qc') || str_contains($q, 'labas ng qc')) {
            return "Pasensya na po, QCitizen. Ang QC-ALERTO ay eksklusibo lamang para sa **Quezon City**.";
        }

        if (str_contains($q, 'ban hammer') || str_contains($q, 'ban')) {
            return "Ang **Ban Hammer** po ay ang aming anti-troll system para sa seguridad ng lahat ng QCitizen.";
        }

        if (str_contains($q, 'report') || str_contains($q, 'ulat')) {
            return "Opo QCitizen, maaari kayong mag-report sa aming [Standard Report Page](/public/report.php).";
        }
        
        if (str_contains($q, 'kumusta')) return "Mabuhay po, QCitizen! Ako ang QC-ALERTO Assistant. Ano po ang maipaglilingkod ko?";
        
        return "Magandang araw po! Ako ang inyong QC-ALERTO System Expert. Maaari niyo po akong tanungin tungkol sa **pag-report** o **pag-track**. Ano po ang maipaglilingkod ko, QCitizen?";
    }
}
