<?php
/**
 * Response.php
 * รวมศูนย์การส่ง JSON response ทั้งหมด เพื่อให้ทุก endpoint คืนค่ารูปแบบเดียวกัน
 */

class Response
{
    /** ส่ง JSON ดิบๆ พร้อมกำหนด HTTP status code แล้วจบการทำงานทันที */
    public static function json(array $payload, int $statusCode = 200): void
    {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE);
        exit;
    }

    /** กรณีสำเร็จ */
    public static function success($data = null, string $message = '', int $statusCode = 200): void
    {
        self::json([
            'success' => true,
            'message' => $message,
            'data'    => $data
        ], $statusCode);
    }

    /** กรณีผิดพลาด (ทั่วไป / server error) */
    public static function error(string $message, int $statusCode = 400, ?array $errors = null): void
    {
        $payload = [
            'success' => false,
            'message' => $message
        ];
        if ($errors !== null) {
            $payload['errors'] = $errors;
        }
        self::json($payload, $statusCode);
    }

    /** กรณี validation ไม่ผ่าน (HTTP 422) */
    public static function validationError(array $errors): void
    {
        self::error('ข้อมูลไม่ผ่านการตรวจสอบ', 422, $errors);
    }

    /** กรณีไม่พบ route ที่ร้องขอ */
    public static function notFound(string $message = 'ไม่พบ Endpoint ที่ร้องขอ'): void
    {
        self::error($message, 404);
    }
}
