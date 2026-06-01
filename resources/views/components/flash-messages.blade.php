@php
    // шаблон страницы
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

    $seenMessages = [];
    $flashes = array_values(array_filter($flashes, static function (array $flash) use (&$seenMessages): bool {
        $text = (string) $flash['message'];
        if (isset($seenMessages[$text])) {
            return false;
        }
        $seenMessages[$text] = true;

        return true;
    }));
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
