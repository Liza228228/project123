@props([
    'application',
    'formAction',
    'checkboxName',
    'reasonName',
    'submitLabel' => 'Сохранить согласование',
    'formId' => 'commercial-offer-approval-form',
    'showViewLink' => false,
])

<form method="POST" action="{{ $formAction }}" id="{{ $formId }}" class="mt-4 space-y-4">
    @csrf
    @include('applications.partials.commercial-offer-approval-fields', [
        'application' => $application,
        'checkboxName' => $checkboxName,
        'reasonName' => $reasonName,
        'showViewLink' => $showViewLink,
    ])
    <div>
        <button type="submit" class="ui-btn ui-btn--primary">{{ $submitLabel }}</button>
    </div>
</form>
<script>
    (function () {
        var form = document.getElementById(@js($formId));
        if (!form) return;
        var cb = form.querySelector('.co-approval-checkbox');
        var block = form.querySelector('.co-approval-reason-block');
        var reason = form.querySelector('.co-approval-reason-input');
        function sync() {
            if (!cb || !block || !reason) return;
            if (cb.checked) {
                block.classList.add('hidden');
                reason.value = '';
            } else {
                block.classList.remove('hidden');
            }
        }
        cb?.addEventListener('change', sync);
        sync();
    })();
</script>
