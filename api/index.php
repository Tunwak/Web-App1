<?php
/**
 * ─────────────────────────────────────────────────────────────────────────────
 * api/index.php — API Entrypoint & Central Route Dispatcher
 * ─────────────────────────────────────────────────────────────────────────────
 * [Full-stack Dev Note]:
 * ต่อยอดจาก Workshop-w10:
 * 1. กำหนด CORS Headers รองรับการเรียกจากต่าง Origin หรือ Fetch API
 * 2. จัดการ Preflight Request (OPTIONS) ให้ตอบ 204 ทันที
 * 3. [ส่วนที่เพิ่มสำหรับ Final Project]:
 *    - โหลด ProductController และลงทะเบียน Endpoint CRUD สำหรับสินค้า (/products) ครบ 5 methods
 *    - ควบคู่กับ Categories & Suppliers (Read-only) ที่เรียนในสัปดาห์ที่ 10
 * ─────────────────────────────────────────────────────────────────────────────
 */

// 1. CORS Headers
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// 2. Database Connection (PDO)
require_once __DIR__ . '/../inc/connDB.php';

// 3. Core Framework Helpers (จาก Workshop-w10)
require_once __DIR__ . '/core/Response.php';
require_once __DIR__ . '/core/Router.php';

// 4. Load Controllers
require_once __DIR__ . '/controllers/ProductController.php';   // [เพิ่มสำหรับโปรเจกต์นี้]
require_once __DIR__ . '/controllers/CategoryController.php';  // [ของเดิมจาก Workshop-w10]
require_once __DIR__ . '/controllers/SupplierController.php';  // [ของเดิมจาก Workshop-w10]

try {
    // 5. Initialize Controllers พร้อมส่ง PDO instance ($conn) เข้าไปใช้งาน
    $productController  = new ProductController($conn);
    $categoryController = new CategoryController($conn);
    $supplierController = new SupplierController($conn);

    // 6. Setup Routing Table
    $router = new Router();

    // ── [ส่วนที่เพิ่มใหม่: Products Full CRUD] ──
    $router->get('/products',      [$productController, 'index']);   // ดึงทั้งหมด + ค้นหา
    $router->get('/products/{id}', [$productController, 'show']);    // ดึงรายตัว
    $router->post('/products',     [$productController, 'store']);   // เพิ่มสินค้าใหม่
    $router->put('/products/{id}', [$productController, 'update']);  // แก้ไขสินค้า
    $router->delete('/products/{id}', [$productController, 'destroy']); // ลบสินค้า

    // ── [ของเดิมจาก Workshop-w10: Dropdown Data Sources] ──
    $router->get('/categories',      [$categoryController, 'index']);
    $router->get('/categories/{id}', [$categoryController, 'show']);
    $router->get('/suppliers',       [$supplierController, 'index']);
    $router->get('/suppliers/{id}',  [$supplierController, 'show']);

    // 7. ดึง URI Path จาก RewriteRule (.htaccess ส่งผ่านมาใน query string '__route')
    $requestPath   = $_GET['__route'] ?? '';
    $requestMethod = $_SERVER['REQUEST_METHOD'];

    // 8. ส่งต่อไปยัง Controller ที่จับคู่ไว้
    $router->dispatch($requestMethod, $requestPath);

} catch (Throwable $e) {
    // Global Error Handler สำหรับจับ Uncaught Error ป้องกันเซิร์ฟเวอร์หลุดเป็นหน้าขาว
    Response::error('Internal Server Error: ' . $e->getMessage(), 500);
}
