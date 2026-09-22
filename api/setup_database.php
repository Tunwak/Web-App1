<?php
/**
 * setup_database.php
 * Script for initializing tables and sample data from dbNorthwind.sql
 */
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../inc/connDB.php';

try {
    $sqlFile = __DIR__ . '/../../dbNorthwind.sql';
    if (!file_exists($sqlFile)) {
        echo json_encode([
            'success' => false,
            'message' => 'File dbNorthwind.sql not found at ' . $sqlFile
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $sql = file_get_contents($sqlFile);

    // Execute multi-query SQL directly into connected database
    $conn->exec($sql);

    // Check count of products
    $stmt = $conn->query("SELECT COUNT(*) AS total FROM tb_products");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'message' => 'Database initialized successfully!',
        'total_products' => (int)($result['total'] ?? 0)
    ], JSON_UNESCAPED_UNICODE);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
