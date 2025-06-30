function bootstrap(): array {
    $dotenv = Dotenv::createImmutable(__DIR__);
    $dotenv->safeLoad();

    date_default_timezone_set($_ENV['APP_TIMEZONE'] ?? 'UTC');

    if (($_ENV['APP_ENV'] ?? 'production') !== 'production') {
        error_reporting(E_ALL);
        ini_set('display_errors', '1');
    } else {
        error_reporting(0);
        ini_set('display_errors', '0');
    }

    set_exception_handler(function (Throwable $e): void {
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode([
            'error' => 'Internal Server Error',
            'message' => (getenv('APP_ENV') !== 'production') ? $e->getMessage() : 'An unexpected error occurred.',
        ]);
        exit;
    });

    $request = Request::createFromGlobals();

    $dispatcher = FastRoute\simpleDispatcher(function (RouteCollector $r): void {
        $routes = require __DIR__ . '/config/routes.php';
        foreach ($routes as $route) {
            $r->addRoute($route['method'], $route['path'], $route['handler']);
        }
    });

    return [$request, $dispatcher];
}

function handleRequest(Request $request, Dispatcher $dispatcher): void {
    $routeInfo = $dispatcher->dispatch($request->getMethod(), rawurldecode($request->getPathInfo()));

    switch ($routeInfo[0]) {
        case Dispatcher::NOT_FOUND:
            $response = new Response('Not Found', 404);
            break;
        case Dispatcher::METHOD_NOT_ALLOWED:
            $response = new Response('Method Not Allowed', 405);
            break;
        case Dispatcher::FOUND:
            $handler = $routeInfo[1];
            $vars = $routeInfo[2];
            [$controllerClass, $method] = explode('@', $handler, 2);
            if (!class_exists($controllerClass) || !method_exists($controllerClass, $method)) {
                $response = new Response('Handler not found', 500);
                break;
            }
            $controller = new $controllerClass();
            $response = call_user_func_array([$controller, $method], array_merge([$request], $vars));
            if (!$response instanceof Response) {
                throw new RuntimeException('Controller must return instance of Symfony\Component\HttpFoundation\Response.');
            }
            break;
        default:
            $response = new Response('Routing error', 500);
    }

    $response->send();
}

[$request, $dispatcher] = bootstrap();
handleRequest($request, $dispatcher);