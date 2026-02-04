<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class StorePaymentRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'order_id' => 'required|integer|exists:orders,id',
            'payment_method' => 'required|string|in:credit_card,debit_card,paypal,bank_transfer',
            'card_number' => 'required_if:payment_method,credit_card,debit_card|string|nullable',
            'paypal_email' => 'required_if:payment_method,paypal|email|nullable',
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'order_id.required' => 'Order ID is required.',
            'order_id.integer' => 'Order ID must be an integer.',
            'order_id.exists' => 'The specified order does not exist.',
            'payment_method.required' => 'Payment method is required.',
            'payment_method.string' => 'Payment method must be a string.',
            'payment_method.in' => 'Payment method must be one of: credit_card, debit_card, paypal, bank_transfer.',
            'card_number.required_if' => 'Card number is required for credit card and debit card payments.',
            'paypal_email.required_if' => 'PayPal email is required for PayPal payments.',
            'paypal_email.email' => 'PayPal email must be a valid email address.',
        ];
    }

    /**
     * Handle a failed validation attempt.
     *
     * @throws \Illuminate\Http\Exceptions\HttpResponseException
     */
    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            response()->json([
                'errors' => $validator->errors(),
            ], 422)
        );
    }
}

