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

// 1. ดึงการตั้งค่า Host & Port
$servername = getenv('MYSQLHOST')     ?: (getenv('DB_HOST') ?: 'localhost');
$port       = getenv('MYSQLPORT')     ?: (getenv('DB_PORT') ?: 3306);

// 2. ดึง Credential การเข้าใช้งาน
$username   = getenv('MYSQLUSER')     ?: (getenv('DB_USER') ?: 'root');
$password   = getenv('MYSQLPASSWORD') ?: (getenv('DB_PASS') ?: 'root');
$dbname     = getenv('MYSQLDATABASE') ?: (getenv('DB_NAME') ?: 'db_northwind');

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
