<?php

namespace App\Http\Requests;

use App\Models\RequestSubmission;

class UpdateLayoutApplicationRequest extends StoreLayoutApplicationRequest
{
    protected function prepareForValidation(): void
    {
        $submission = $this->route('submission');
        if ($submission instanceof RequestSubmission) {
            $this->merge([
                'layout_structure_id' => $submission->layout_structure_id,
            ]);
        }
    }
}
