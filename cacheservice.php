private function __construct()
    {
        $this->cacheDir = getenv('CACHE_DIR') ?: __DIR__ . '/../cache';
        if (!is_dir($this->cacheDir) && !mkdir($this->cacheDir, 0755, true)) {
            throw new \RuntimeException("Unable to create cache directory: {$this->cacheDir}");
        }
        if (!is_writable($this->cacheDir)) {
            throw new \RuntimeException("Cache directory is not writable: {$this->cacheDir}");
        }

        $ttlEnv = getenv('CACHE_TTL');
        if ($ttlEnv !== false) {
            if (!ctype_digit($ttlEnv) || (int)$ttlEnv < 1) {
                throw new \RuntimeException("Invalid CACHE_TTL environment variable: {$ttlEnv}");
            }
            $this->defaultTtl = (int)$ttlEnv;
        } else {
            $this->defaultTtl = 3600;
        }
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function get(string $key)
    {
        $file = $this->getFilename($key);
        if (!file_exists($file)) {
            return null;
        }

        $contents = file_get_contents($file);
        if ($contents === false) {
            error_log("CacheService: failed to read cache file {$file}");
            return null;
        }

        $data = json_decode($contents, true);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($data) || !array_key_exists('expire', $data) || !array_key_exists('value', $data)) {
            if (!unlink($file)) {
                error_log("CacheService: failed to delete corrupted cache file {$file}");
            }
            return null;
        }

        $expire = (int)$data['expire'];
        if ($expire > 0 && $expire < time()) {
            if (!unlink($file)) {
                error_log("CacheService: failed to delete expired cache file {$file}");
            }
            return null;
        }

        return $data['value'];
    }

    public function set(string $key, $value, int $ttl = null): bool
    {
        $ttl = $ttl ?? $this->defaultTtl;
        if ($ttl < 1) {
            throw new \InvalidArgumentException("TTL must be a positive integer; got {$ttl}");
        }

        $expire = time() + $ttl;
        $data = ['expire' => $expire, 'value' => $value];
        $json = json_encode($data);
        if ($json === false) {
            throw new \RuntimeException("CacheService: failed to JSON encode data for key {$key}");
        }

        $file = $this->getFilename($key);
        $temp = $file . '.' . uniqid('', true) . '.tmp';

        $bytes = file_put_contents($temp, $json, LOCK_EX);
        if ($bytes === false) {
            throw new \RuntimeException("CacheService: failed to write to temp cache file {$temp}");
        }

        if (!rename($temp, $file)) {
            unlink($temp);
            throw new \RuntimeException("CacheService: failed to rename temp file {$temp} to {$file}");
        }

        return true;
    }

    public function delete(string $key): bool
    {
        $file = $this->getFilename($key);
        if (file_exists($file)) {
            if (!unlink($file)) {
                error_log("CacheService: failed to delete cache file {$file}");
                return false;
            }
            return true;
        }
        return false;
    }

    private function getFilename(string $key): string
    {
        $hashed = hash('sha256', $key);
        return rtrim($this->cacheDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $hashed . '.cache';
    }
}