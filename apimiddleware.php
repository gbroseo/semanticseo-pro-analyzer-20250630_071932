public function __construct(string $validApiKey = null)
    {
        $this->validApiKey = $validApiKey
            ?? getenv('API_KEY')
            ?: ($_ENV['API_KEY'] ?? '');

        if ($this->validApiKey === '') {
            throw new RuntimeException('API key is not configured.');
        }
    }

    /**
     * @param ServerRequestInterface $request
     * @param callable               $next
     * @return ResponseInterface
     */
    public function handle(ServerRequestInterface $request, callable $next): ResponseInterface
    {
        if (!$this->verifyApiKey($request)) {
            $payload = [
                'error'   => 'Unauthorized',
                'message' => 'Invalid or missing API key',
            ];
            $body = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

            return new Response(
                401,
                [
                    'Content-Type'     => 'application/json; charset=utf-8',
                    'WWW-Authenticate' => 'Bearer',
                ],
                $body
            );
        }

        return $next($request);
    }

    private function verifyApiKey(ServerRequestInterface $request): bool
    {
        $authorization = $request->getHeaderLine('Authorization')
            ?: $request->getHeaderLine('authorization');

        if ($authorization && preg_match('/Bearer\s+(.+)$/i', $authorization, $matches)) {
            $apiKey = trim($matches[1]);
        } else {
            $apiKey = $request->getHeaderLine('X-API-KEY')
                ?: $request->getHeaderLine('x-api-key');
        }

        if ($apiKey === '') {
            return false;
        }

        return hash_equals($this->validApiKey, $apiKey);
    }
}