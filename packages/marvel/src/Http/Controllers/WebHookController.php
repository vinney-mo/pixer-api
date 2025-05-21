<?php

namespace Marvel\Http\Controllers;

use Illuminate\Http\Request;
use Marvel\Facades\Payment;
use Marvel\Payments\Flutterwave;

class WebHookController extends CoreController
{

    public function stripe(Request $request)
    {
        return Payment::handleWebHooks($request);
    }

    public function paypal(Request $request)
    {
        return Payment::handleWebHooks($request);
    }

    public function razorpay(Request $request)
    {
        return Payment::handleWebHooks($request);
    }
    public function mollie(Request $request)
    {
        return Payment::handleWebHooks($request);
    }
    public function sslcommerz(Request $request)
    {
        return Payment::handleWebHooks($request);
    }
    public function paystack(Request $request)
    {
        return Payment::handleWebHooks($request);
    }
    public function paymongo(Request $request)
    {
        return Payment::handleWebHooks($request);
    }
    public function xendit(Request $request)
    {
        return Payment::handleWebHooks($request);
    }
    public function iyzico(Request $request)
    {
        return Payment::handleWebHooks($request);
    }
    public function bitpay(Request $request)
    {
        return Payment::handleWebHooks($request);
    }
    public function coinbase(Request $request)
    {
        return Payment::handleWebHooks($request);
    }
    public function bkash(Request $request)
    {
        return Payment::handleWebHooks($request);
    }
    public function flutterwave(Request $request)
    {
        return Payment::handleWebHooks($request);
    }
    public function callback(Request $request)
    {
        return Flutterwave::callback($request);
    }

    /**
     * Handle Stripe Checkout success callback
     *
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function stripeSuccess(Request $request)
    {
        // Get the session ID from the request
        $session_id = $request->get('session_id');

        if (!$session_id) {
            return redirect()->to(config('shop.stripe.frontend_callback_url', '/checkout/payment-failed'));
        }

        try {
            // Retrieve the session from Stripe
            $stripe = new \Marvel\Payments\Stripe();
            $session = $stripe->stripe->checkout->sessions->retrieve($session_id);

            // Check if payment was successful
            if ($session->payment_status === 'paid') {
                // Get the order tracking number from the session metadata
                $order_tracking_number = $session->metadata->order_tracking_number ?? null;

                if ($order_tracking_number) {
                    // Redirect to the frontend success page with the order tracking number
                    return redirect()->to(
                        config('shop.stripe.frontend_success_url', '/checkout/payment-success') .
                        '?order_tracking_number=' . $order_tracking_number
                    );
                }
            }

            // If we get here, something went wrong
            return redirect()->to(config('shop.stripe.frontend_callback_url', '/checkout/payment-failed'));
        } catch (\Exception $e) {
            \Log::error('Stripe success callback error: ' . $e->getMessage());
            return redirect()->to(config('shop.stripe.frontend_callback_url', '/checkout/payment-failed'));
        }
    }

    /**
     * Handle Stripe Checkout cancel callback
     *
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function stripeCancel(Request $request)
    {
        // Redirect to the frontend cancel page
        return redirect()->to(config('shop.stripe.frontend_cancel_url', '/checkout/payment-failed'));
    }
}
