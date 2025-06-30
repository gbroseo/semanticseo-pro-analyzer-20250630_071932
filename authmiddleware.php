public function __construct(
        ResponseFactoryInterface $responseFactory,
        StreamFactoryInterface $streamFactory,
        string $jwtSecret,
        array $except = []
    ) {
        $this->responseFactory = $responseFactory;
        $this->streamFactory  = $streamFactory;
        $this->jwtSecret      = $jwtSecret;
        $this->except         = $except;
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_set_cookie_params([
                'secure'   => $this->isSecure(),
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
            session_start();
        }

        $path = $this->getUriPath($request);

        if ($this->inExceptArray($path)) {
            return $handler->handle($request);
        }

        if ($this->isApiRequest($request)) {
            if (!$this->isAuthenticated($request)) {
                return $this->unauthorizedJsonResponse();
            }
        } else {
            if (!$this->isAuthenticated($request)) {
                return $this->redirectResponse($path);
            }
        }

        return $handler->handle($request);
    }

    protected function isSecure(): bool
    {
        if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
            return true;
        }
        if (
            !empty($_SERVER['HTTP_X_FORWARDED_PROTO']) &&
            strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https'
        ) {
            return true;
        }
        return false;
    }

    protected function unauthorizedJsonResponse(): ResponseInterface
    {
        $response = $this->responseFactory->createResponse(401)
            ->withHeader('Content-Type', 'application/json');
        $body = $this->streamFactory->createStream(json_encode(['error' => 'Unauthorized']));
        return $response->withBody($body);
    }

    protected function redirectResponse(string $path): ResponseInterface
    {
        $redirectUri = '/login?redirect=' . urlencode($path);
        return $this->responseFactory
            ->createResponse(302)
            ->withHeader('Location', $redirectUri);
    }

    protected function isAuthenticated(ServerRequestInterface $request): bool
    {
        $token = $this->getBearerToken($request);
        if ($token !== null && $this->validateToken($token)) {
            return true;
        }
        return !empty($_SESSION['user_id']);
    }

    protected function getBearerToken(ServerRequestInterface $request): ?string
    {
        $header = $request->getHeaderLine('Authorization');
        if (preg_match('/Bearer\s+(\S+)/i', $header, $matches)) {
            return $matches[1];
        }
        $serverHeader = $_SERVER['HTTP_AUTHORIZATION']
            ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION']
            ?? '';
        if (preg_match('/Bearer\s+(\S+)/i', $serverHeader, $matches)) {
            return $matches[1];
        }
        return null;
    }

    protected function validateToken(string $token): bool
    {
        try {
            JWT::decode($token, new Key($this->jwtSecret, 'HS256'));
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    protected function isApiRequest(ServerRequestInterface $request): bool
    {
        $path = $this->getUriPath($request);
        if (strpos($path, '/api/') === 0) {
            return true;
        }
        $accept = $request->getHeaderLine('Accept');
        return stripos($accept, 'application/json') !== false;
    }

    protected function getUriPath(ServerRequestInterface $request): string
    {
        $uri  = (string) $request->getUri()->getPath();
        $path = parse_url($uri, PHP_URL_PATH) ?: '/';
        return '/' . ltrim($path, '/');
    }

    protected function inExceptArray(string $path): bool
    {
        foreach ($this->except as $except) {
            if ($except === $path) {
                return true;
            }
        }
        return false;
    }
}