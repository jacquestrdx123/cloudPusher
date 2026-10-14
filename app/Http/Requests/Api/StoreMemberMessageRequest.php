<?php

namespace App\Http\Requests\Api;

use App\Actions\SendMemberMessage;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMemberMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'body' => ['nullable', 'string', 'max:2000'],
            'recipients' => ['required', 'array'],
            'recipients.type' => ['required', Rule::in([
                SendMemberMessage::RECIPIENTS_USERS,
                SendMemberMessage::RECIPIENTS_GROUP,
                SendMemberMessage::RECIPIENTS_BROADCAST,
            ])],
            'recipients.user_ids' => [
                'required_if:recipients.type,'.SendMemberMessage::RECIPIENTS_USERS,
                'array',
                'min:1',
                'max:100',
            ],
            'recipients.user_ids.*' => ['integer', 'distinct'],
            'recipients.group_id' => [
                'required_if:recipients.type,'.SendMemberMessage::RECIPIENTS_GROUP,
                'integer',
            ],
        ];
    }

    /**
     * @return array{
     *     title: string,
     *     body: string|null,
     *     recipients: array{type: string, user_ids?: array<int, int>, group_id?: int}
     * }
     */
    public function payload(): array
    {
        $recipients = ['type' => $this->string('recipients.type')->toString()];

        if ($recipients['type'] === SendMemberMessage::RECIPIENTS_USERS) {
            $recipients['user_ids'] = array_map('intval', $this->array('recipients.user_ids'));
        }

        if ($recipients['type'] === SendMemberMessage::RECIPIENTS_GROUP) {
            $recipients['group_id'] = $this->integer('recipients.group_id');
        }

        return [
            'title' => $this->string('title')->trim()->toString(),
            'body' => $this->filled('body') ? $this->string('body')->trim()->toString() : null,
            'recipients' => $recipients,
        ];
    }
}
