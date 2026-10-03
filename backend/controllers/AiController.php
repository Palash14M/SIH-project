<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../services/GeminiService.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';

class AiController {
    /**
     * GET /api/ai/status
     * Returns Google AI Studio / Gemini connection and health state.
     */
    public function status(): void {
        $result = GeminiService::healthCheck();
        if ($result['connected']) {
            Response::success($result, 'Google AI Studio connection is operational.');
        } else {
            Response::error($result['message'] ?? 'Google AI Studio connection error', 503, $result);
        }
    }

    /**
     * POST /api/ai/chat
     * AI Copilot Assistant for MoSJE inspections, rules, and tender queries.
     */
    public function chat(): void {
        $input = getJsonInput();
        $message = trim($input['message'] ?? $input['prompt'] ?? '');
        $history = is_array($input['history'] ?? null) ? $input['history'] : [];

        if (empty($message)) {
            Response::badRequest('The message parameter is required.');
        }

        try {
            $reply = GeminiService::askAssistant($message, $history);
            Response::success([
                'reply' => $reply,
                'model' => GeminiService::getModel(),
                'timestamp' => date('Y-m-d H:i:s'),
            ], 'AI response generated successfully.');
        } catch (Throwable $e) {
            Response::error('Failed to generate AI response: ' . $e->getMessage(), 500);
        }
    }

    /**
     * POST /api/ai/audit-inspection/{id}
     * AI Inspection Evidence & Quality Compliance Audit.
     */
    /**
     * POST /api/ai/audit-inspection/{id}
     * AI Inspection Evidence & Quality Compliance Audit.
     */
    public function auditInspection(array $params): void {
        $user = AuthMiddleware::optionalAuth();
        $id = (int)($params['id'] ?? 0);
        $pdo = Database::getConnection();

        $stmt = $pdo->prepare("
            SELECT i.*, t.tender_number, t.title as tender_title,
                   u.name as inspector_name
            FROM inspections i
            JOIN tenders t ON i.tender_id = t.id
            JOIN users u ON i.inspector_id = u.id
            WHERE i.id = :id LIMIT 1
        ");
        $stmt->execute([':id' => $id]);
        $inspection = $stmt->fetch();

        if (!$inspection) {
            Response::notFound('Inspection not found.');
        }

        // Quality checks
        $qStmt = $pdo->prepare("
            SELECT qc.*, qct.item_name, qct.is_critical 
            FROM quality_checks qc
            JOIN quality_checklist_templates qct ON qc.template_id = qct.id
            WHERE qc.inspection_id = :iid
        ");
        $qStmt->execute([':iid' => $id]);
        $qualityChecks = $qStmt->fetchAll();

        // Evidence items
        $eStmt = $pdo->prepare("SELECT * FROM inspection_items WHERE inspection_id = :iid");
        $eStmt->execute([':iid' => $id]);
        $evidenceItems = $eStmt->fetchAll();

        try {
            $audit = GeminiService::auditInspection($inspection, $qualityChecks, $evidenceItems);
            Response::success([
                'inspection_id' => $id,
                'audit' => $audit,
                'model' => GeminiService::getModel(),
                'timestamp' => date('Y-m-d H:i:s'),
            ], 'AI inspection audit completed.');
        } catch (Throwable $e) {
            Response::error('AI Inspection Audit failed: ' . $e->getMessage(), 500);
        }
    }

    /**
     * POST /api/ai/audit-tender/{id}
     * AI Tender Risk, Budget Variance & Delay Probability Analysis.
     */
    public function auditTender(array $params): void {
        $user = AuthMiddleware::optionalAuth();
        $id = (int)($params['id'] ?? 0);
        $pdo = Database::getConnection();

        $stmt = $pdo->prepare("
            SELECT t.*, c.name as contractor_name
            FROM tenders t
            LEFT JOIN contractors c ON t.contractor_id = c.id
            WHERE t.id = :id LIMIT 1
        ");
        $stmt->execute([':id' => $id]);
        $tender = $stmt->fetch();

        if (!$tender) {
            Response::notFound('Tender not found.');
        }

        $mStmt = $pdo->prepare("SELECT * FROM tender_milestones WHERE tender_id = :tid ORDER BY milestone_order ASC");
        $mStmt->execute([':tid' => $id]);
        $milestones = $mStmt->fetchAll();

        try {
            $analysis = GeminiService::evaluateTenderRisk($tender, $milestones);
            Response::success([
                'tender_id' => $id,
                'analysis' => $analysis,
                'model' => GeminiService::getModel(),
                'timestamp' => date('Y-m-d H:i:s'),
            ], 'AI tender risk analysis completed.');
        } catch (Throwable $e) {
            Response::error('AI Tender Risk Analysis failed: ' . $e->getMessage(), 500);
        }
    }
}

// Register AI Routes in Router
Router::add('GET', '/api/ai/status', [AiController::class, 'status']);
Router::add('GET', '/api/ai/health', [AiController::class, 'status']);
Router::add('POST', '/api/ai/chat', [AiController::class, 'chat']);
Router::add('POST', '/api/ai/audit-inspection/{id}', [AiController::class, 'auditInspection']);
Router::add('POST', '/api/ai/audit-tender/{id}', [AiController::class, 'auditTender']);

