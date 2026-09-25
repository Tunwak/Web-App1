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

$dsnNoDb = "mysql:host={$servername};port={$port};charset=utf8mb4";
$dsnWithDb = "mysql:host={$servername};port={$port};dbname={$dbname};charset=utf8mb4";

function buildFallbackNorthwindSql(): string
{
    return <<<'SQL'
CREATE TABLE IF NOT EXISTS `tb_categories` (
    `i_CategoryID` INT NOT NULL AUTO_INCREMENT,
    `c_CategoryName` VARCHAR(255) NOT NULL,
    PRIMARY KEY (`i_CategoryID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `tb_suppliers` (
    `i_SupplierID` INT NOT NULL AUTO_INCREMENT,
    `c_SupplierName` VARCHAR(255) NOT NULL,
    PRIMARY KEY (`i_SupplierID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `tb_products` (
    `i_ProductID` INT NOT NULL AUTO_INCREMENT,
    `c_ProductName` VARCHAR(255) NOT NULL,
    `i_SupplierID` INT NOT NULL,
    `i_CategoryID` INT NOT NULL,
    `c_Unit` VARCHAR(255) NOT NULL,
    `i_Price` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    PRIMARY KEY (`i_ProductID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `tb_categories` (`i_CategoryID`, `c_CategoryName`) VALUES
(1, 'Beverages'),
(2, 'Condiments'),
(3, 'Confections'),
(4, 'Dairy Products'),
(5, 'Grains/Cereals'),
(6, 'Meat/Poultry'),
(7, 'Produce'),
(8, 'Seafood');

INSERT IGNORE INTO `tb_suppliers` (`i_SupplierID`, `c_SupplierName`) VALUES
(1, 'Exotic Liquids'),
(2, 'New Orleans Cajun Delights'),
(3, 'Grandma Kelly''s Homestead'),
(4, 'Tokyo Traders'),
(5, 'Cooperativa de Quesos ''Las Cabras''');

INSERT IGNORE INTO `tb_products` (`i_ProductID`, `c_ProductName`, `i_SupplierID`, `i_CategoryID`, `c_Unit`, `i_Price`) VALUES
(1, 'Chai', 1, 1, '10 boxes x 20 bags', 18.00),
(2, 'Chang', 1, 1, '24 - 12 oz bottles', 19.00),
(3, 'Aniseed Syrup', 1, 2, '12 - 550 ml bottles', 10.00),
(4, 'Chef Anton''s Cajun Seasoning', 2, 2, '48 - 6 oz jars', 22.00),
(5, 'Chef Anton''s Gumbo Mix', 2, 2, '36 boxes', 21.35),
(6, 'Grandma''s Boysenberry Spread', 3, 2, '12 - 8 oz jars', 25.00),
(7, 'Uncle Bob''s Organic Dried Pears', 3, 7, '12 - 1 lb pkgs.', 30.00),
(8, 'Northwoods Cranberry Sauce', 3, 2, '12 - 12 oz jars', 40.00),
(9, 'Mishi Kobe Niku', 4, 6, '18 - 500 g pkgs.', 97.00),
(10, 'Ikura', 4, 8, '12 - 200 ml jars', 31.00);
SQL;
}

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
        $sqlFileCandidates = [
            __DIR__ . '/../dbNorthwind.sql',
            __DIR__ . '/dbNorthwind.sql',
            __DIR__ . '/../../dbNorthwind.sql',
        ];

        $sqlFile = null;
        foreach ($sqlFileCandidates as $candidate) {
            if (file_exists($candidate)) {
                $sqlFile = $candidate;
                break;
            }
        }

        if ($sqlFile !== null) {
            $sql = file_get_contents($sqlFile);
        } else {
            $sql = buildFallbackNorthwindSql();
        }
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
