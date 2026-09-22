<?php
/**
 * Router.php
 * Router แบบง่าย รองรับ path parameter เช่น /products/{id}
 * ใช้งาน:
 *   $router->get('/products', [$controller, 'index']);
 *   $router->get('/products/{id}', [$controller, 'show']);
 *   $router->dispatch($method, $uri);
 */

class Router
{
    private array $routes = [];

    public function get(string $pattern, callable $handler): void
    {
        $this->add('GET', $pattern, $handler);
    }

    public function post(string $pattern, callable $handler): void
    {
        $this->add('POST', $pattern, $handler);
    }

    public function put(string $pattern, callable $handler): void
    {
        $this->add('PUT', $pattern, $handler);
    }

    public function delete(string $pattern, callable $handler): void
    {
        $this->add('DELETE', $pattern, $handler);
    }

    private function add(string $method, string $pattern, callable $handler): void
    {
        $this->routes[] = [
            'method'  => $method,
            'pattern' => trim($pattern, '/'),
            'handler' => $handler
        ];
    }

    /**
     * จับคู่ method + uri กับ route ที่ลงทะเบียนไว้ แล้วเรียก handler
     * ถ้าไม่พบ route ที่ตรงกันเลย → ส่ง 404 ผ่าน Response::notFound()
     */
    public function dispatch(string $method, string $uri): void
    {
        $uri = trim($uri, '/');

        foreach ($this->routes as $route) {
            if ($route['method'] !== $method) {
                continue;
            }

            $params = $this->matchPattern($route['pattern'], $uri);
            if ($params !== false) {
                call_user_func_array($route['handler'], $params);
                return;
            }
        }

        Response::notFound("ไม่พบ Endpoint: [$method] /$uri");
    }

    /**
     * เทียบ pattern เช่น "products/{id}" กับ uri จริงเช่น "products/5"
     * คืนค่า array ของ path params ตามลำดับ ({id} → '5') หรือ false ถ้าไม่ตรง
     */
    private function matchPattern(string $pattern, string $uri)
    {
        $patternSegments = $pattern === '' ? [] : explode('/', $pattern);
        $uriSegments = $uri === '' ? [] : explode('/', $uri);

        if (count($patternSegments) !== count($uriSegments)) {
            return false;
        }

        $params = [];
        foreach ($patternSegments as $i => $segment) {
            if (preg_match('/^\{(\w+)\}$/', $segment, $matches)) {
                // เป็น path param เช่น {id} → เก็บค่าไว้ส่งเข้า handler
                $params[] = $uriSegments[$i];
            } elseif ($segment !== $uriSegments[$i]) {
                return false;
            }
        }

        return $params;
    }
}
