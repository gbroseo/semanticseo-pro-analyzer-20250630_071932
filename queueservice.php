private const MAX_ATTEMPTS = 3;
    private const VISIBILITY_TIMEOUT = 300; // seconds
    private const SLEEP_SECONDS = 1; // seconds

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->initializeTable();
    }

    private function initializeTable(): void
    {
        $sql = "CREATE TABLE IF NOT EXISTS queue_jobs (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            payload LONGTEXT NOT NULL,
            status ENUM('pending','processing','completed','failed') NOT NULL DEFAULT 'pending',
            attempts INT UNSIGNED NOT NULL DEFAULT 0,
            reserved_at DATETIME NULL,
            error_message TEXT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
        $this->pdo->exec($sql);
    }

    public function enqueueJob(array $data): int
    {
        $stmt = $this->pdo->prepare("INSERT INTO queue_jobs (payload) VALUES (:payload)");
        $stmt->execute(['payload' => json_encode($data)]);
        return (int)$this->pdo->lastInsertId();
    }

    public function dequeueJob(): ?array
    {
        $this->recoverStuckJobs();

        try {
            $this->pdo->beginTransaction();

            $stmt = $this->pdo->prepare(
                "SELECT id, payload
                 FROM queue_jobs
                 WHERE status = 'pending'
                   AND attempts < :max
                 ORDER BY id ASC
                 LIMIT 1
                 FOR UPDATE"
            );
            $stmt->execute(['max' => self::MAX_ATTEMPTS]);
            $job = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$job) {
                $this->pdo->commit();
                return null;
            }

            $update = $this->pdo->prepare(
                "UPDATE queue_jobs
                 SET status = 'processing',
                     attempts = attempts + 1,
                     reserved_at = NOW(),
                     updated_at = NOW()
                 WHERE id = :id"
            );
            $update->execute(['id' => $job['id']]);

            $this->pdo->commit();

            return [
                'id' => (int)$job['id'],
                'payload' => json_decode($job['payload'], true),
            ];
        } catch (PDOException $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    private function recoverStuckJobs(): void
    {
        $timeout = self::VISIBILITY_TIMEOUT;
        $max = self::MAX_ATTEMPTS;

        $resetSql = "UPDATE queue_jobs
            SET status = 'pending',
                reserved_at = NULL,
                updated_at = NOW()
            WHERE status = 'processing'
              AND reserved_at < (NOW() - INTERVAL :timeout SECOND)
              AND attempts < :max";
        $resetStmt = $this->pdo->prepare($resetSql);
        $resetStmt->execute(['timeout' => $timeout, 'max' => $max]);

        $failSql = "UPDATE queue_jobs
            SET status = 'failed',
                error_message = 'Max attempts exceeded',
                updated_at = NOW()
            WHERE status = 'processing'
              AND reserved_at < (NOW() - INTERVAL :timeout SECOND)
              AND attempts >= :max";
        $failStmt = $this->pdo->prepare($failSql);
        $failStmt->execute(['timeout' => $timeout, 'max' => $max]);
    }

    public function processJobs(callable $handler): void
    {
        while (true) {
            $job = null;
            try {
                $job = $this->dequeueJob();
            } catch (PDOException $e) {
                throw $e;
            }

            if (!$job) {
                sleep(self::SLEEP_SECONDS);
                continue;
            }

            $id = $job['id'];

            try {
                call_user_func($handler, $job['payload']);

                $stmt = $this->pdo->prepare(
                    "UPDATE queue_jobs
                     SET status = 'completed',
                         updated_at = NOW()
                     WHERE id = :id"
                );
                $stmt->execute(['id' => $id]);
            } catch (Throwable $e) {
                $error = $e->getMessage();
                $attempts = $this->getAttempts($id);

                if ($attempts < self::MAX_ATTEMPTS) {
                    $stmt = $this->pdo->prepare(
                        "UPDATE queue_jobs
                         SET status = 'pending',
                             reserved_at = NULL,
                             error_message = :error,
                             updated_at = NOW()
                         WHERE id = :id"
                    );
                    $stmt->execute(['id' => $id, 'error' => $error]);
                } else {
                    $stmt = $this->pdo->prepare(
                        "UPDATE queue_jobs
                         SET status = 'failed',
                             error_message = :error,
                             updated_at = NOW()
                         WHERE id = :id"
                    );
                    $stmt->execute(['id' => $id, 'error' => $error]);
                }
            }
        }
    }

    private function getAttempts(int $id): int
    {
        $stmt = $this->pdo->prepare("SELECT attempts FROM queue_jobs WHERE id = :id");
        $stmt->execute(['id' => $id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ? (int)$result['attempts'] : 0;
    }

    public function clearQueue(): void
    {
        $this->pdo->exec("TRUNCATE TABLE queue_jobs");
    }
}