@php // шаблон страницы
@endphp
@props([
    'application',
    'thumbSize' => 'sm',
])

@php
    use Illuminate\Support\Js;

    $photos = $application->installationActPhotos;
@endphp

@if($photos->isNotEmpty())
    @php
        $photoUrls = $photos->values()->map(fn ($p) => route('applications.installation-act.photo', [$application, $p]))->all();
        $thumbClasses = $thumbSize === 'md' ? 'h-28 w-28' : 'h-20 w-20';
        $gapClass = $thumbSize === 'md' ? 'gap-3' : 'gap-2';
    @endphp
    <div
        class="installation-act-photo-gallery"
        x-data="installationActPhotoGallery({ urls: {{ Js::from($photoUrls) }} })"
        @keydown.escape.window="open && close()"
        @keydown.window="if (!open) return; if ($event.key === 'ArrowLeft') { $event.preventDefault(); prev(); } if ($event.key === 'ArrowRight') { $event.preventDefault(); next(); }"
    >
        <div class="flex flex-wrap {{ $gapClass }}">
            @foreach($photos as $idx => $installationActPhoto)
                <button
                    type="button"
                    class="{{ $thumbClasses }} shrink-0 overflow-hidden rounded-xl border border-stone-200/90 bg-stone-100 shadow-sm ring-offset-2 transition hover:ring-2 hover:ring-orange-400/50 focus:outline-none focus:ring-2 focus:ring-orange-500/60 dark:border-stone-600 dark:bg-stone-800 dark:ring-offset-stone-900"
                    @click="openAt({{ $idx }})"
                    aria-label="Показать фото {{ $idx + 1 }} крупно"
                >
                    <img
                        src="{{ route('applications.installation-act.photo', [$application, $installationActPhoto]) }}"
                        alt=""
                        class="h-full w-full object-cover pointer-events-none"
                        loading="lazy"
                    />
                </button>
            @endforeach
        </div>

        <div
            x-show="open"
            x-cloak
            x-transition.opacity
            class="fixed inset-0 z-[100] flex items-center justify-center p-4 sm:p-8"
            role="dialog"
            aria-modal="true"
            :aria-label="lightAlt"
        >
            <div
                class="absolute inset-0 bg-black/75 backdrop-blur-sm"
                @click="close()"
                aria-hidden="true"
            ></div>

            <div class="relative z-10 flex max-h-[min(92vh,900px)] w-full max-w-5xl flex-col items-center gap-3">
                <div class="flex w-full items-center justify-between gap-2 text-white">
                    <p class="min-w-0 truncate text-sm font-medium drop-shadow-md" x-text="lightAlt"></p>
                    <button
                        type="button"
                        class="shrink-0 rounded-xl border border-white/30 bg-white/10 px-3 py-2 text-sm font-medium text-white backdrop-blur hover:bg-white/20 focus:outline-none focus:ring-2 focus:ring-white/50"
                        @click="close()"
                    >
                        Закрыть
                    </button>
                </div>

                <div class="relative flex w-full max-w-full flex-1 items-center justify-center">
                    @if($photos->count() > 1)
                        <button
                            type="button"
                            class="absolute left-0 z-20 hidden h-11 w-11 shrink-0 items-center justify-center rounded-full border border-white/25 bg-white/15 text-lg text-white backdrop-blur hover:bg-white/25 focus:outline-none focus:ring-2 focus:ring-white/40 sm:flex"
                            :class="{ 'opacity-40 pointer-events-none': lightIndex <= 0 }"
                            @click.stop="prev()"
                            aria-label="Предыдущее фото"
                        >
                            ‹
                        </button>
                        <button
                            type="button"
                            class="absolute right-0 z-20 hidden h-11 w-11 shrink-0 items-center justify-center rounded-full border border-white/25 bg-white/15 text-lg text-white backdrop-blur hover:bg-white/25 focus:outline-none focus:ring-2 focus:ring-white/40 sm:flex"
                            :class="{ 'opacity-40 pointer-events-none': lightIndex >= urls.length - 1 }"
                            @click.stop="next()"
                            aria-label="Следующее фото"
                        >
                            ›
                        </button>
                    @endif

                    <img
                        x-show="open && lightSrc"
                        :src="lightSrc"
                        :alt="lightAlt"
                        class="max-h-[min(85vh,820px)] max-w-full rounded-xl border border-white/20 object-contain shadow-2xl"
                        @click.stop
                    />
                </div>

                @if($photos->count() > 1)
                    <div class="flex w-full justify-center gap-2 sm:hidden">
                        <button
                            type="button"
                            class="rounded-lg border border-white/30 bg-white/10 px-4 py-2 text-sm text-white"
                            :class="{ 'opacity-40 pointer-events-none': lightIndex <= 0 }"
                            @click="prev()"
                        >
                            Назад
                        </button>
                        <button
                            type="button"
                            class="rounded-lg border border-white/30 bg-white/10 px-4 py-2 text-sm text-white"
                            :class="{ 'opacity-40 pointer-events-none': lightIndex >= urls.length - 1 }"
                            @click="next()"
                        >
                            Вперёд
                        </button>
                    </div>
                @endif
            </div>
        </div>
    </div>
@endif
