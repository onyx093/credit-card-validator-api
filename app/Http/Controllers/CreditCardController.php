<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;

class CreditCardController extends Controller
{
    public function validateCreditCard(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'creditCardNumber' => [
                'required',
                'numeric',
                'digits_between:16,19',
                function ($attribute, $value, $fail) {
                    $cardNumbers = array_map('intval', array_reverse(str_split($value)));
                    for ($i = 0; $i < count($cardNumbers); $i++) {
                        if (($i % 2) !== 0) {
                            $cardNumbers[$i] *= 2;
                            if($cardNumbers[$i] > 9)
                            {
                                $cardNumbers[$i] -= 9;
                            }
                        }
                    }
                    if (array_sum($cardNumbers) % 10 !== 0) {
                        $fail('The :attribute is incorrect or invalid.');
                    }
                },
            ],
            'expiryDate' => [
                'required',
                'regex:/^(0[1-9]|1[0-2])\/([0-9]{2})$/',
                function ($attribute, $value, $fail) {
                    $expiry = Carbon::createFromFormat('m/y', $value);
                    $currentDate = Carbon::now();

                    if (!$expiry || $expiry->lessThan($currentDate)) {
                        $fail('The :attribute is invalid or has already expired.');
                    }
                },
            ],
            'cvv' => [
                'required',
                'numeric',
                function ($attribute, $value, $fail) use ($request){
                    $cardType = $this->getCardType($request->input('creditCardNumber'));
                    if ($cardType === 'amex') {
                        if (strlen($value) !== 4) {
                            $fail('The :attribute must be a 4-digit number for American Express cards.');
                        }
                    } else {
                        if (strlen($value) !== 3) {
                            $fail('The :attribute must be a 3-digit number.');
                        }
                    }
                }
            ],
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }
        return response()->json([...$validator->validated(), 'validation_success' => 'true'], 200);
    }

    private function getCardType($cardNumber)
    {
        $cardTypeDigits = substr($cardNumber, 0, 2);

        if (str_contains($cardTypeDigits, '34') || str_contains($cardTypeDigits, '37')) {
            return 'amex';
        } elseif (str_starts_with($cardNumber, '4')) {
            return 'visa';
        } elseif (str_starts_with($cardNumber, '5')) {
            return 'mastercard';
        }

        return 'unknown';
    }
}
