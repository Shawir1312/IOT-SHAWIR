<?php
/**
 * ShawirIOT - Widget CRUD API
 */
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../includes/auth.php';

if (!isLoggedIn()) jsonResponse(false, 'Unauthorized', null, 401);

$input  = json_decode(file_get_contents('php://input'), true) ?? [];
$action = $input['action'] ?? '';
$user   = currentUser();

// Verify CSRF
$token = $input['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (!hash_equals(csrfToken(), $token)) {
    jsonResponse(false, 'Token tidak valid.', null, 403);
}

switch ($action) {

    // ========================================================
    // CREATE WIDGET
    // ========================================================
    case 'create': {
        $dashboardId = (int)($input['dashboard_id'] ?? 0);
        $dashboard   = DB::row("SELECT * FROM dashboards WHERE id = ? AND user_id = ?", [$dashboardId, $user['id']]);
        if (!$dashboard) jsonResponse(false, 'Dashboard tidak ditemukan.', null, 404);

        $plan = getUserPlan($user['id']);
        $widgetCount = DB::count('widgets', 'dashboard_id = ?', [$dashboardId]);
        if ($widgetCount >= $plan['max_widgets_per_device']) {
            jsonResponse(false, "Batas widget paket {$plan['name']} adalah {$plan['max_widgets_per_device']}. Upgrade paket.", null, 403);
        }

        $validTypes = ['value_display','line_chart','bar_chart','gauge','button','slider','switch','led','terminal','label','map','radial_gauge'];
        $type = $input['type'] ?? '';
        if (!in_array($type, $validTypes)) jsonResponse(false, 'Tipe widget tidak valid.', null, 400);

        $id = DB::insert(
            "INSERT INTO widgets (dashboard_id, type, label, pin, color, text_color, min_value, max_value, unit, on_value, off_value, pos_x, pos_y, width, height)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)",
            [
                $dashboardId,
                $type,
                sanitize($input['label'] ?? 'Widget'),
                sanitize($input['pin']   ?? 'V0'),
                sanitize($input['color'] ?? '#6366f1'),
                sanitize($input['text_color'] ?? '#ffffff'),
                (float)($input['min_value'] ?? 0),
                (float)($input['max_value'] ?? 100),
                sanitize($input['unit']      ?? ''),
                sanitize($input['on_value']  ?? '1'),
                sanitize($input['off_value'] ?? '0'),
                (int)($input['pos_x'] ?? 0),
                (int)($input['pos_y'] ?? 0),
                max(1, min(12, (int)($input['width']  ?? 4))),
                max(1, (int)($input['height'] ?? 2)),
            ]
        );

        $widget = DB::row("SELECT * FROM widgets WHERE id = ?", [$id]);
        jsonResponse(true, 'Widget berhasil ditambahkan.', $widget);
    }

    // ========================================================
    // UPDATE WIDGET
    // ========================================================
    case 'update': {
        $widgetId = (int)($input['widget_id'] ?? 0);
        $widget   = DB::row(
            "SELECT w.* FROM widgets w JOIN dashboards d ON w.dashboard_id = d.id WHERE w.id = ? AND d.user_id = ?",
            [$widgetId, $user['id']]
        );
        if (!$widget) jsonResponse(false, 'Widget tidak ditemukan.', null, 404);

        DB::query(
            "UPDATE widgets SET label=?, pin=?, color=?, min_value=?, max_value=?, unit=?, on_value=?, off_value=?, width=?, height=? WHERE id=?",
            [
                sanitize($input['label']     ?? $widget['label']),
                sanitize($input['pin']       ?? $widget['pin']),
                sanitize($input['color']     ?? $widget['color']),
                (float)($input['min_value']  ?? $widget['min_value']),
                (float)($input['max_value']  ?? $widget['max_value']),
                sanitize($input['unit']      ?? $widget['unit']),
                sanitize($input['on_value']  ?? $widget['on_value']),
                sanitize($input['off_value'] ?? $widget['off_value']),
                max(1, min(12, (int)($input['width']  ?? $widget['width']))),
                max(1, (int)($input['height'] ?? $widget['height'])),
                $widgetId,
            ]
        );

        jsonResponse(true, 'Widget diperbarui.');
    }

    // ========================================================
    // DELETE WIDGET
    // ========================================================
    case 'delete': {
        $widgetId = (int)($input['widget_id'] ?? 0);
        $widget   = DB::row(
            "SELECT w.id FROM widgets w JOIN dashboards d ON w.dashboard_id = d.id WHERE w.id = ? AND d.user_id = ?",
            [$widgetId, $user['id']]
        );
        if (!$widget) jsonResponse(false, 'Widget tidak ditemukan.', null, 404);

        DB::query("DELETE FROM widgets WHERE id = ?", [$widgetId]);
        jsonResponse(true, 'Widget dihapus.');
    }

    default:
        jsonResponse(false, 'Action tidak dikenali.', null, 400);
}
