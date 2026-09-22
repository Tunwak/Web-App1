<?php
/**
 * ProductController.php
 * คอนโทรลเลอร์สำหรับจัดการข้อมูลสินค้า (tb_products)
 * สร้างขึ้นใหม่สำหรับโปรเจกต์ โดยใช้โครงสร้างและลายมือโค้ดตามแบบ Workshop-w10
 * 
 * [โครงสร้างตาราง tb_products ใน MySQL]:
 *   - i_ProductID   (int, PK, auto_increment)
 *   - c_ProductName (varchar(30))
 *   - i_SupplierID  (int, FK)
 *   - i_CategoryID  (int, FK)
 *   - c_Unit        (varchar(30))
 *   - i_Price       (float)
 */

class ProductController
{
    private PDO $conn;

    public function __construct(PDO $conn)
    {
        $this->conn = $conn;
    }

    /** 
     * GET /api/products — ดึงรายการสินค้าทั้งหมด (+ ค้นหาตาม keyword)
     * รองรับ query string: ?search=คำค้นหา
     */
    public function index(): void
    {
        try {
            $search = trim($_GET['search'] ?? '');

            // ดึงข้อมูลสินค้าพร้อม JOIN เอาชื่อ Category และ Supplier มาแสดง
            $sql = "SELECT p.i_ProductID    AS id,
                           p.c_ProductName  AS productName,
                           p.i_SupplierID   AS supplierId,
                           p.i_CategoryID   AS categoryId,
                           p.c_Unit         AS unit,
                           p.i_Price        AS price,
                           c.c_CategoryName AS categoryName,
                           s.c_SupplierName AS supplierName
                    FROM tb_products p
                    LEFT JOIN tb_categories c ON p.i_CategoryID = c.i_CategoryID
                    LEFT JOIN tb_suppliers  s ON p.i_SupplierID = s.i_SupplierID";

            $params = [];

            // ถ้ามีการส่งคำค้นหามา ค้นหาจากชื่อสินค้า, หมวดหมู่ หรือผู้จัดจำหน่าย
            if ($search !== '') {
                $sql .= " WHERE p.c_ProductName LIKE :search 
                             OR c.c_CategoryName LIKE :searchCat
                             OR s.c_SupplierName LIKE :searchSup";
                $params[':search']    = "%{$search}%";
                $params[':searchCat'] = "%{$search}%";
                $params[':searchSup'] = "%{$search}%";
            }

            $sql .= " ORDER BY p.i_ProductID ASC";

            $stmt = $this->conn->prepare($sql);
            $stmt->execute($params);

            Response::success($stmt->fetchAll(PDO::FETCH_ASSOC));
        } catch (PDOException $e) {
            Response::error('ไม่สามารถดึงข้อมูลสินค้าได้: ' . $e->getMessage(), 500);
        }
    }

