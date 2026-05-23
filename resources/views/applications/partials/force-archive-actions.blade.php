@php
    $stacked = (bool) ($stacked ?? false);
    $defaultBtnClass = $stacked
        ? 'ui-btn ui-btn--secondary w-full min-h-[44px] py-3 sm:min-h-0 sm:py-2 [touch-action:manipulation]'
        : 'ui-btn ui-btn--secondary';
    $restoreBtnClass = $restoreButtonClass ?? $defaultBtnClass;
    $archiveBtnClass = $archiveButtonClass ?? $defaultBtnClass;
    $formClass = ($inline ?? false) || ! $stacked ? 'inline' : '';
@endphp
@if($application->isAdminArchived())
    <form method="post"
          action="{{ route('applications.admin-unarchive', $application) }}"
          @class([$formClass])
          data-app-confirm="Заявка снова станет активной. Мастер участка сможет с ней работать как раньше."
          data-app-confirm-title="Вернуть заявку №{{ $application->id }} из архива?"
          data-app-confirm-label="Да, вернуть"
          data-app-confirm-variant="primary">
        @csrf
        <button type="submit" class="{{ $restoreBtnClass }}">
            Вернуть из архива
        </button>
    </form>
@elseif(! $application->archived_at)
    <form method="post"
          action="{{ route('applications.admin-archive', $application) }}"
          @class([$formClass])
          data-app-confirm="Заявка станет неактивной."
          data-app-confirm-title="Перенести заявку №{{ $application->id }} в архив?"
          data-app-confirm-label="Да, в архив"
          data-app-confirm-variant="danger">
        @csrf
        <button type="submit" class="{{ $archiveBtnClass }}">
            В архив
        </button>
    </form>
@endif
