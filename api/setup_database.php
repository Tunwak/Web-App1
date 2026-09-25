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

$env = function (array $names, $fallback = null) {
    foreach ($names as $name) {
        $value = getenv($name);

        if ($value === false || $value === null || $value === '') {
            $value = $_ENV[$name] ?? $_SERVER[$name] ?? null;
        }

        if ($value !== null && $value !== '') {
            return $value;
        }
    }
    return $fallback;
};

$mysqlUrl = $env(['MYSQL_URL', 'DATABASE_URL']);

$servername = $env(['MYSQLHOST', 'MYSQL_HOST', 'DB_HOST'], 'mysql.railway.internal');
$port       = $env(['MYSQLPORT', 'MYSQL_PORT', 'DB_PORT'], 3306);
$username   = $env(['MYSQLUSER', 'MYSQL_USER', 'DB_USER'], 'root');
$password   = $env(['MYSQLPASSWORD', 'MYSQL_PASSWORD', 'DB_PASS'], 'CrgcBBXtlSBeZwyFcXbEwOvWUzpRlNSf');
$dbname     = $env(['MYSQLDATABASE', 'MYSQL_DATABASE', 'DB_NAME'], 'railway');

if ($mysqlUrl) {
    $parsedUrl = parse_url($mysqlUrl);
    if ($parsedUrl && !empty($parsedUrl['host'])) {
        $servername = $parsedUrl['host'];
        $port       = $parsedUrl['port'] ?? $port;
        $username   = $parsedUrl['user'] ?? $username;
        $password   = $parsedUrl['pass'] ?? $password;
        $dbname     = ltrim($parsedUrl['path'] ?? '', '/') ?: $dbname;
    }
}

$dsnNoDb = "mysql:host={$servername};port={$port};charset=utf8mb4";
$dsnWithDb = "mysql:host={$servername};port={$port};dbname={$dbname};charset=utf8mb4";

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

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_FILES['sqlfile']) && $_FILES['sqlfile']['error'] === UPLOAD_ERR_OK) {
        $tmpPath = $_FILES['sqlfile']['tmp_name'];
        $sql = file_get_contents($tmpPath);
        $uploaded = true;
    }

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

    $dbCheck = new PDO($dsnNoDb, $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    $dbCheck->exec("CREATE DATABASE IF NOT EXISTS `{$dbname}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $conn = new PDO($dsnWithDb, $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    $conn->exec($sql);

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
