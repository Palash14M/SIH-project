<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';

class NotificationController {
    public function list(): void {
        $user = AuthMiddleware::authenticate();
        $pdo = Database::getConnection();

        $stmt = $pdo->prepare("
            SELECT * FROM notifications 
            WHERE user_id = :uid 
            ORDER BY is_read ASC, id DESC
            LIMIT 50
        ");
        $stmt->execute([':uid' => $user['id']]);
        $notifications = $stmt->fetchAll();

        Response::success($notifications, 'Notifications retrieved');
    }

    public function unreadCount(): void {
        $user = AuthMiddleware::authenticate();
        $pdo = Database::getConnection();

        $stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = :uid AND is_read = 0");
        $stmt->execute([':uid' => $user['id']]);
        $count = (int)$stmt->fetchColumn();

        Response::success(['unread_count' => $count], 'Unread count retrieved');
    }

    public function markAsRead(array $params): void {
        $user = AuthMiddleware::authenticate();
        $id = (int)($params['id'] ?? 0);
        $pdo = Database::getConnection();

        $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE id = :id AND user_id = :uid");
        $stmt->execute([':id' => $id, ':uid' => $user['id']]);

        Response::success(null, 'Notification marked as read');
    }

    public function markAllAsRead(): void {
        $user = AuthMiddleware::authenticate();
        $pdo = Database::getConnection();

        $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = :uid");
        $stmt->execute([':uid' => $user['id']]);

        Response::success(null, 'All notifications marked as read');
    }
}

Router::add('GET', '/api/notifications', [NotificationController::class, 'list']);
Router::add('GET', '/api/notifications/unread-count', [NotificationController::class, 'unreadCount']);
Router::add('PUT', '/api/notifications/{id}/read', [NotificationController::class, 'markAsRead']);
Router::add('POST', '/api/notifications/mark-all-read', [NotificationController::class, 'markAllAsRead']);
