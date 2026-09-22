<?php
class SupplierController
{
    private PDO $conn;

    public function __construct(PDO $conn)
    {
        $this->conn = $conn;
    }

    /** GET /api/suppliers — ดึงรายการ Supplier ทั้งหมด */
    public function index(): void
    {
        try {
            $sql = "SELECT i_SupplierID AS id, c_SupplierName AS name
                    FROM tb_suppliers
                    ORDER BY i_SupplierID ASC";

            $stmt = $this->conn->prepare($sql);
            $stmt->execute();

            Response::success($stmt->fetchAll(PDO::FETCH_ASSOC));
        } catch (PDOException $e) {
            Response::error('ไม่สามารถดึงข้อมูลผู้จัดจำหน่ายได้', 500);
        }
    }

    /** GET /api/suppliers/{id} — ดึง Supplier รายตัว */
    public function show(string $id): void
    {
        try {
            $sql = "SELECT i_SupplierID AS id, c_SupplierName AS name
                    FROM tb_suppliers
                    WHERE i_SupplierID = :id";

            $stmt = $this->conn->prepare($sql);
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->execute();

            $supplier = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$supplier) {
                Response::notFound('ไม่พบผู้จัดจำหน่ายรหัสนี้');
                return;
            }

            Response::success($supplier);
        } catch (PDOException $e) {
            Response::error('ไม่สามารถดึงข้อมูลผู้จัดจำหน่ายได้', 500);
        }
    }
}
