<?php

namespace App\Http\Requests;

use App\Enums\SupportObjectType;
use App\Enums\SupportTicketCategory;
use App\Support\SupportPermissions;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreSupportTicketRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'category' => ['required', 'string', Rule::in(SupportTicketCategory::values())],
            'object_type' => ['nullable', 'string', Rule::in(SupportObjectType::values())],
            'object_id' => ['nullable', 'integer', 'required_with:object_type'],
            'subject' => ['required', 'string', 'max:160'],
            'message' => ['required', 'string', 'max:5000'],
            'attachment' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,webp,gif,doc,docx,xls,xlsx,csv,txt', 'max:10240'],
        ];
    }

    /**
     * Ensure the selected object exists and belongs to the seller (unless staff).
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $typeValue = $this->input('object_type');
            $objectId = $this->input('object_id');

            if (! $typeValue || ! $objectId) {
                return;
            }

            $type = SupportObjectType::tryFrom($typeValue);
            if (! $type) {
                return;
            }

            /** @var class-string<Model> $model */
            $model = $type->modelClass();
            $record = $model::query()->find($objectId);

            if (! $record) {
                $validator->errors()->add('object_id', __('support_tickets.errors.object_not_found'));

                return;
            }

            $user = $this->user();
            $isStaff = $user->hasPermission(SupportPermissions::READ_ALL)
                || $user->hasPermission(SupportPermissions::MANAGE);

            if (! $isStaff && (int) $record->{$type->ownerColumn()} !== (int) $user->id) {
                $validator->errors()->add('object_id', __('support_tickets.errors.object_forbidden'));
            }
        });
    }
}
