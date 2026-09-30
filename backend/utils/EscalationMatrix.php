<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/Audit.php';

class EscalationMatrix {
    public const CRORE_100 = 1000000000.0; // 100 Crore = 1,000,000,000 INR

    /**
     * Check budget overage and dispatch tiered alerts
     * Tiered severity:
     * - overage <= 5%: notify assigned Field Inspector
     * - overage > 5% and <= 10%: alert District Officer + State Officer
     * - overage > 10% OR overage amount > ₹100 crore: RED ALERT to MoSJE Admin + State Officer
     */
    public static function checkAndEscalate(int $tenderId, float $actualSpent): ?array {
        $pdo = Database::getConnection();

        $stmt = $pdo->prepare("
            SELECT t.*, d.name as district_name, s.name as state_name
            FROM tenders t
            JOIN districts d ON t.district_id = d.id
            JOIN states s ON t.state_id = s.id
            WHERE t.id = :id LIMIT 1
        ");
        $stmt->execute([':id' => $tenderId]);
        $tender = $stmt->fetch();

        if (!$tender) {
            return null;
        }

        $sanctioned = (float)$tender['sanctioned_amount'];
        if ($sanctioned <= 0) {
            return null;
        }

        $overageAmount = $actualSpent - $sanctioned;
        if ($overageAmount <= 0) {
            return [
                'has_overage' => false,
                'overage_amount' => 0.0,
                'overage_percentage' => 0.0,
                'tier' => 'NONE',
                'severity' => 'NORMAL',
                'notified_users' => [],
            ];
        }

        $overagePercentage = round(($overageAmount / $sanctioned) * 100.0, 2);

        // Determine tier
        $tier = 'TIER_1_INSPECTOR';
        $severity = 'INFO';
        $targetRoles = ['INSPECTOR'];

        if ($overagePercentage > 10.0 || $overageAmount > self::CRORE_100) {
            $tier = 'TIER_3_RED_ALERT';
            $severity = 'CRITICAL'; // RED ALERT
            $targetRoles = ['MOSJE_ADMIN', 'STATE_OFFICER', 'MASTER_ADMIN'];
        } elseif ($overagePercentage > 5.0 && $overagePercentage <= 10.0) {
            $tier = 'TIER_2_DISTRICT_STATE';
            $severity = 'WARNING';
            $targetRoles = ['DISTRICT_OFFICER', 'STATE_OFFICER'];
        } else {
            // <= 5%
            $tier = 'TIER_1_INSPECTOR';
            $severity = 'INFO';
            $targetRoles = ['INSPECTOR'];
        }

        // Format currency string
        $fmtOverage = '₹' . number_format($overageAmount, 2);
        $fmtSanctioned = '₹' . number_format($sanctioned, 2);
        $fmtSpent = '₹' . number_format($actualSpent, 2);

        $now = date('Y-m-d H:i:s');
        $notifiedUsers = [];

        // 1. Find assigned Field Inspectors
        $inspStmt = $pdo->prepare("SELECT inspector_id FROM tender_inspectors WHERE tender_id = :tid");
        $inspStmt->execute([':tid' => $tenderId]);
        $inspectorIds = $inspStmt->fetchAll(PDO::FETCH_COLUMN);

        // 2. Find District Officer
        $doId = $tender['district_officer_id'];
        if (!$doId) {
            $doStmt = $pdo->prepare("SELECT id FROM users WHERE role = 'DISTRICT_OFFICER' AND district_id = :did LIMIT 1");
            $doStmt->execute([':did' => $tender['district_id']]);
            $doId = $doStmt->fetchColumn() ?: null;
        }

        // 3. Find State Officer
        $soId = $tender['state_officer_id'];
        if (!$soId) {
            $soStmt = $pdo->prepare("SELECT id FROM users WHERE role = 'STATE_OFFICER' AND state_id = :sid LIMIT 1");
            $soStmt->execute([':sid' => $tender['state_id']]);
            $soId = $soStmt->fetchColumn() ?: null;
        }

        // 4. Find Admins
        $adminStmt = $pdo->query("SELECT id FROM users WHERE role IN ('MOSJE_ADMIN', 'MASTER_ADMIN')");
        $adminIds = $adminStmt->fetchAll(PDO::FETCH_COLUMN);

        // Map target users
        $recipientIds = [];
        if (in_array('INSPECTOR', $targetRoles, true)) {
            foreach ($inspectorIds as $iid) {
                $recipientIds[] = (int)$iid;
            }
        }
        if (in_array('DISTRICT_OFFICER', $targetRoles, true) && $doId) {
            $recipientIds[] = (int)$doId;
        }
        if (in_array('STATE_OFFICER', $targetRoles, true) && $soId) {
            $recipientIds[] = (int)$soId;
        }
        if (in_array('MOSJE_ADMIN', $targetRoles, true) || in_array('MASTER_ADMIN', $targetRoles, true)) {
            foreach ($adminIds as $aid) {
                $recipientIds[] = (int)$aid;
            }
        }

        // Ensure minimum notification requirement ("notifies every hierarchy level at minimum")
        // If it's a Red Alert, also inform DO and Inspector
        if ($tier === 'TIER_3_RED_ALERT') {
            if ($doId) $recipientIds[] = (int)$doId;
            foreach ($inspectorIds as $iid) $recipientIds[] = (int)$iid;
        } elseif ($tier === 'TIER_2_DISTRICT_STATE') {
            foreach ($inspectorIds as $iid) $recipientIds[] = (int)$iid;
        }

        $recipientIds = array_unique(array_filter($recipientIds));

        $title = ($severity === 'CRITICAL')
            ? "🚨 RED ALERT: Tender {$tender['tender_number']} Budget Overage"
            : (($severity === 'WARNING')
                ? "⚠️ Budget Overage Alert: Tender {$tender['tender_number']}"
                : "ℹ️ Budget Notice: Tender {$tender['tender_number']}");

        $message = "Budget overage detected on '{$tender['title']}'. " .
            "Sanctioned: {$fmtSanctioned}, Actual Spent: {$fmtSpent}. " .
            "Overage: {$fmtOverage} ({$overagePercentage}%). " .
            "Tier: {$tier}, Severity: {$severity}.";

        $notifStmt = $pdo->prepare("
            INSERT INTO notifications (user_id, title, message, type, entity_type, entity_id, created_at)
            VALUES (:uid, :title, :msg, :type, 'tender', :eid, :now)
        ");

        foreach ($recipientIds as $uid) {
            $notifStmt->execute([
                ':uid' => $uid,
                ':title' => $title,
                ':msg' => $message,
                ':type' => ($severity === 'CRITICAL' ? 'RED_ALERT' : ($severity === 'WARNING' ? 'WARNING' : 'INFO')),
                ':eid' => $tenderId,
                ':now' => $now,
            ]);
            $notifiedUsers[] = $uid;
        }

        // Log escalation in audit trail
        Audit::log(
            null,
            'SYSTEM',
            'BUDGET_OVERAGE_ESCALATION',
            'tenders',
            $tenderId,
            $fmtSanctioned,
            $fmtSpent,
            "Overage {$fmtOverage} ({$overagePercentage}%) escalated at {$tier} to " . count($recipientIds) . " recipients."
        );

        return [
            'has_overage' => true,
            'overage_amount' => $overageAmount,
            'overage_percentage' => $overagePercentage,
            'tier' => $tier,
            'severity' => $severity,
            'target_roles' => $targetRoles,
            'notified_users' => $notifiedUsers,
            'title' => $title,
            'message' => $message,
        ];
    }
}
