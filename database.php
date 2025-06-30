private function __construct() {}
    private function __clone() {}
    private function __wakeup() {}

    public static function connect(): \PDO
    {
        if (self::$connection === null) {
            $driver = getenv('DB_DRIVER') ?: 'mysql';
            $host = getenv('DB_HOST') ?: '127.0.0.1';
            $port = getenv('DB_PORT') ?: ($driver === 'pgsql' ? '5432' : '3306');
            $database = getenv('DB_DATABASE') ?: '';
            $username = getenv('DB_USERNAME') ?: '';
            $password = getenv('DB_PASSWORD') ?: '';
            $charset = getenv('DB_CHARSET') ?: 'utf8mb4';

            if ($database === '') {
                throw new \InvalidArgumentException('Environment variable DB_DATABASE is required and cannot be empty.');
            }
            if ($username === '') {
                throw new \InvalidArgumentException('Environment variable DB_USERNAME is required and cannot be empty.');
            }

            switch ($driver) {
                case 'pgsql':
                    $dsn = "pgsql:host={$host};port={$port};dbname={$database}";
                    break;
                case 'mysql':
                    $dsn = "mysql:host={$host};port={$port};dbname={$database};charset={$charset}";
                    break;
                default:
                    $dsn = "{$driver}:host={$host};port={$port};dbname={$database};charset={$charset}";
                    break;
            }

            $options = [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
                \PDO::ATTR_EMULATE_PREPARES => false,
            ];

            try {
                self::$connection = new \PDO($dsn, $username, $password, $options);
            } catch (\PDOException $e) {
                throw new \PDOException('Database connection failed: ' . $e->getMessage(), (int)$e->getCode(), $e);
            }
        }

        return self::$connection;
    }

    public static function migrate(): void
    {
        $pdo = self::connect();
        $config = [
            'envTableVar'     => 'MIGRATIONS_TABLE',
            'defaultTable'    => 'migrations',
            'envPathVar'      => 'MIGRATIONS_PATH',
            'defaultPaths'    => [__DIR__ . '/migrations', dirname(__DIR__) . '/migrations'],
            'createSqls'      => [
                'mysql'   => "CREATE TABLE IF NOT EXISTS {table} (
    id INT AUTO_INCREMENT PRIMARY KEY,
    migration VARCHAR(255) NOT NULL,
    batch INT NOT NULL,
    applied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;",
                'pgsql'   => "CREATE TABLE IF NOT EXISTS {table} (
    id SERIAL PRIMARY KEY,
    migration VARCHAR(255) NOT NULL,
    batch INT NOT NULL,
    applied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);",
                'default' => "CREATE TABLE IF NOT EXISTS {table} (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    migration VARCHAR(255) NOT NULL,
    batch INTEGER NOT NULL,
    applied_at DATETIME DEFAULT CURRENT_TIMESTAMP
);",
            ],
            'historyColumn'   => 'migration',
            'hasBatch'        => true,
            'batchColumn'     => 'batch',
            'insertSql'       => "INSERT INTO {table} (migration, batch) VALUES (:migration, :batch)",
            'queryHistorySql' => "SELECT migration FROM {table}",
            'queryMaxBatchSql'=> "SELECT MAX(batch) FROM {table}",
        ];
        self::runScripts($pdo, $config);
    }

    public static function seed(): void
    {
        $pdo = self::connect();
        $config = [
            'envTableVar'     => 'SEEDS_TABLE',
            'defaultTable'    => 'seeds',
            'envPathVar'      => 'SEEDS_PATH',
            'defaultPaths'    => [__DIR__ . '/seeds', dirname(__DIR__) . '/seeds'],
            'createSqls'      => [
                'mysql'   => "CREATE TABLE IF NOT EXISTS {table} (
    id INT AUTO_INCREMENT PRIMARY KEY,
    seed VARCHAR(255) NOT NULL,
    run_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;",
                'pgsql'   => "CREATE TABLE IF NOT EXISTS {table} (
    id SERIAL PRIMARY KEY,
    seed VARCHAR(255) NOT NULL,
    run_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);",
                'default' => "CREATE TABLE IF NOT EXISTS {table} (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    seed VARCHAR(255) NOT NULL,
    run_at DATETIME DEFAULT CURRENT_TIMESTAMP
);",
            ],
            'historyColumn'   => 'seed',
            'hasBatch'        => false,
            'batchColumn'     => null,
            'insertSql'       => "INSERT INTO {table} (seed) VALUES (:seed)",
            'queryHistorySql' => "SELECT seed FROM {table}",
        ];
        self::runScripts($pdo, $config);
    }

    private static function runScripts(\PDO $pdo, array $config): void
    {
        // Determine and validate table name
        $tableName = getenv($config['envTableVar']) ?: $config['defaultTable'];
        self::validateIdentifier($tableName);
        $driver = $pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);
        $quotedTable = self::quoteIdentifier($tableName, $driver);

        // Create history table if not exists
        $sqls = $config['createSqls'];
        $createSql = $sqls[$driver] ?? $sqls['default'];
        $pdo->exec(str_replace('{table}', $quotedTable, $createSql));

        // Determine scripts path
        $scriptsPath = getenv($config['envPathVar']) ?: '';
        if (!$scriptsPath) {
            foreach ($config['defaultPaths'] as $path) {
                if (is_dir($path)) {
                    $scriptsPath = $path;
                    break;
                }
            }
        }
        if (!$scriptsPath || !is_dir($scriptsPath)) {
            return;
        }

        // Fetch already executed scripts
        $historyColumn = $config['historyColumn'];
        self::validateIdentifier($historyColumn);
        $queryHistory = str_replace('{table}', $quotedTable, $config['queryHistorySql']);
        $stmtHistory = $pdo->query($queryHistory);
        $executedScripts = $stmtHistory->fetchAll(\PDO::FETCH_COLUMN) ?: [];

        // Determine batch for migrations
        $batch = null;
        if (!empty($config['hasBatch'])) {
            $queryMaxBatch = str_replace('{table}', $quotedTable, $config['queryMaxBatchSql']);
            $stmtMax = $pdo->query($queryMaxBatch);
            $lastBatch = (int) ($stmtMax->fetchColumn() ?: 0);
            $batch = $lastBatch + 1;
        }

        // Discover and sort script files
        $files = glob(rtrim($scriptsPath, '/\\') . '/*.php');
        sort($files);

        foreach ($files as $file) {
            $scriptName = basename($file);
            if (in_array($scriptName, $executedScripts, true)) {
                continue;
            }

            // Load script
            $raw = require $file;
            if (is_callable($raw) || is_string($raw)) {
                $operations = [$raw];
            } elseif (is_array($raw)) {
                $operations = $raw;
            } else {
                throw new \RuntimeException("Script file '{$file}' must return a callable, array, or SQL string, got " . gettype($raw));
            }

            try {
                $pdo->beginTransaction();
                foreach ($operations as $op) {
                    if (is_callable($op)) {
                        $op($pdo);
                    } elseif (is_string($op)) {
                        $pdo->exec($op);
                    } else {
                        throw new \RuntimeException("Invalid operation type in '{$file}': expected callable or SQL string, got " . gettype($op));
                    }
                }

                // Record execution
                $insertSql = str_replace('{table}', $quotedTable, $config['insertSql']);
                $stmtInsert = $pdo->prepare($insertSql);
                $params = [$historyColumn => $scriptName];
                if (!empty($config['hasBatch'])) {
                    $params[$config['batchColumn']] = $batch;
                }
                $stmtInsert->execute($params);
                $pdo->commit();
            } catch (\Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                throw $e;
            }
        }
    }

    private static function validateIdentifier(string $identifier): void
    {
        if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $identifier)) {
            throw new \InvalidArgumentException("Invalid identifier '{$identifier}'. Must start with a letter or underscore and contain only alphanumeric characters and underscores.");
        }
    }

    private static function quoteIdentifier(string $identifier, string $driver): string
    {
        self::validateIdentifier($identifier);
        if ($driver === 'mysql') {
            $quote = '`';
        } else {
            $quote = '"';
        }
        return $quote . str_replace($quote, '', $identifier) . $quote;
    }
}