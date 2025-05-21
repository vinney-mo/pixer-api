<?php

namespace Marvel\Payments;

use Exception;

use Marvel\Database\Models\Order;
use Stripe\StripeClient;
use Marvel\Payments\PaymentInterface;
use Marvel\Payments\Base;
use Marvel\Traits\OrderStatusManagerWithPaymentTrait;
use Marvel\Enums\OrderStatus;
use Marvel\Enums\PaymentStatus;
use Marvel\Traits\PaymentTrait;
use Symfony\Component\HttpKernel\Exception\HttpException;

class Stripe extends Base implements PaymentInterface
{

  use OrderStatusManagerWithPaymentTrait;
  use PaymentTrait;

  public $stripe;

  /**
   * __construct
   *
   * @return void
   */
  public function __construct()
  {
    parent::__construct();
    $this->stripe = new StripeClient(config('shop.stripe.api_secret'));
  }

  /**
   * createCustomer
   *
   * @param  object $request
   * @return array
   */
  public function createCustomer($request): array
  {
    $customer = $this->stripe->customers->create([
      'email' => $request->user()->email,
      'name'  => $request->user()->name
    ]);

    return [
      'customer_id' => $customer->id,
      'customer_email' => $customer->email,
    ];
  }

  /**
   * attachPaymentMethodToCustomer
   *
   * @param  string $retrieved_payment_method
   * @param  object $request
   * @return object
   */
  public function attachPaymentMethodToCustomer($retrieved_payment_method, $request): object
  {
    $customer = $this->createPaymentCustomer($request);
    $attachedPaymentMethod = $this->stripe->paymentMethods->attach(
      $retrieved_payment_method,
      [
        'customer' => $customer['customer_id']
      ]
    );

    return $attachedPaymentMethod;
  }

  /**
   * detachPaymentMethodToCustomer
   *
   * @param  string $retrieved_payment_method
   * @return object
   */
  public function detachPaymentMethodToCustomer($retrieved_payment_method): object
  {
    $detachedPaymentMethod = $this->stripe->paymentMethods->detach(
      $retrieved_payment_method,
      []
    );

    return $detachedPaymentMethod;
  }

  /**
   * getIntent
   *
   * @param  array $data
   * @return array
   */
  public function getIntent($data): array
  {

    try {
      extract($data);

      // Get the success and cancel URLs from config or use defaults
      $success_url = config('shop.stripe.success_url', url('/stripe/payment-success'));
      $cancel_url = config('shop.stripe.cancel_url', url('/stripe/payment-cancel'));

      // Create a Checkout Session for hosted checkout
      $checkout_session = [
        'payment_method_types' => ['card'],
        'line_items' => [[
          'price_data' => [
            'currency' => $this->currency,
            'product_data' => [
              'name' => 'Order Payment',
              'description' => 'Marvel Payment',
            ],
            'unit_amount' => round($amount, 2) * 100,
          ],
          'quantity' => 1,
        ]],
        'mode' => 'payment',
        'success_url' => $success_url . '?session_id={CHECKOUT_SESSION_ID}',
        'cancel_url' => $cancel_url,
        'metadata' => [
          'order_tracking_number' => $order_tracking_number,
        ]
      ];

      if (isset($customer)) {
        $checkout_session['customer'] = $customer;
      }

      $session = $this->stripe->checkout->sessions->create($checkout_session);

      return [
        'client_secret' => $session->id,
        'payment_id'    => $session->payment_intent,
        'is_redirect'   => true,
        'redirect_url'  => $session->url
      ];
    } catch (\Stripe\Exception\CardException $e) {
      throw new HttpException(400, INVALID_CARD);
    } catch (\Stripe\Exception\RateLimitException $e) {
      throw new HttpException(400, TOO_MANY_REQUEST);
    } catch (\Stripe\Exception\InvalidRequestException $e) {
      throw new HttpException(400, INVALID_REQUEST);
    } catch (\Stripe\Exception\InvalidArgumentException $e) {
      throw new HttpException(400, INVALID_REQUEST);
    } catch (\Stripe\Exception\AuthenticationException $e) {
      throw new HttpException(400, AUTHENTICATION_FAILED);
    } catch (\Stripe\Exception\ApiConnectionException $e) {
      throw new HttpException(400, API_CONNECTION_FAILED);
    } catch (\Stripe\Exception\ApiErrorException $e) {
      throw new HttpException(400, API_CONNECTION_FAILED);
    } catch (Exception $e) {
      throw new HttpException(400, SOMETHING_WENT_WRONG_WITH_PAYMENT);
    }
  }

