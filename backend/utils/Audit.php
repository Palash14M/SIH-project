<?php

require_once __DIR__ . '/../config/database.php';

class Audit {
    public static function log(
        ?int $userId,
        ?string $role,
        string $action,
        string $entityType,
        ?int $entityId,
        ?string $oldState = null,
        ?string $newState = null,
        ?string $reason = null,
        array $metadata = []
    ): void {
        try {
            $pdo = Database::getConnection();
            $stmt = $pdo->prepare("
                INSERT INTO audit_log (
                    user_id, role, action, entity_type, entity_id,
                    old_state, new_state, reason, metadata, created_at
                ) VALUES (
                    :user_id, :role, :action, :entity_type, :entity_id,
                    :old_state, :new_state, :reason, :metadata, :created_at
                )
            ");

            $stmt->execute([
                ':user_id' => $userId,
                ':role' => $role,
                ':action' => $action,
                ':entity_type' => $entityType,
                ':entity_id' => $entityId,
                ':old_state' => $oldState,
                ':new_state' => $newState,
                ':reason' => $reason,
                ':metadata' => !empty($metadata) ? json_encode($metadata) : null,
                ':created_at' => date('Y-m-d H:i:s'),
            ]);
        } catch (Throwable $e) {
            // Never break main flow if audit table is not yet migrated
            error_log("Audit log failure: " . $e->getMessage());
        }
    }
}
