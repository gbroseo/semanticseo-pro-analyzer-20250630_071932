public function register(): void;
    public function boot(): void;
}

class Application
{
    protected string $basePath;
    protected array $providers = [];
    protected array $services = [];
    protected bool $booted = false;
    protected array $configCache = [];

    public function __construct(string $basePath = null, array $providers = [])
    {
        $this->basePath = $basePath ? (realpath($basePath) ?: $basePath) : realpath(__DIR__);
        $this->loadEnvironment();
        $configProviders = $this->getConfig('app.providers');
        $this->providers = !empty($providers) ? $providers : (is_array($configProviders) ? $configProviders : []);
        $this->registerProviders();
    }

    protected function loadEnvironment(): void
    {
        $envFile = $this->basePath . '/.env';
        if (class_exists(Dotenv::class) && file_exists($envFile)) {
            try {
                Dotenv::createImmutable($this->basePath)->load();
            } catch (\Throwable $e) {
                throw new \RuntimeException("Failed to load .env file: " . $e->getMessage(), 0, $e);
            }
        }
    }

    protected function getConfig(string $key): mixed
    {
        $segments = explode('.', $key);
        $file = array_shift($segments);
        $path = $this->basePath . '/config/' . $file . '.php';
        if (!file_exists($path)) {
            return null;
        }
        if (!isset($this->configCache[$file])) {
            $this->configCache[$file] = require $path;
        }
        $config = $this->configCache[$file];
        foreach ($segments as $segment) {
            if (is_array($config) && array_key_exists($segment, $config)) {
                $config = $config[$segment];
            } else {
                return null;
            }
        }
        return $config;
    }

    protected function registerProviders(): void
    {
        foreach ($this->providers as $providerClass) {
            if (!class_exists($providerClass)) {
                throw new \RuntimeException("Provider {$providerClass} not found.");
            }
            $provider = new $providerClass($this);
            if (!$provider instanceof ServiceProviderInterface) {
                throw new \RuntimeException("Provider {$providerClass} must implement ServiceProviderInterface.");
            }
            $provider->register();
            $this->services['provider.' . $providerClass] = $provider;
        }
    }

    public function boot(): self
    {
        if ($this->booted) {
            return $this;
        }
        foreach ($this->services as $key => $service) {
            if (strpos($key, 'provider.') === 0) {
                $service->boot();
            }
        }
        $this->booted = true;
        return $this;
    }

    public function bind(string $name, callable|object $resolver): void
    {
        $this->services[$name] = $resolver;
    }

    public function getService(string $name): mixed
    {
        if (!isset($this->services[$name])) {
            throw new \RuntimeException("Service '{$name}' not found.");
        }
        $entry = $this->services[$name];
        if (is_callable($entry)) {
            $entry = $entry($this);
            $this->services[$name] = $entry;
        }
        return $entry;
    }
}

$app = (new Application(__DIR__))->boot();
return $app;