  /**
   * retrievePaymentIntent
   *
   * @param  string $payment_intent_id
   * @return object
   */
  public function retrievePaymentIntent($payment_intent_id): object
  {
    try {
      $intent_data = $this->stripe->paymentIntents->retrieve(
        $payment_intent_id
      );

      // expected payment-intent status

      // succeeded  ------------------- Customer completed payment on your checkout page
      // requires_action -------------- Customer did not complete the checkout
      // requires_payment_method ------ Customer’s payment failed on your checkout page

      return $intent_data;
    } catch (\Stripe\Exception\ApiErrorException $e) {
      throw new HttpException(400, API_CONNECTION_FAILED);
    } catch (\Stripe\Exception\RateLimitException $e) {
      throw new HttpException(400, TOO_MANY_REQUEST);
    } catch (\Stripe\Exception\InvalidRequestException $e) {
      throw new HttpException(400, INVALID_REQUEST);
    } catch (\Stripe\Exception\InvalidArgumentException $e) {
      throw new HttpException(400, INVALID_REQUEST);
    } catch (\Stripe\Exception\AuthenticationException $e) {
      throw new HttpException(400, AUTHENTICATION_FAILED);
    } catch (\Stripe\Exception\ApiConnectionException $e) {
      throw new HttpException(400, API_CONNECTION_FAILED);
    } catch (\Stripe\Exception\ApiErrorException $e) {
      throw new HttpException(400, API_CONNECTION_FAILED);
    } catch (\Exception $e) {
      throw new HttpException(400, SOMETHING_WENT_WRONG_WITH_PAYMENT);
    }
  }

  /**
   * confirmPaymentIntent
   *
   * @param  string $payment_intent_id
   * @param  array $data
   * @return object
   */
  public function confirmPaymentIntent($payment_intent_id, $data): object
  {
    try {
      $intent_data = $this->stripe->paymentIntents->confirm(
        $payment_intent_id,
        $data
      );

      return $intent_data;
    } catch (\Stripe\Exception\ApiErrorException $e) {
      throw new HttpException(400, API_CONNECTION_FAILED);
    } catch (\Stripe\Exception\RateLimitException $e) {
      throw new HttpException(400, TOO_MANY_REQUEST);
    } catch (\Stripe\Exception\InvalidRequestException $e) {
      throw new HttpException(400, INVALID_REQUEST);
    } catch (\Stripe\Exception\InvalidArgumentException $e) {
      throw new HttpException(400, INVALID_REQUEST);
    } catch (\Stripe\Exception\AuthenticationException $e) {
      throw new HttpException(400, AUTHENTICATION_FAILED);
    } catch (\Stripe\Exception\ApiConnectionException $e) {
      throw new HttpException(400, API_CONNECTION_FAILED);
    } catch (\Stripe\Exception\ApiErrorException $e) {
      throw new HttpException(400, API_CONNECTION_FAILED);
    } catch (\Exception $e) {
      throw new HttpException(400, SOMETHING_WENT_WRONG_WITH_PAYMENT);
    }
  }

  /**
   * verify
   *
   * @param $id
   * @return bool
   * @throws Exception
   */
  public function verify($id): bool
  {
    try {
      // Check if the ID is a session ID (starts with 'cs_') or a payment intent ID
      if (strpos($id, 'cs_') === 0) {
        // It's a checkout session ID
        $session = $this->stripe->checkout->sessions->retrieve($id);

        // Check if the payment status is paid
        return $session->payment_status === 'paid';
      } else {
        // It's a regular payment ID (charge or payment intent)
        $payment = $this->stripe->charges->retrieve($id);
        return isset($payment->paid) ? $payment->paid : false;
      }
    } catch (\Stripe\Exception\CardException $e) {
      throw new HttpException(400, INVALID_CARD);
    } catch (\Stripe\Exception\RateLimitException $e) {
      throw new HttpException(400, TOO_MANY_REQUEST);
    } catch (\Stripe\Exception\InvalidRequestException $e) {
      throw new HttpException(400, INVALID_REQUEST);
    } catch (\Stripe\Exception\AuthenticationException $e) {
      throw new HttpException(400, AUTHENTICATION_FAILED);
    } catch (\Stripe\Exception\ApiConnectionException $e) {
      throw new HttpException(400, API_CONNECTION_FAILED);
    } catch (\Stripe\Exception\ApiErrorException $e) {
      throw new HttpException(400, API_CONNECTION_FAILED);
    } catch (Exception $e) {
      throw new HttpException(400, SOMETHING_WENT_WRONG_WITH_PAYMENT);
    }
  }

