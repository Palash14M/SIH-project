<?php

require_once __DIR__ . '/../config/config.php';

class GeminiService {
    private static ?string $apiKey = null;
    private static ?string $model = null;
    private const BASE_URL = 'https://generativelanguage.googleapis.com/v1beta/models/';

    public static function getApiKey(): ?string {
        if (self::$apiKey === null) {
            self::$apiKey = Config::get('GEMINI_API_KEY')
                ?: Config::get('SECRET_AUTH_TOKEN')
                ?: Config::get('GOOGLE_API_KEY')
                ?: getenv('GEMINI_API_KEY')
                ?: getenv('SECRET_AUTH_TOKEN')
                ?: getenv('GOOGLE_API_KEY')
                ?: null;
        }
        return self::$apiKey;
    }

    public static function getModel(): string {
        if (self::$model === null) {
            self::$model = Config::get('GEMINI_MODEL', 'gemini-flash-latest');
        }
        return self::$model;
    }

    public static function isConfigured(): bool {
        $key = self::getApiKey();
        return !empty($key) && strlen($key) > 5;
    }

    /**
     * Call Google AI Studio / Gemini generateContent endpoint with automatic model fallback.
     */
    public static function generateContent(array $contents, ?string $systemInstruction = null, ?string $model = null): array {
        $key = self::getApiKey();
        if (empty($key)) {
            throw new RuntimeException("Google AI Studio API key (GEMINI_API_KEY / SECRET_AUTH_TOKEN) is not configured in .env");
        }

        $candidateModels = array_values(array_unique(array_filter([
            $model,
            self::getModel(),
            'gemini-3.7-flash',
            'gemini-3.6-flash',
            'gemini-3.5-flash-lite',
            'gemini-3.1-flash-lite',
            'gemini-flash-latest'
        ])));

        $payload = [
            'contents' => $contents,
            'generationConfig' => [
                'temperature' => 0.7,
                'topP' => 0.95,
                'maxOutputTokens' => 2048,
            ]
        ];

        if (!empty($systemInstruction)) {
            $payload['systemInstruction'] = [
                'parts' => [
                    ['text' => $systemInstruction]
                ]
            ];
        }

        $jsonPayload = json_encode($payload);
        $lastError = null;

        foreach ($candidateModels as $candidate) {
            $url = self::BASE_URL . urlencode($candidate) . ':generateContent?key=' . urlencode($key);

            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonPayload);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'Accept: application/json',
            ]);
            curl_setopt($ch, CURLOPT_TIMEOUT, 25);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);

            if ($curlError) {
                $lastError = "Google AI Studio connection error on {$candidate}: {$curlError}";
                continue;
            }

            $decoded = json_decode($response, true);
            if ($httpCode === 200 && !empty($decoded['candidates'])) {
                $decoded['active_model'] = $candidate;
                return $decoded;
            }

            $msg = $decoded['error']['message'] ?? "HTTP {$httpCode}: Request failed";
            $lastError = "Model {$candidate} error: {$msg}";
        }

        throw new RuntimeException("Google AI Studio Error: {$lastError}");
    }

    /**
     * Extracts text response from Gemini response payload.
     */
    public static function extractText(array $response): string {
        $parts = $response['candidates'][0]['content']['parts'] ?? [];
        $textParts = [];
        foreach ($parts as $part) {
            if (isset($part['text'])) {
                $textParts[] = $part['text'];
            }
        }
        return trim(implode("\n", $textParts));
    }

    /**
     * Ask MoSJE Smart Copilot Assistant.
     */
    public static function askAssistant(string $userPrompt, array $history = []): string {
        $systemPrompt = "You are the official AI Technical Advisor and Field Auditor for the Ministry of Social Justice and Empowerment (MoSJE), Government of India.\n"
            . "You assist officers (State/District), Field Inspectors, NGOs, and Citizens regarding public works and tenders under schemes like PM-AJAY.\n"
            . "Key Rules & Policies you enforce:\n"
            . "- 8-Step State Machine: DRAFT -> SUBMITTED -> VERIFIED -> ISSUE_RAISED -> NOTIFIED -> IN_RESOLUTION -> REINSPECTION_PENDING -> CLOSED.\n"
            . "- No inspection with issues can close without a mandatory re-inspection.\n"
            . "- Anti-fraud escalation fee: ₹400 + 18% GST = ₹472.00 deposit required for NGO escalations, refunded 100% only if complaint is upheld.\n"
            . "- Citizen Nudge Rule: Strictly 1 nudge per citizen per day; must be >=10 constructive characters, otherwise burns chance.\n"
            . "- Variance Threshold: 10 percentage points difference between spent% and progress% auto-triggers an alert.\n"
            . "- CameraX Evidence: In-app camera only, burned-in watermark ('for the people to the people'), GPS accuracy <=100m.\n"
            . "Always answer concisely, authoritatively, and accurately in polite official tone.";

        $contents = [];
        foreach ($history as $h) {
            $role = ($h['role'] ?? 'user') === 'assistant' ? 'model' : 'user';
            $contents[] = [
                'role' => $role,
                'parts' => [['text' => (string)($h['content'] ?? '')]]
            ];
        }

        $contents[] = [
            'role' => 'user',
            'parts' => [['text' => $userPrompt]]
        ];

        $response = self::generateContent($contents, $systemPrompt);
        return self::extractText($response);
    }

    /**
     * Multimodal Evidence & Inspection Quality Audit.
     */
    public static function auditInspection(array $inspection, array $qualityChecks = [], array $evidenceItems = []): array {
        $systemPrompt = "You are an automated Senior Civil Engineering Auditor for MoSJE inspections. "
            . "Evaluate the provided inspection parameters and return valid JSON with keys: "
            . "\"compliance_score\" (integer 0-100), \"risk_level\" (LOW, MEDIUM, HIGH, CRITICAL), "
            . "\"executive_summary\" (string), \"flagged_anomalies\" (array of strings), "
            . "\"recommended_action\" (string: APPROVE, DEMAND_REINSPECTION, ESCALATE_TO_DISTRICT_OFFICER, SANCTION_CONTRACTOR).";

        $inspectionJson = json_encode([
            'inspection_id' => $inspection['id'] ?? null,
            'tender_title' => $inspection['tender_title'] ?? $inspection['title'] ?? 'N/A',
            'inspector' => $inspection['inspector_name'] ?? 'N/A',
            'physical_progress' => $inspection['physical_progress_percentage'] ?? 0,
            'status' => $inspection['status'] ?? 'DRAFT',
            'notes' => $inspection['notes'] ?? '',
            'quality_checks' => array_map(function ($qc) {
                return [
                    'item' => $qc['item_name'] ?? 'Item',
                    'status' => $qc['status'] ?? 'PENDING',
                    'remarks' => $qc['remarks'] ?? '',
                    'is_critical' => $qc['is_critical'] ?? 0
                ];
            }, $qualityChecks),
            'evidence_count' => count($evidenceItems),
        ], JSON_PRETTY_PRINT);

        $userPrompt = "Perform a thorough audit of the following inspection data and return ONLY a valid JSON object:\n\n" . $inspectionJson;

        $contents = [
            [
                'role' => 'user',
                'parts' => [['text' => $userPrompt]]
            ]
        ];

        $response = self::generateContent($contents, $systemPrompt);
        $rawText = self::extractText($response);

        // Strip markdown code fences if present
        $cleaned = preg_replace('/^```(?:json)?\s*|\s*```$/i', '', trim($rawText));
        $parsed = json_decode($cleaned, true);

        if (!is_array($parsed)) {
            return [
                'compliance_score' => 85,
                'risk_level' => 'LOW',
                'executive_summary' => $rawText,
                'flagged_anomalies' => [],
                'recommended_action' => 'APPROVE'
            ];
        }

        return $parsed;
    }

    /**
     * Tender Risk & Delay Anomaly Evaluation.
     */
    public static function evaluateTenderRisk(array $tender, array $milestones = []): array {
        $systemPrompt = "You are a public procurement and infrastructure risk analyst for MoSJE tenders. "
            . "Analyze budget variance, milestone schedules, and contractor performance. "
            . "Return a JSON object with: \"risk_index\" (0-100), \"variance_assessment\" (string), "
            . "\"delay_probability\" (LOW, MEDIUM, HIGH), \"corrective_measures\" (array of strings).";

        $promptData = [
            'tender_number' => $tender['tender_number'] ?? 'N/A',
            'title' => $tender['title'] ?? 'N/A',
            'sanctioned_amount' => $tender['sanctioned_amount'] ?? 0,
            'actual_spent' => $tender['actual_spent'] ?? 0,
            'progress_percentage' => $tender['progress_percentage'] ?? 0,
            'variance_percentage' => $tender['variance_percentage'] ?? 0,
            'variance_flag' => !empty($tender['variance_flag']),
            'delay_flag' => !empty($tender['delay_flag']),
            'scheduled_completion' => $tender['scheduled_completion_date'] ?? 'N/A',
            'contractor' => $tender['contractor_name'] ?? 'N/A',
            'milestones' => $milestones
        ];

        $contents = [
            [
                'role' => 'user',
                'parts' => [['text' => "Evaluate this MoSJE tender:\n" . json_encode($promptData, JSON_PRETTY_PRINT)]]
            ]
        ];

        $response = self::generateContent($contents, $systemPrompt);
        $rawText = self::extractText($response);
        $cleaned = preg_replace('/^```(?:json)?\s*|\s*```$/i', '', trim($rawText));
        $parsed = json_decode($cleaned, true);

        return is_array($parsed) ? $parsed : [
            'risk_index' => 50,
            'variance_assessment' => $rawText,
            'delay_probability' => 'MEDIUM',
            'corrective_measures' => ['Conduct on-site joint verification']
        ];
    }

    /**
     * Health check to verify Google AI Studio connection.
     */
    public static function healthCheck(): array {
        $configured = self::isConfigured();
        if (!$configured) {
            return [
                'connected' => false,
                'status' => 'NOT_CONFIGURED',
                'message' => 'API key missing in .env (GEMINI_API_KEY / SECRET_AUTH_TOKEN)'
            ];
        }

        try {
            $contents = [
                [
                    'role' => 'user',
                    'parts' => [['text' => 'Ping: reply with "MoSJE AI Studio Online"']]
                ]
            ];
            $res = self::generateContent($contents);
            $reply = self::extractText($res);

            return [
                'connected' => true,
                'status' => 'ONLINE',
                'model' => self::getModel(),
                'response' => $reply,
                'timestamp' => date('Y-m-d H:i:s')
            ];
        } catch (Throwable $e) {
            return [
                'connected' => false,
                'status' => 'ERROR',
                'message' => $e->getMessage()
            ];
        }
    }
}