    /** 
     * GET /api/products/{id} — ดึงข้อมูลสินค้ารายตัวตาม ID
     */
    public function show(string $id): void
    {
        try {
            $sql = "SELECT p.i_ProductID    AS id,
                           p.c_ProductName  AS productName,
                           p.i_SupplierID   AS supplierId,
                           p.i_CategoryID   AS categoryId,
                           p.c_Unit         AS unit,
                           p.i_Price        AS price,
                           c.c_CategoryName AS categoryName,
                           s.c_SupplierName AS supplierName
                    FROM tb_products p
                    LEFT JOIN tb_categories c ON p.i_CategoryID = c.i_CategoryID
                    LEFT JOIN tb_suppliers  s ON p.i_SupplierID = s.i_SupplierID
                    WHERE p.i_ProductID = :id";

            $stmt = $this->conn->prepare($sql);
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->execute();

            $product = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$product) {
                Response::notFound('ไม่พบสินค้ารหัสนี้');
                return;
            }

            Response::success($product);
        } catch (PDOException $e) {
            Response::error('ไม่สามารถดึงข้อมูลสินค้าได้: ' . $e->getMessage(), 500);
        }
    }

    /** 
     * POST /api/products — เพิ่มข้อมูลสินค้าใหม่
     */
    public function store(): void
    {
        try {
            $data = json_decode(file_get_contents('php://input'), true);
            if (!$data) {
                Response::error('ข้อมูลไม่ถูกต้อง หรือรูปแบบ JSON ไม่ถูกต้อง', 400);
                return;
            }

            // ตรวจสอบความถูกต้องของข้อมูล (Validation) ตามกฎเดียวกับ Workshop-w9
            $errors = $this->validate($data);
            if (!empty($errors)) {
                Response::validationError($errors);
                return;
            }

            $sql = "INSERT INTO tb_products (c_ProductName, i_SupplierID, i_CategoryID, c_Unit, i_Price)
                    VALUES (:productName, :supplierId, :categoryId, :unit, :price)";

            $stmt = $this->conn->prepare($sql);
            $stmt->execute([
                ':productName' => trim($data['productName']),
                ':supplierId'  => (int) $data['supplierId'],
                ':categoryId'  => (int) $data['categoryId'],
                ':unit'        => trim($data['unit'] ?? ''),
                ':price'       => (float) $data['price'],
            ]);

            $newId = $this->conn->lastInsertId();

            Response::success(
                ['id' => $newId],
                'เพิ่มข้อมูลสินค้าเรียบร้อยแล้ว',
                201
            );
        } catch (PDOException $e) {
            Response::error('ไม่สามารถเพิ่มข้อมูลสินค้าได้: ' . $e->getMessage(), 500);
        }
    }

    /** 
     * PUT /api/products/{id} — แก้ไขข้อมูลสินค้า
     */
    public function update(string $id): void
    {
        try {
            // ตรวจสอบก่อนว่ามีสินค้ารหัสนี้อยู่ในตารางจริงหรือไม่
            $check = $this->conn->prepare("SELECT i_ProductID FROM tb_products WHERE i_ProductID = :id");
            $check->bindParam(':id', $id, PDO::PARAM_INT);
            $check->execute();
            if (!$check->fetch()) {
                Response::notFound('ไม่พบสินค้ารหัสนี้ในระบบ');
                return;
            }

            $data = json_decode(file_get_contents('php://input'), true);
            if (!$data) {
                Response::error('ข้อมูลไม่ถูกต้อง หรือรูปแบบ JSON ไม่ถูกต้อง', 400);
                return;
            }

            // ตรวจสอบข้อมูลก่อนบันทึก
            $errors = $this->validate($data);
            if (!empty($errors)) {
                Response::validationError($errors);
                return;
            }

            $sql = "UPDATE tb_products SET
                        c_ProductName = :productName,
                        i_SupplierID  = :supplierId,
                        i_CategoryID  = :categoryId,
                        c_Unit        = :unit,
                        i_Price       = :price
                    WHERE i_ProductID = :id";

            $stmt = $this->conn->prepare($sql);
            $stmt->execute([
                ':productName' => trim($data['productName']),
                ':supplierId'  => (int) $data['supplierId'],
                ':categoryId'  => (int) $data['categoryId'],
                ':unit'        => trim($data['unit'] ?? ''),
                ':price'       => (float) $data['price'],
                ':id'          => (int) $id,
            ]);

            Response::success(
                ['id' => $id],
                'แก้ไขข้อมูลสินค้าเรียบร้อยแล้ว'
            );
        } catch (PDOException $e) {
            Response::error('ไม่สามารถแก้ไขข้อมูลสินค้าได้: ' . $e->getMessage(), 500);
        }
    }

    /** 
     * DELETE /api/products/{id} — ลบข้อมูลสินค้า
     */
    public function destroy(string $id): void
    {
        try {
            // ตรวจสอบว่ามีสินค้ารหัสนี้อยู่จริงหรือไม่
            $check = $this->conn->prepare("SELECT i_ProductID FROM tb_products WHERE i_ProductID = :id");
            $check->bindParam(':id', $id, PDO::PARAM_INT);
            $check->execute();
            if (!$check->fetch()) {
                Response::notFound('ไม่พบสินค้ารหัสนี้ในระบบ');
                return;
            }

            $stmt = $this->conn->prepare("DELETE FROM tb_products WHERE i_ProductID = :id");
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->execute();

            Response::success(null, 'ลบสินค้าเรียบร้อยแล้ว');
        } catch (PDOException $e) {
            Response::error('ไม่สามารถลบสินค้าได้: ' . $e->getMessage(), 500);
        }
    }

    /**
     * validate() — ฟังก์ชันตรวจสอบความถูกต้องของฟอร์ม (Validation Rules)
     * อ้างอิงตามข้อกำหนดใน Workshop-w9 (process_product.php)
     */
    private function validate(array $data): array
    {
        $errors = [];

        // 1. ชื่อสินค้า — ห้ามว่าง และความยาวต้องไม่เกิน 30 ตัวอักษร
        $name = trim($data['productName'] ?? '');
        if ($name === '') {
            $errors[] = 'กรุณากรอกชื่อสินค้า';
        } elseif (mb_strlen($name) > 30) {
            $errors[] = 'ชื่อสินค้าต้องยาวไม่เกิน 30 ตัวอักษร';
        }

        // 2. หมวดหมู่สินค้า — ต้องเลือกค่า และต้องเป็นตัวเลขที่ถูกต้อง
        if (empty($data['categoryId']) || (int)$data['categoryId'] < 1) {
            $errors[] = 'กรุณาเลือกหมวดหมู่สินค้า';
        }

        // 3. ผู้จัดจำหน่าย — ต้องเลือกค่า และต้องเป็นตัวเลขที่ถูกต้อง
        if (empty($data['supplierId']) || (int)$data['supplierId'] < 1) {
            $errors[] = 'กรุณาเลือกผู้จัดจำหน่าย';
        }

        // 4. หน่วยนับสินค้า — ห้ามว่าง และความยาวต้องไม่เกิน 30 ตัวอักษร
        $unit = trim($data['unit'] ?? '');
        if ($unit === '') {
            $errors[] = 'กรุณากรอกหน่วยนับสินค้า (เช่น กล่อง, ขวด, แพ็ค)';
        } elseif (mb_strlen($unit) > 30) {
            $errors[] = 'หน่วยนับสินค้าต้องยาวไม่เกิน 30 ตัวอักษร';
        }

        // 5. ราคาสินค้า — ต้องเป็นตัวเลข และต้องไม่ต่ำกว่า 0
        if (!isset($data['price']) || !is_numeric($data['price'])) {
            $errors[] = 'กรุณากรอกราคาสินค้าเป็นตัวเลข';
        } elseif ((float)$data['price'] < 0) {
            $errors[] = 'ราคาสินค้าต้องไม่ต่ำกว่า 0 บาท';
        }

        return $errors;
    }
}
