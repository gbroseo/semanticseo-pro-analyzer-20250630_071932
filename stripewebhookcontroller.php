public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
        $this->apiKey = getenv('STRIPE_SECRET_KEY') ?: '';
        if ($this->apiKey === '') {
            $this->logger->warning('Stripe secret key not configured.');
        }
        Stripe::setApiKey($this->apiKey);

        $this->webhookSecret = getenv('STRIPE_WEBHOOK_SECRET') ?: '';
        if ($this->webhookSecret === '') {
            $this->logger->warning('Stripe webhook secret not configured.');
        }
    }

    public function handleWebhook(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        if (strtoupper($request->getMethod()) !== 'POST') {
            return $this->respondJson($response, ['error' => 'Method Not Allowed. Use POST.'], 405);
        }

        if ($this->webhookSecret === '') {
            $this->logger->error('Stripe Webhook: Webhook secret is not configured.');
            return $this->respondJson($response, ['error' => 'Webhook secret not configured.'], 500);
        }

        $body = $request->getBody();
        $body->rewind();
        $payloadBody = $body->getContents();
        if ($payloadBody === '' || $payloadBody === false) {
            $this->logger->error('Stripe Webhook: Empty payload.');
            return $this->respondJson($response, ['error' => 'Empty payload.'], 400);
        }

        $signatureHeader = $request->getHeaderLine('Stripe-Signature');
        if ($signatureHeader === '') {
            $this->logger->error('Stripe Webhook: Missing Stripe-Signature header.');
            return $this->respondJson($response, ['error' => 'Missing signature header.'], 400);
        }

        try {
            $event = Webhook::constructEvent($payloadBody, $signatureHeader, $this->webhookSecret);
        } catch (UnexpectedValueException $e) {
            $this->logger->error('Stripe Webhook Error: Invalid payload - ' . $e->getMessage());
            return $this->respondJson($response, ['error' => 'Invalid payload.'], 400);
        } catch (SignatureVerificationException $e) {
            $this->logger->error('Stripe Webhook Error: Invalid signature - ' . $e->getMessage());
            return $this->respondJson($response, ['error' => 'Invalid signature.'], 400);
        } catch (\Exception $e) {
            $this->logger->error('Stripe Webhook Error: Unexpected error during verification - ' . $e->getMessage());
            return $this->respondJson($response, ['error' => 'Webhook verification failed.'], 500);
        }

        try {
            switch ($event->type) {
                case 'checkout.session.completed':
                    $this->handleCheckoutSessionCompleted($event->data->object);
                    break;
                case 'invoice.payment_succeeded':
                    $this->handleInvoicePaymentSucceeded($event->data->object);
                    break;
                case 'invoice.payment_failed':
                    $this->handleInvoicePaymentFailed($event->data->object);
                    break;
                case 'customer.subscription.created':
                    $this->handleSubscriptionCreated($event->data->object);
                    break;
                case 'customer.subscription.updated':
                    $this->handleSubscriptionUpdated($event->data->object);
                    break;
                case 'customer.subscription.deleted':
                    $this->handleSubscriptionDeleted($event->data->object);
                    break;
                default:
                    $this->logger->info('Stripe Webhook: Unhandled event type ' . $event->type);
                    break;
            }
        } catch (\Exception $e) {
            $this->logger->error('Stripe Webhook Error: Handler exception - ' . $e->getMessage());
            return $this->respondJson($response, ['error' => 'Webhook handler error.'], 500);
        }

        return $this->respondJson($response, ['status' => 'success'], 200);
    }

    private function respondJson(ResponseInterface $response, array $data, int $status): ResponseInterface
    {
        $payload = json_encode($data);
        $response = $response->withStatus($status)
                             ->withHeader('Content-Type', 'application/json');
        $response->getBody()->write($payload);
        return $response;
    }

    private function handleCheckoutSessionCompleted(object $session): void
    {
        $subscriptionId = $session->subscription ?? null;
        $customerId = $session->customer ?? null;
        if ($subscriptionId !== null && $customerId !== null) {
            SubscriptionService::activateSubscription($subscriptionId, $customerId);
        }
    }

    private function handleInvoicePaymentSucceeded(object $invoice): void
    {
        $subscriptionId = $invoice->subscription ?? null;
        if ($subscriptionId !== null) {
            SubscriptionService::markPaymentSucceeded(
                $subscriptionId,
                $invoice->id,
                $invoice->amount_paid,
                $invoice->period_start,
                $invoice->period_end
            );
        }
    }

    private function handleInvoicePaymentFailed(object $invoice): void
    {
        $subscriptionId = $invoice->subscription ?? null;
        if ($subscriptionId !== null) {
            SubscriptionService::markPaymentFailed(
                $subscriptionId,
                $invoice->id,
                $invoice->attempt_count,
                $invoice->next_payment_attempt
            );
        }
    }

    private function handleSubscriptionCreated(object $subscription): void
    {
        SubscriptionService::createSubscription($subscription);
    }

    private function handleSubscriptionUpdated(object $subscription): void
    {
        SubscriptionService::updateSubscription($subscription);
    }

    private function handleSubscriptionDeleted(object $subscription): void
    {
        SubscriptionService::cancelSubscription(
            $subscription->id,
            $subscription->cancel_at_period_end,
            $subscription->canceled_at
        );
    }
}