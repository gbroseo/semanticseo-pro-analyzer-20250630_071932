public function __construct(PDO $db, LoggerInterface $logger)
    {
        $this->db = $db;
        $this->logger = $logger;
    }

    public function subscribeUser(Request $request, Response $response, array $args): Response
    {
        $user = $request->getAttribute('user');
        if (empty($user['id'])) {
            return $this->errorResponse($response, 'Unauthenticated', 401);
        }

        $body = (string)$request->getBody();
        $data = json_decode($body, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return $this->errorResponse($response, 'Invalid JSON payload', 400);
        }

        $planId = $data['plan_id'] ?? null;
        if (!$planId || !filter_var($planId, FILTER_VALIDATE_INT)) {
            return $this->errorResponse($response, 'Missing or invalid plan_id', 400);
        }

        try {
            $stmt = $this->db->prepare('SELECT id FROM plans WHERE id = :id');
            $stmt->execute(['id' => $planId]);
            if (!$stmt->fetch(PDO::FETCH_ASSOC)) {
                return $this->errorResponse($response, 'Plan not found', 404);
            }
        } catch (Exception $e) {
            $this->logger->error('Plan lookup failed', ['exception' => $e]);
            return $this->errorResponse($response, 'Failed to retrieve plan', 500);
        }

        try {
            $this->db->beginTransaction();

            $stmt = $this->db->prepare('SELECT id FROM subscriptions WHERE user_id = :uid AND status = :status FOR UPDATE');
            $stmt->execute(['uid' => $user['id'], 'status' => 'active']);
            if ($stmt->fetch(PDO::FETCH_ASSOC)) {
                $this->db->rollBack();
                return $this->errorResponse($response, 'User already has an active subscription', 409);
            }

            $now = (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('Y-m-d H:i:s');
            $stmt = $this->db->prepare('INSERT INTO subscriptions (user_id, plan_id, status, created_at, updated_at) VALUES (:uid, :pid, :status, :created, :updated)');
            $stmt->execute([
                'uid'     => $user['id'],
                'pid'     => $planId,
                'status'  => 'active',
                'created' => $now,
                'updated' => $now,
            ]);
            $subscriptionId = (int)$this->db->lastInsertId();

            $this->db->commit();

            $result = [
                'subscription_id' => $subscriptionId,
                'user_id'         => $user['id'],
                'plan_id'         => $planId,
                'status'          => 'active',
                'created_at'      => $now,
                'updated_at'      => $now,
            ];

            return $this->successResponse($response, $result, 201);
        } catch (PDOException $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            $this->logger->error('Database error during subscription creation', ['exception' => $e]);
            if ($e->getCode() === '23000') {
                return $this->errorResponse($response, 'User already has an active subscription', 409);
            }
            return $this->errorResponse($response, 'Subscription creation failed', 500);
        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            $this->logger->error('Error during subscription creation', ['exception' => $e]);
            return $this->errorResponse($response, 'Subscription creation failed', 500);
        }
    }

    public function cancelSubscription(Request $request, Response $response, array $args): Response
    {
        $user = $request->getAttribute('user');
        if (empty($user['id'])) {
            return $this->errorResponse($response, 'Unauthenticated', 401);
        }

        $body = (string)$request->getBody();
        $data = json_decode($body, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return $this->errorResponse($response, 'Invalid JSON payload', 400);
        }

        $subscriptionId = $data['subscription_id'] ?? $args['subscription_id'] ?? null;
        if (!$subscriptionId || !filter_var($subscriptionId, FILTER_VALIDATE_INT)) {
            return $this->errorResponse($response, 'Missing or invalid subscription_id', 400);
        }

        try {
            $stmt = $this->db->prepare('SELECT id, status FROM subscriptions WHERE id = :id AND user_id = :uid');
            $stmt->execute(['id' => $subscriptionId, 'uid' => $user['id']]);
            $subscription = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$subscription) {
                return $this->errorResponse($response, 'Subscription not found', 404);
            }
            if ($subscription['status'] !== 'active') {
                return $this->errorResponse($response, 'Subscription is not active', 400);
            }

            $now = (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('Y-m-d H:i:s');
            $stmt = $this->db->prepare('UPDATE subscriptions SET status = :status, updated_at = :updated WHERE id = :id');
            $stmt->execute([
                'status'  => 'cancelled',
                'updated' => $now,
                'id'      => $subscriptionId,
            ]);

            return $this->successResponse($response, ['subscription_id' => $subscriptionId, 'status' => 'cancelled']);
        } catch (PDOException $e) {
            $this->logger->error('Database error during subscription cancellation', ['exception' => $e]);
            return $this->errorResponse($response, 'Cancellation failed', 500);
        } catch (Exception $e) {
            $this->logger->error('Error during subscription cancellation', ['exception' => $e]);
            return $this->errorResponse($response, 'Cancellation failed', 500);
        }
    }

    public function getSubscriptionStatus(Request $request, Response $response, array $args): Response
    {
        $user = $request->getAttribute('user');
        if (empty($user['id'])) {
            return $this->errorResponse($response, 'Unauthenticated', 401);
        }

        $subscriptionId = $args['subscription_id'] ?? null;

        try {
            if ($subscriptionId && filter_var($subscriptionId, FILTER_VALIDATE_INT)) {
                $stmt = $this->db->prepare('SELECT id, plan_id, status, created_at, updated_at FROM subscriptions WHERE id = :id AND user_id = :uid');
                $stmt->execute(['id' => $subscriptionId, 'uid' => $user['id']]);
            } else {
                $stmt = $this->db->prepare('SELECT id, plan_id, status, created_at, updated_at FROM subscriptions WHERE user_id = :uid ORDER BY created_at DESC LIMIT 1');
                $stmt->execute(['uid' => $user['id']]);
            }

            $subscription = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$subscription) {
                return $this->successResponse($response, ['status' => 'none']);
            }

            return $this->successResponse($response, ['status' => $subscription['status'], 'subscription' => $subscription]);
        } catch (PDOException $e) {
            $this->logger->error('Database error fetching subscription status', ['exception' => $e]);
            return $this->errorResponse($response, 'Failed to retrieve subscription status', 500);
        } catch (Exception $e) {
            $this->logger->error('Error fetching subscription status', ['exception' => $e]);
            return $this->errorResponse($response, 'Failed to retrieve subscription status', 500);
        }
    }

    private function successResponse(Response $response, array $data, int $status = 200): Response
    {
        $payload = ['success' => true, 'data' => $data];
        $response = $response->withHeader('Content-Type', 'application/json')->withStatus($status);
        $response->getBody()->write(json_encode($payload));
        return $response;
    }

    private function errorResponse(Response $response, string $message, int $status): Response
    {
        $payload = ['success' => false, 'error' => $message];
        $response = $response->withHeader('Content-Type', 'application/json')->withStatus($status);
        $response->getBody()->write(json_encode($payload));
        return $response;
    }
}