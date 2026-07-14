<?php

namespace App\Http\Requests;

use App\Http\Concerns\ValidatesNotificationPayload;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class WebhookPushRequest extends FormRequest
{
    use ValidatesNotificationPayload;

    /**
     * The signature middleware already authenticated the caller.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return $this->notificationPayloadRules();
    }

    protected function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $this->validateNotificationTarget($validator);
        });
    }
}
