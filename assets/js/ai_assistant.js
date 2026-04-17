/**
 * QC-ALERTO Smart AI Assistant (Zero-Cost NLP Engine)
 * Handles real-time category suggestions and emergency detection.
 */
class QCAlertAI {
    constructor(config = {}) {
        this.categories = config.categories || []; // Map of ID to Name
        this.onSuggestion = config.onSuggestion || null;
        this.onUrgency = config.onUrgency || null;

        // Keyword dictionary with weights - Matched to IRMS Categories
        this.dictionary = {
            'Fire Incident': {
                keywords: ['sunog', 'apoy', 'usok', 'fire', 'smoke', 'sparks', 'sparking', 'spark', 'short circuit', 'electrical', 'kuryente', 'liyab', 'bumbero', 'flame', 'burning'],
                weight: 1.5
            },
            'Crime / Theft': {
                keywords: ['magnanakaw', 'holdap', 'thief', 'robbery', 'robber', 'baril', 'gun', 'weapon', 'saksak', 'stabbing', 'stab', 'away', 'gulo', 'fight', 'riot', 'rumble', 'nakawan', 'bugbog', 'assault', 'snatch', 'snatcher', 'akyat bahay', 'carnap', 'nanakaw'],
                weight: 1.5
            },
            'Medical Emergency': {
                keywords: ['hinimatay', 'fainted', 'unconscious', 'dugo', 'blood', 'bleeding', 'sugat', 'injury', 'accident', 'aksidente', 'nabangga', 'pagkabangga', 'heart attack', 'nahihilo', 'dizzy', 'preggy', 'buntis', 'labor', 'stroke', 'seizure', 'emergency', 'nagcollapse'],
                weight: 1.5
            },
            'Flood': {
                keywords: ['baha', 'flood', 'bagyo', 'typhoon', 'storm', 'habagat', 'lubog sa tubig', 'clogged drain'],
                weight: 1.2
            },
            'Power Outage': {
                keywords: ['tubig', 'leak', 'tulo', 'linya', 'kable', 'poste', 'post', 'meralco', 'maynilad', 'manila water', 'walang kuryente', 'blackout', 'brownout', 'no power', 'putol na kable'],
                weight: 1.1
            },
            'Road Accident': {
                keywords: ['traffic', 'trapik', 'road', 'daan', 'lubak', 'pothole', 'harang', 'obstruction', 'illegal parking', 'parked', 'tow', 'towing', 'banggaan', 'car crash', 'motorcycle accident'],
                weight: 1.0
            },
            'Missing Person': {
                keywords: ['nawawala', 'missing', 'lost person', 'hindi umuwi', 'missing child', 'nawala'],
                weight: 1.5
            }
        };

        // Emergency keywords that trigger "Critical" suggestion
        this.emergencyKeywords = ['patay', 'death', 'dead', 'mass casualty', 'active shooter', 'major fire', 'trapped', 'hindi makahinga', 'breathless', 'critical condition', 'agaw buhay'];
    }

    analyze(text) {
        if (!text || text.length < 3) return null;
        const input = text.toLowerCase();
        
        let scores = {};
        let isEmergency = false;

        // Check for emergency keywords
        for (const word of this.emergencyKeywords) {
            if (input.includes(word)) {
                isEmergency = true;
                break;
            }
        }

        // Score categories
        for (const [catName, data] of Object.entries(this.dictionary)) {
            scores[catName] = 0;
            for (const keyword of data.keywords) {
                if (input.includes(keyword)) {
                    scores[catName] += data.weight;
                }
            }
        }

        // Get highest score
        let bestMatch = null;
        let maxScore = 0;
        for (const [catName, score] of Object.entries(scores)) {
            if (score > maxScore) {
                maxScore = score;
                bestMatch = catName;
            }
        }

        return {
            category: bestMatch,
            confidence: maxScore,
            isEmergency: isEmergency
        };
    }

    /**
     * Performs server-side LLM analyze for maximum accuracy.
     */
    async analyzeAccurate(title, description) {
        if (!title || !description || title.length < 5 || description.length < 10) return null;

        const formData = new FormData();
        formData.append('title', title);
        formData.append('description', description);

        try {
            const response = await fetch('/irms/ajax/ai_analyze.php', {
                method: 'POST',
                body: formData
            });
            return await response.json();
        } catch (error) {
            console.error('AI Analysis Error:', error);
            // Fallback to local keyword analysis if server is down
            const result = this.analyze(title + ' ' + description);
            return {
                category_id: null, // We can't map local names to IDs easily here without more config
                severity: result.isEmergency ? 'critical' : 'medium',
                confidence: result.confidence / 10,
                is_local_fallback: true
            };
        }
    }

    /**
     * Gets human-readable address from coordinates
     */
    async reverseGeocode(lat, lng) {
        try {
            const response = await fetch(`/irms/ajax/reverse_geocode.php?lat=${lat}&lng=${lng}`);
            return await response.json();
        } catch (error) {
            console.error('Reverse Geocode Error:', error);
            return { address: lat.toFixed(5) + ', ' + lng.toFixed(5) };
        }
    }
}

// Global instance for browser
window.QCAlertAI = QCAlertAI;