  /**
   * handleWebHooks
   *
   * @param  object $request
   * @return void
   */
  public function handleWebHooks($request): void
  {
    $endpoint_secret = config('shop.stripe.webhook_secret');
    $payload = @file_get_contents('php://input');
    $sig_header = $_SERVER['HTTP_STRIPE_SIGNATURE'];
    $event = null;

    try {
      $event = \Stripe\Webhook::constructEvent(
        $payload,
        $sig_header,
        $endpoint_secret
      );
    } catch (\UnexpectedValueException $e) {
      // Invalid payload
      http_response_code(400);
      exit();
    } catch (\Stripe\Exception\SignatureVerificationException $e) {
      // Invalid signature
      http_response_code(400);
      exit();
    }

    $intent = $this->matchSucceededOrFailed($request);

    if (!$intent) {
      http_response_code(200);
      exit();
    }

    $webhook_return_message = [
      'charge_status'     => $intent['status'],
      'payment_intent'    => $intent['payment_intent'],
      'amount'            => $intent['amount'],
      'paid'              => $intent['paid'],
      'payment_method'    => $intent['payment_method_details']['type'],
      'amount_captured'   => $intent['amount_captured'],
      'order_tracking_id' => $intent['metadata']['order_tracking_number'],
    ];

    // send response to check charge status
    $this->paymentGatewayWebHookResponse($webhook_return_message);

    http_response_code(200);
    exit();
  }

  /**
   * paymentGatewayWebHookResponse
   *
   * @param  array $data
   * @return void
   */
  public function paymentGatewayWebHookResponse($data)
  {
    $order = Order::where('tracking_number', '=', $data['order_tracking_id'])->firstOrFail();

    if (isset($data)) {
      switch ($data['charge_status']) {
        case 'succeeded':
          # code...
          # Throw email to admin that order is success.
          $this->webhookSuccessResponse($order, OrderStatus::PROCESSING, PaymentStatus::SUCCESS);
          break;

        case 'pending':
          # code...
          # Throw email to admin that order is pending.
          # Throw email to user that order is pending.
          $order->order_status = OrderStatus::PENDING;
          $order->payment_status = PaymentStatus::AWAITING_FOR_APPROVAL;
          $order->save();
          $this->orderStatusManagementOnPayment($order, OrderStatus::PENDING, PaymentStatus::AWAITING_FOR_APPROVAL);
          break;

        case 'failed':
          # code...
          # Throw email to admin that order is pending.
          # Throw email to user that order is failure.
          $order->order_status = OrderStatus::PENDING;
          $order->payment_status = PaymentStatus::FAILED;
          $order->save();
          $this->orderStatusManagementOnPayment($order, OrderStatus::PENDING, PaymentStatus::FAILED);
          break;
      }
    }
  }

  /**
   * retrievePaymentMethod
   *
   * @param  string $method_key
   * @return object
   */
  public function retrievePaymentMethod($method_key): object
  {
    try {
      $retrieved_method =  $this->stripe->paymentMethods->retrieve(
        $method_key,
        []
      );

      return $retrieved_method;
    } catch (Exception $e) {
      throw $e;
    }
  }

  /**
   * setIntent
   *
   * @param  array $data
   * @return array
   */
  public function setIntent($data): array
  {
    try {
      $intent = $this->stripe->setupIntents->create($data);
      return [
        'client_secret' => $intent->client_secret,
        'intent_id'     => $intent->id,
      ];
    } catch (Exception $e) {
      throw $e;
    }
  }

  public function matchSucceededOrFailed($request)
  {
    if (isset($request?->data['object']['object'])) {
      if ($request?->data['object']['object'] == 'charge') {
        return $request?->data['object'];
      } elseif ($request?->data['object']['object'] == 'checkout.session') {
        // Handle checkout session events
        $session = $request?->data['object'];

        // If the session has a payment intent, retrieve it to get charge details
        if (isset($session['payment_intent'])) {
          try {
            $payment_intent = $this->stripe->paymentIntents->retrieve($session['payment_intent']);

            // If the payment intent has charges, return the latest charge
            if (isset($payment_intent['latest_charge'])) {
              $charge = $this->stripe->charges->retrieve($payment_intent['latest_charge']);
              return $charge;
            }
          } catch (\Exception $e) {
            // Log the error but continue processing
            \Log::error('Error retrieving payment intent or charge: ' . $e->getMessage());
          }
        }

        // If we couldn't get charge details, create a minimal charge object with session data
        return [
          'status' => $session['payment_status'] === 'paid' ? 'succeeded' : 'failed',
          'payment_intent' => $session['payment_intent'] ?? null,
          'amount' => $session['amount_total'] ?? 0,
          'paid' => $session['payment_status'] === 'paid',
          'payment_method_details' => ['type' => 'card'], // Default to card
          'amount_captured' => $session['amount_total'] ?? 0,
          'metadata' => $session['metadata'] ?? [],
        ];
      }
    }
    return false;
  }
}
