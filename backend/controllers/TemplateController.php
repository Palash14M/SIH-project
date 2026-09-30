<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../utils/Validator.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';

class TemplateController {
    public function listCategories(): void {
        $pdo = Database::getConnection();
        $stmt = $pdo->query("SELECT * FROM tender_categories ORDER BY id ASC");
        Response::success($stmt->fetchAll(), 'Categories retrieved');
    }

    public function createCategory(): void {
        AuthMiddleware::requireRoles(['MOSJE_ADMIN']);
        $input = getJsonInput();

        $validator = Validator::make($input, [
            'name' => 'required|string|min:3',
            'description' => 'string',
        ]);
        if ($validator->fails()) {
            Response::error('Validation failed', 422, $validator->errors());
        }

        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("INSERT INTO tender_categories (name, description, created_at) VALUES (:n, :d, :c)");
        $stmt->execute([
            ':n' => $input['name'],
            ':d' => $input['description'] ?? null,
            ':c' => date('Y-m-d H:i:s'),
        ]);

        Response::success(['id' => (int)$pdo->lastInsertId(), 'name' => $input['name']], 'Category created', 201);
    }

    public function listChecklistTemplates(): void {
        $pdo = Database::getConnection();
        $catId = $_GET['category_id'] ?? null;

        $sql = "
            SELECT t.*, c.name as category_name
            FROM quality_checklist_templates t
            JOIN tender_categories c ON t.category_id = c.id
        ";
        $params = [];
        if ($catId) {
            $sql .= " WHERE t.category_id = :cid";
            $params[':cid'] = (int)$catId;
        }
        $sql .= " ORDER BY t.category_id ASC, t.is_critical DESC, t.id ASC";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        Response::success($stmt->fetchAll(), 'Checklist templates retrieved');
    }

    public function createChecklistTemplate(): void {
        AuthMiddleware::requireRoles(['MOSJE_ADMIN']);
        $input = getJsonInput();

        $validator = Validator::make($input, [
            'category_id' => 'required|integer',
            'item_name' => 'required|string|min:3',
            'is_critical' => 'integer',
        ]);
        if ($validator->fails()) {
            Response::error('Validation failed', 422, $validator->errors());
        }

        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("
            INSERT INTO quality_checklist_templates (category_id, item_name, description, is_critical, created_at)
            VALUES (:cid, :name, :desc, :crit, :now)
        ");
        $stmt->execute([
            ':cid' => (int)$input['category_id'],
            ':name' => $input['item_name'],
            ':desc' => $input['description'] ?? null,
            ':crit' => !empty($input['is_critical']) ? 1 : 0,
            ':now' => date('Y-m-d H:i:s'),
        ]);

        Response::success(['id' => (int)$pdo->lastInsertId()], 'Checklist template created', 201);
    }
}

Router::add('GET', '/api/categories', [TemplateController::class, 'listCategories']);
Router::add('POST', '/api/categories', [TemplateController::class, 'createCategory']);
Router::add('GET', '/api/checklist-templates', [TemplateController::class, 'listChecklistTemplates']);
Router::add('POST', '/api/checklist-templates', [TemplateController::class, 'createChecklistTemplate']);
