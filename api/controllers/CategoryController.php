<?php
class CategoryController
{
    private PDO $conn;

    public function __construct(PDO $conn)
    {
        $this->conn = $conn;
    }

    /** GET /api/categories — ดึงรายการ Category ทั้งหมด */
    public function index(): void
    {
        try {
            $sql = "SELECT i_CategoryID AS id, c_CategoryName AS name
                    FROM tb_categories
                    ORDER BY i_CategoryID ASC";

            $stmt = $this->conn->prepare($sql);
            $stmt->execute();

            Response::success($stmt->fetchAll(PDO::FETCH_ASSOC));
        } catch (PDOException $e) {
            Response::error('ไม่สามารถดึงข้อมูลหมวดหมู่สินค้าได้', 500);
        }
    }

    /** GET /api/categories/{id} — ดึง Category รายตัว */
    public function show(string $id): void
    {
        try {
            $sql = "SELECT i_CategoryID AS id, c_CategoryName AS name
                    FROM tb_categories
                    WHERE i_CategoryID = :id";

            $stmt = $this->conn->prepare($sql);
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->execute();

            $category = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$category) {
                Response::notFound('ไม่พบหมวดหมู่รหัสนี้');
                return;
            }

            Response::success($category);
        } catch (PDOException $e) {
            Response::error('ไม่สามารถดึงข้อมูลหมวดหมู่สินค้าได้', 500);
        }
    }
}
