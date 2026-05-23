@php
    $reservedStatusCodes = ['profile-updated', 'password-updated'];
    $statusMessage = session('status');
    $showStatus = filled($statusMessage) && ! in_array($statusMessage, $reservedStatusCodes, true);

    $flashes = [];
    if ($showStatus) {
        $flashes[] = ['type' => 'success', 'message' => $statusMessage];
    }
    foreach (['success', 'error', 'warning', 'info'] as $key) {
        $message = session($key);
        if (filled($message)) {
            $flashes[] = ['type' => $key, 'message' => $message];
        }
    }
@endphp

@if ($flashes !== [])
    <div {{ $attributes->class(['space-y-3']) }} aria-live="polite">
        @foreach ($flashes as $flash)
            <x-app-alert :type="$flash['type']" dismissible>
                {{ $flash['message'] }}
            </x-app-alert>
        @endforeach
    </div>
@endif
