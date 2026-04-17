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
            'category'   => ($score > 0) ? $bestMatch : null,
            'is_critical' => $isCritical,
            'confidence'  => (float)$score,
            'reason'      => ($score > 0) ? "Keyword match found for {$bestMatch}." : "No clear keywords detected."
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
     * AI-Powered Professional Formal Report Generator
     */
    public static function generateFormalReport($category, $location, $description, $severity) {
        $prompt = "As an Official Incident Auditor, generate a highly professional, clinical, and formal 2-paragraph official report for the following incident. 
        Category: $category
        Location: $location
        Severity: $severity
        Citizen Description: \"$description\"
        
        Tone: Official, legal-grade, objective. Use professional terminology (e.g., 'dispatch', 'situational containment', 'jurisdictional response').
        Do not include headers or footers, just the 2 paragraphs of text.";

        $response = self::callGroq($prompt);
        return $response ?: "An official incident report has been registered for this case at {$location}. Initial containment measures and departmental coordination are in progress.";
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
     * Classifies an incident using LLM (Groq) for high accuracy.
     */
    public static function classifyIncident($title, $description, $categories) {
        require_once __DIR__ . '/../config/ai_config.php';
        if (!defined('GROQ_API_KEY') || empty(GROQ_API_KEY)) {
            return self::analyze($title, $description); // Fallback to keyword analysis
        }

        $catList = implode(', ', array_map(fn($c) => $c['name'], $categories));
        $prompt = "Classify this incident based on Title and Description. 
Title: {$title}
Description: {$description}

Available Categories: [{$catList}]
Severities: [low, medium, high, critical]

Return ONLY a JSON object:
{
  \"category\": \"Exact Name of Category\",
  \"severity\": \"low/medium/high/critical\", 
  \"confidence\": 0.0 to 1.0,
  \"location_suggestion\": \"Extracted specific address or landmark (null if none)\",
  \"confidence_location\": 0.0 to 1.0,
  \"reason\": \"1-sentence explanation\"
}";

        $url = "https://api.groq.com/openai/v1/chat/completions";
        $data = [
            "model" => "llama-3.3-70b-versatile",
            "messages" => [
                ["role" => "system", "content" => "You are an incident classification expert. Return ONLY valid JSON."],
                ["role" => "user", "content" => $prompt]
            ],
            "response_format" => ["type" => "json_object"],
            "temperature" => 0.1
        ];

        $content = self::callGroq($prompt, true); // true means expect JSON
        
        if ($content) {
            $parsed = json_decode($content, true);
            if ($parsed) return $parsed;
        }

        return self::analyze($title, $description); // Final fallback
    }

    /**
     * AI-Driven Responder Matching
     * Suggests the best responder based on incident context and available personnel.
     */
    public static function suggestResponder(string $title, string $category, array $availableResponders): ?int {
        if (empty($availableResponders)) return null;

        $responderCtx = "";
        foreach ($availableResponders as $r) {
            $responderCtx .= "- ID: {$r['id']} | Name: {$r['name']}\n";
        }

        $prompt = "As an incident coordinator for Quezon City, analyze this report and pick the most appropriate responder from the list.
        Incident: \"$title\" ($category)
        
        Responders Available:
        $responderCtx
        
        Rules:
        1. Fire/Electrical -> Prefer Fire/BFP related names.
        2. Crime/Theft -> Prefer Police/PNP/Security names.
        3. Medical -> Prefer Medical/Rescue/Ambulance.
        4. Others -> Use highest relevance.
        5. Return ONLY the integer ID of the best responder.";

        $bestId = self::callGroq($prompt);
        
        // If the result is JSON or has text, extract digits
        if (is_array($bestId)) {
            return null;
        }
        
        $cleanedId = preg_replace('/[^0-9]/', '', (string)$bestId);

        return $cleanedId ? (int)$cleanedId : null;
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

    /**
     * Internal helper for Groq API Communications
     */
    private static function callGroq($prompt, $isJson = false) {
        if (!defined('GROQ_API_KEY') || empty(GROQ_API_KEY)) return null;
        
        $url = "https://api.groq.com/openai/v1/chat/completions";
        $data = [
            "model" => "llama-3.3-70b-versatile",
            "messages" => [
                ["role" => "system", "content" => $isJson ? "You are a data extraction expert. Return ONLY valid JSON." : "You are an incident response coordinator. Return only the requested value."],
                ["role" => "user", "content" => $prompt]
            ],
            "temperature" => 0.1
        ];

        if ($isJson) {
            $data["response_format"] = ["type" => "json_object"];
        }

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . GROQ_API_KEY
        ]);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        
        $response = curl_exec($ch);
        curl_close($ch);

        $result = json_decode($response, true);
        return $result['choices'][0]['message']['content'] ?? null;
    }
}
