<?php
/**
 * ShawirIOT - Dashboard API (Save layout, Update dashboard config)
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
    // SAVE LAYOUT (Drag & Drop + Resize positions)
    // ========================================================
    case 'save_layout': {
        $dashboardId = (int)($input['dashboard_id'] ?? 0);
        $dashboard   = DB::row("SELECT * FROM dashboards WHERE id = ? AND user_id = ?", [$dashboardId, $user['id']]);
        if (!$dashboard) jsonResponse(false, 'Dashboard tidak ditemukan.', null, 404);

        $layout = $input['layout'] ?? [];
        if (!is_array($layout)) jsonResponse(false, 'Data layout tidak valid.', null, 400);

        // Update each widget's coordinates
        $pdo = DB::getInstance();
        $stmt = $pdo->prepare("UPDATE widgets SET pos_x = ?, pos_y = ?, width = ?, height = ? WHERE id = ? AND dashboard_id = ?");

        foreach ($layout as $item) {
            $wid = (int)($item['id'] ?? 0);
            $x   = max(0, min(11, (int)($item['pos_x'] ?? 0)));
            $y   = max(0, (int)($item['pos_y'] ?? 0));
            $w   = max(1, min(12, (int)($item['width'] ?? 4)));
            $h   = max(1, (int)($item['height'] ?? 2));

            if ($wid > 0) {
                $stmt->execute([$x, $y, $w, $h, $wid, $dashboardId]);
            }
        }

        jsonResponse(true, 'Layout dashboard berhasil disimpan!');
    }

    // ========================================================
    // UPDATE DASHBOARD SETTINGS (Color, Title)
    // ========================================================
    case 'update_settings': {
        $dashboardId = (int)($input['dashboard_id'] ?? 0);
        $dashboard   = DB::row("SELECT * FROM dashboards WHERE id = ? AND user_id = ?", [$dashboardId, $user['id']]);
        if (!$dashboard) jsonResponse(false, 'Dashboard tidak ditemukan.', null, 404);

        $title   = sanitize($input['title'] ?? $dashboard['title']);
        $bgColor = sanitize($input['bg_color'] ?? $dashboard['bg_color']);

        DB::query("UPDATE dashboards SET title = ?, bg_color = ? WHERE id = ?", [$title, $bgColor, $dashboardId]);
        jsonResponse(true, 'Pengaturan dashboard disimpan.');
    }

    default:
        jsonResponse(false, 'Action tidak dikenali.', null, 400);
}
