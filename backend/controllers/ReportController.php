<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../services/ReportService.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';

class ReportController {
    public function downloadInspectionPdf(array $params): void {
        $user = AuthMiddleware::authenticate();
        $id = (int)($params['id'] ?? 0);
        $pdo = Database::getConnection();

        $stmt = $pdo->prepare("
            SELECT i.*, t.tender_number, t.title as tender_title, t.sanctioned_amount,
                   u.name as inspector_name, v.name as verified_by_name
            FROM inspections i
            JOIN tenders t ON i.tender_id = t.id
            JOIN users u ON i.inspector_id = u.id
            LEFT JOIN users v ON i.verified_by = v.id
            WHERE i.id = :id LIMIT 1
        ");
        $stmt->execute([':id' => $id]);
        $ins = $stmt->fetch();

        if (!$ins) {
            Response::notFound('Inspection not found');
        }

        // Fetch evidence items
        $eStmt = $pdo->prepare("SELECT * FROM inspection_items WHERE inspection_id = :iid ORDER BY id ASC");
        $eStmt->execute([':iid' => $id]);
        $ins['evidence_items'] = $eStmt->fetchAll();

        // Fetch quality checks
        $qStmt = $pdo->prepare("
            SELECT qc.*, qct.item_name, qct.is_critical 
            FROM quality_checks qc
            JOIN quality_checklist_templates qct ON qc.template_id = qct.id
            WHERE qc.inspection_id = :iid
        ");
        $qStmt->execute([':iid' => $id]);
        $ins['quality_checks'] = $qStmt->fetchAll();

        $reportsDir = dirname(__DIR__) . '/storage/reports';
        $pdfPath = $reportsDir . '/inspection_' . $id . '.pdf';

        ReportService::generateInspectionPdf($ins, $pdfPath);

        if (!file_exists($pdfPath)) {
            Response::error('Failed to generate PDF report', 500);
        }

        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="Inspection_Report_' . $id . '.pdf"');
        header('Content-Length: ' . filesize($pdfPath));
        readfile($pdfPath);
        exit;
    }

    public function exportTendersCsv(): void {
        $user = AuthMiddleware::optionalAuth();
        $pdo = Database::getConnection();

        $sql = "
            SELECT t.*, c.name as category_name, d.name as district_name, s.name as state_name,
                   co.name as contractor_name
            FROM tenders t
            JOIN tender_categories c ON t.category_id = c.id
            JOIN districts d ON t.district_id = d.id
            JOIN states s ON t.state_id = s.id
            JOIN contractors co ON t.contractor_id = co.id
            ORDER BY t.id DESC
        ";
        $stmt = $pdo->query($sql);
        $tenders = $stmt->fetchAll();

        $reportsDir = dirname(__DIR__) . '/storage/reports';
        $csvPath = $reportsDir . '/tenders_export_' . date('Ymd_His') . '.csv';

        ReportService::generateTendersCsv($tenders, $csvPath);

        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="MoSJE_Tenders_Export.csv"');
        header('Content-Length: ' . filesize($csvPath));
        readfile($csvPath);
        exit;
    }

    public function dashboardSummary(): void {
        $user = AuthMiddleware::optionalAuth();
        $pdo = Database::getConnection();

        // 1. Tenders by Status
        $statusCounts = $pdo->query("
            SELECT status, COUNT(*) as count 
            FROM tenders 
            GROUP BY status
        ")->fetchAll(PDO::FETCH_KEY_PAIR);

        // 2. Inspections by Status
        $inspCounts = $pdo->query("
            SELECT status, COUNT(*) as count 
            FROM inspections 
            GROUP BY status
        ")->fetchAll(PDO::FETCH_KEY_PAIR);

        // 3. Variance & Delay Flags
        $varianceTenders = (int)$pdo->query("SELECT COUNT(*) FROM tenders WHERE variance_flag = 1")->fetchColumn();
        $delayedTenders = (int)$pdo->query("SELECT COUNT(*) FROM tenders WHERE delay_flag = 1")->fetchColumn();

        // 4. Overdue Re-inspections
        $today = date('Y-m-d');
        $overdueReinspections = (int)$pdo->query("
            SELECT COUNT(*) FROM reinspections 
            WHERE status = 'SCHEDULED' AND scheduled_date < '{$today}'
        ")->fetchColumn();

        // 5. Coverage by District
        $districtCoverage = $pdo->query("
            SELECT d.name as district, COUNT(t.id) as tender_count,
                   AVG(t.progress_percentage) as avg_progress,
                   SUM(t.sanctioned_amount) as total_sanctioned
            FROM districts d
            LEFT JOIN tenders t ON d.id = t.district_id
            GROUP BY d.id
        ")->fetchAll();

        Response::success([
            'tenders_by_status' => $statusCounts,
            'inspections_by_status' => $inspCounts,
            'variance_alerts' => $varianceTenders,
            'delayed_tenders' => $delayedTenders,
            'overdue_reinspections' => $overdueReinspections,
            'district_coverage' => $districtCoverage,
        ], 'Dashboard summary metrics retrieved');
    }
}

Router::add('GET', '/api/reports/inspection/{id}/pdf', [ReportController::class, 'downloadInspectionPdf']);
Router::add('GET', '/api/reports/tenders/csv', [ReportController::class, 'exportTendersCsv']);
Router::add('GET', '/api/dashboard/summary', [ReportController::class, 'dashboardSummary']);
