<?php

// валидация формы
namespace App\Http\Requests;

use App\Models\RequestSubmission;

class UpdateLayoutApplicationRequest extends StoreLayoutApplicationRequest
{
    protected function prepareForValidation(): void
    {
        parent::prepareForValidation();

        $submission = $this->route('submission');
        if ($submission instanceof RequestSubmission) {
            $this->merge([
                'layout_structure_id' => $submission->layout_structure_id,
            ]);
        }
    }
}
