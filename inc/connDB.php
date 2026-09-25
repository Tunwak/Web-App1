<?php
/**
 * ─────────────────────────────────────────────────────────────────────────────
 * connDB.php — Database Connection Module (PDO)
 * ─────────────────────────────────────────────────────────────────────────────
 * [Full-stack Dev Note]:
 * ออกแบบให้รองรับ 2 สภาพแวดล้อมอัตโนมัติ (Zero-configuration Switch):
 * 1. Local Development (MAMP):
 *    - fallback เป็น localhost พอร์ต 3306, user/pass = root/root
 * 2. Production (Railway Cloud):
 *    - ตรวจจับ Environment Variables ของ Railway โดยตรง:
 *      MYSQLHOST, MYSQLPORT, MYSQLUSER, MYSQLPASSWORD, MYSQLDATABASE
 *    - และรองรับตัวแปร custom DB_HOST, DB_PORT เผื่อกรณีใช้ Cloud เจ้าอื่น
 * ─────────────────────────────────────────────────────────────────────────────
 */

// 1. Support either a single MySQL URL or the older environment-variable set
$env = function (array $names, $fallback = null) {
    foreach ($names as $name) {
        $value = getenv($name);
        if ($value !== false && $value !== null && $value !== '') {
            return $value;
        }
    }
    return $fallback;
};

$mysqlUrl = $env(['MYSQL_URL', 'MYSQL_PUBLIC_URL', 'DATABASE_URL']);

$servername = $env(['MYSQLHOST', 'MYSQL_HOST', 'DB_HOST'], 'mysql.railway.internal');
$port       = $env(['MYSQLPORT', 'MYSQL_PORT', 'DB_PORT'], 3306);
$username   = $env(['MYSQLUSER', 'MYSQL_USER', 'DB_USER', 'MYSQL_USERNAME'], 'root');
$password   = $env(['MYSQLPASSWORD', 'MYSQL_PASSWORD', 'DB_PASS', 'MYSQL_ROOT_PASSWORD'], 'root');
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

try {
    // กำหนด DSN พร้อมระบุ charset utf8mb4 เพื่อรองรับภาษาไทย 100%
    $dsn = "mysql:host={$servername};port={$port};dbname={$dbname};charset=utf8mb4";
    $conn = new PDO($dsn, $username, $password);

    // ตั้งค่า Error Mode ให้โยน Exception เมื่อ Query มีปัญหา (ง่ายต่อการ Debug)
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // ปิด Emulate Prepares เพื่อความปลอดภัยจาก SQL Injection ระดับ Native PDO
    $conn->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);

} catch (PDOException $e) {
    // กรณีเชื่อมต่อไม่สำเร็จ ให้ส่ง HTTP 500 กลับไปพร้อมข้อความ JSON
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database connection failed: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
    exit;
}
