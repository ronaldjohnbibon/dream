<?php

namespace App\Modules\Users\Http\Requests;

use App\Modules\Users\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UpdateCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var User $customer */
        $customer = $this->route('customer');

        return $this->user()?->can('update', $customer) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        /** @var User $customer */
        $customer = $this->route('customer');

        return [
            'name'             => ['required', 'string', 'max:255'],
            'email'            => ['nullable', 'string', 'lowercase', 'email', 'max:255', Rule::unique(User::class)->ignore($customer)],
            'mobile_number'    => ['required', 'string', 'regex:/^(?:\+63|0)9\d{9}$/'],
            'complete_address' => ['required', 'string', 'max:2000'],
            'delivery_area'    => ['required', 'string', 'max:255'],
            'password'         => ['nullable', 'confirmed', Password::defaults()],
        ];
    }
}
