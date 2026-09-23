<?php
/**
 * setup_database.php
 * Script for initializing tables and sample data from dbNorthwind.sql
 *
 * Supports:
 * - GET: show a simple HTML upload form
 * - POST (multipart/form-data): upload a SQL file as 'sqlfile' and import it
 * - If no upload provided, will try to load file from repository path
 */

require_once __DIR__ . '/../inc/connDB.php';

// If visited via browser (GET) show a minimal upload form
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><html><head><meta charset="utf-8"><title>Import dbNorthwind.sql</title></head><body style="font-family:Segoe UI,Arial;margin:32px;">';
    echo '<h2>Import dbNorthwind.sql</h2>';
    echo '<p>เลือกไฟล์ SQL แล้วกด Import เพื่อใส่ตัวอย่างข้อมูลลงฐานข้อมูล.</p>';
    echo '<form method="post" enctype="multipart/form-data">';
    echo '<input type="file" name="sqlfile" accept=".sql" required>'; 
    echo '<button type="submit">Import</button>';
    echo '</form>';
    echo '<hr><p>หรือเรียกสคริปต์โดยตรงถ้าวางไฟล์ไว้ในโฟลเดอร์โปรเจกต์.</p>';
    echo '</body></html>';
    exit;
}

header('Content-Type: application/json; charset=utf-8');

try {
    $uploaded = false;
    $sql = '';

    // 1) If file uploaded via form, use it
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_FILES['sqlfile']) && $_FILES['sqlfile']['error'] === UPLOAD_ERR_OK) {
        $tmpPath = $_FILES['sqlfile']['tmp_name'];
        $sql = file_get_contents($tmpPath);
        $uploaded = true;
    }

    // 2) If no upload, try repository path (legacy behavior)
    if (!$uploaded) {
        $sqlFile = __DIR__ . '/../../dbNorthwind.sql';
        if (!file_exists($sqlFile)) {
            echo json_encode([
                'success' => false,
                'message' => 'File dbNorthwind.sql not found at ' . $sqlFile . ' and no file was uploaded.'
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }
        $sql = file_get_contents($sqlFile);
    }

    if (trim($sql) === '') {
        echo json_encode(['success' => false, 'message' => 'SQL file is empty'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // Execute multi-query SQL. PDO::exec can execute semicolon-separated statements
    $conn->exec($sql);

    // Verify products table exists and count records
    $stmt = $conn->query("SELECT COUNT(*) AS total FROM tb_products");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'message' => 'Database initialized successfully!',
        'uploaded' => $uploaded,
        'total_products' => (int)($result['total'] ?? 0)
    ], JSON_UNESCAPED_UNICODE);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $t) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $t->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
