public function __construct(string $apiKey = null)
    {
        if ($apiKey !== null && $apiKey !== '') {
            $this->apiKey = $apiKey;
        } else {
            $envKey = getenv('TEXTRAZOR_API_KEY') ?: ($_ENV['TEXTRAZOR_API_KEY'] ?? '');
            if (empty($envKey)) {
                throw new \InvalidArgumentException('TextRazor API key is required');
            }
            $this->apiKey = $envKey;
        }
    }

    public function setApiKey(string $key): void
    {
        if ($key === '') {
            throw new \InvalidArgumentException('API key cannot be empty');
        }
        $this->apiKey = $key;
    }

    public function analyzeText(string $text, array $options = []): array
    {
        $url = 'https://api.textrazor.com/';
        $postData = [
            'apiKey' => $this->apiKey,
            'text'   => $text,
        ];

        foreach ($options as $key => $value) {
            if ($key === 'extractors') {
                $postData['extractors'] = is_array($value) ? implode(',', $value) : (string) $value;
            } else {
                $postData[$key] = is_array($value) ? implode(',', $value) : $value;
            }
        }

        if (!isset($postData['extractors'])) {
            $postData['extractors'] = 'entities,topics,properties,dependency-trees,relations';
        }

        $ch = curl_init($url);
        if ($ch === false) {
            throw new TextRazorClientException('Failed to initialize cURL session');
        }

        curl_setopt($ch, CURLOPT_RETURNTRANSFER,     true);
        curl_setopt($ch, CURLOPT_POST,               true);
        curl_setopt($ch, CURLOPT_POSTFIELDS,         http_build_query($postData));
        curl_setopt($ch, CURLOPT_HTTPHEADER,         ['Accept: application/json']);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER,     true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST,     2);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT,     10);
        curl_setopt($ch, CURLOPT_TIMEOUT,            30);

        $response = curl_exec($ch);
        if ($response === false) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new TextRazorClientException('cURL error: ' . $error);
        }

        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode < 200 || $httpCode >= 300) {
            throw new TextRazorApiException("TextRazor API responded with HTTP code {$httpCode}: {$response}");
        }

        return $this->parseResponse($response);
    }

    private function parseResponse(string $response): array
    {
        $data = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new TextRazorClientException('JSON parse error: ' . json_last_error_msg());
        }

        if (!empty($data['error'])) {
            $detail = is_string($data['error']) ? $data['error'] : json_encode($data['error']);
            throw new TextRazorApiException('TextRazor API error: ' . $detail);
        }

        return $data;
    }
}