@php // шаблон страницы
@endphp
@if ($paginator->hasPages())
    <nav role="navigation" aria-label="{{ __('Pagination Navigation') }}" class="flex items-center justify-between">
        <div class="flex justify-between flex-1 sm:hidden">
            @if ($paginator->onFirstPage())
                <span class="relative inline-flex items-center px-4 py-2 text-sm font-medium text-black dark:text-white bg-stone-50 dark:bg-stone-900/20 border border-stone-300 dark:border-stone-700 cursor-default rounded-md">
                    {!! __('pagination.previous') !!}
                </span>
            @else
                <a href="{{ $paginator->previousPageUrl() }}" class="relative inline-flex items-center px-4 py-2 text-sm font-medium text-black dark:text-white bg-white dark:bg-stone-950 border border-stone-300 dark:border-stone-700 rounded-md hover:bg-stone-100 dark:hover:bg-stone-900/40 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-stone-500 dark:focus:ring-offset-stone-950">
                    {!! __('pagination.previous') !!}
                </a>
            @endif

            @if ($paginator->hasMorePages())
                <a href="{{ $paginator->nextPageUrl() }}" class="relative inline-flex items-center px-4 py-2 ml-3 text-sm font-medium text-black dark:text-white bg-white dark:bg-stone-950 border border-stone-300 dark:border-stone-700 rounded-md hover:bg-stone-100 dark:hover:bg-stone-900/40 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-stone-500 dark:focus:ring-offset-stone-950">
                    {!! __('pagination.next') !!}
                </a>
            @else
                <span class="relative inline-flex items-center px-4 py-2 ml-3 text-sm font-medium text-black dark:text-white bg-stone-50 dark:bg-stone-900/20 border border-stone-300 dark:border-stone-700 cursor-default rounded-md">
                    {!! __('pagination.next') !!}
                </span>
            @endif
        </div>

        <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-end">
            <div>
                <span class="relative z-0 inline-flex rtl:flex-row-reverse shadow-sm rounded-md">
                    @if ($paginator->onFirstPage())
                        <span aria-disabled="true" aria-label="{{ __('pagination.previous') }}">
                            <span class="relative inline-flex items-center px-2 py-2 text-sm font-medium text-black dark:text-white bg-stone-50 dark:bg-stone-900/20 border border-stone-300 dark:border-stone-700 cursor-default rounded-l-md" aria-hidden="true">
                                <svg class="w-5 h-5" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M12.707 5.293a1 1 0 010 1.414L9.414 10l3.293 3.293a1 1 0 01-1.414 1.414l-4-4a1 1 0 010-1.414l4-4a1 1 0 011.414 0z" clip-rule="evenodd" />
                                </svg>
                            </span>
                        </span>
                    @else
                        <a href="{{ $paginator->previousPageUrl() }}" rel="prev" class="relative inline-flex items-center px-2 py-2 text-sm font-medium text-black dark:text-white bg-white dark:bg-stone-950 border border-stone-300 dark:border-stone-700 rounded-l-md hover:bg-stone-100 dark:hover:bg-stone-900/40 focus:z-10 focus:outline-none focus:ring-1 focus:ring-stone-500 focus:border-stone-500" aria-label="{{ __('pagination.previous') }}">
                            <svg class="w-5 h-5" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M12.707 5.293a1 1 0 010 1.414L9.414 10l3.293 3.293a1 1 0 01-1.414 1.414l-4-4a1 1 0 010-1.414l4-4a1 1 0 011.414 0z" clip-rule="evenodd" />
                            </svg>
                        </a>
                    @endif

                    @foreach ($elements as $element)
                        @if (is_string($element))
                            <span aria-disabled="true">
                                <span class="relative inline-flex items-center px-4 py-2 -ml-px text-sm font-medium text-black dark:text-white bg-stone-50 dark:bg-stone-900/20 border border-stone-300 dark:border-stone-700 cursor-default">{{ $element }}</span>
                            </span>
                        @endif

                        @if (is_array($element))
                            @foreach ($element as $page => $url)
                                @if ($page == $paginator->currentPage())
                                    <span aria-current="page">
                                        <span class="relative inline-flex items-center px-4 py-2 -ml-px text-sm font-semibold text-white bg-stone-700 dark:bg-stone-300 border border-stone-700 dark:border-stone-300 cursor-default">{{ $page }}</span>
                                    </span>
                                @else
                                    <a href="{{ $url }}" class="relative inline-flex items-center px-4 py-2 -ml-px text-sm font-medium text-black dark:text-white bg-white dark:bg-stone-950 border border-stone-300 dark:border-stone-700 hover:bg-stone-100 dark:hover:bg-stone-900/40 focus:z-10 focus:outline-none focus:ring-1 focus:ring-stone-500 focus:border-stone-500" aria-label="{{ __('Go to page :page', ['page' => $page]) }}">
                                        {{ $page }}
                                    </a>
                                @endif
                            @endforeach
                        @endif
                    @endforeach

                    @if ($paginator->hasMorePages())
                        <a href="{{ $paginator->nextPageUrl() }}" rel="next" class="relative inline-flex items-center px-2 py-2 -ml-px text-sm font-medium text-black dark:text-white bg-white dark:bg-stone-950 border border-stone-300 dark:border-stone-700 rounded-r-md hover:bg-stone-100 dark:hover:bg-stone-900/40 focus:z-10 focus:outline-none focus:ring-1 focus:ring-stone-500 focus:border-stone-500" aria-label="{{ __('pagination.next') }}">
                            <svg class="w-5 h-5" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd" />
                            </svg>
                        </a>
                    @else
                        <span aria-disabled="true" aria-label="{{ __('pagination.next') }}">
                            <span class="relative inline-flex items-center px-2 py-2 -ml-px text-sm font-medium text-black dark:text-white bg-stone-50 dark:bg-stone-900/20 border border-stone-300 dark:border-stone-700 cursor-default rounded-r-md" aria-hidden="true">
                                <svg class="w-5 h-5" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd" />
                                </svg>
                            </span>
                        </span>
                    @endif
                </span>
            </div>
        </div>
    </nav>
@endif
