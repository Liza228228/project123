<?php

namespace App\Http\Controllers;

use App\Models\ApplicationReportFooter;
use App\Support\ReportFontChoices;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ApplicationReportFooterController extends Controller
{
    public function index(): View
    {
        $footers = ApplicationReportFooter::query()->orderBy('name')->get();

        return view('applications.report.footers.index', compact('footers'));
    }

    public function create(): View
    {
        $settings = ApplicationReportFooter::defaultSettings();
        $fontOptions = ReportFontChoices::options();

        return view('applications.report.footers.create', compact('settings', 'fontOptions'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'settings' => ['nullable', 'array'],
            'settings.font_family' => ['required', Rule::in(ReportFontChoices::values())],
            'settings.chairman_align' => ['required', Rule::in(['left', 'center', 'right'])],
            'settings.members_align' => ['required', Rule::in(['left', 'center', 'right'])],
            'settings.chairman_label' => ['nullable', 'string', 'max:200'],
            'settings.chairman_sig_caption' => ['nullable', 'string', 'max:300'],
            'settings.chairman_name_caption' => ['nullable', 'string', 'max:300'],
            'settings.members_label' => ['nullable', 'string', 'max:200'],
            'settings.members_count' => ['nullable', 'integer', 'min:1', 'max:12'],
            'settings.member_sig_caption' => ['nullable', 'string', 'max:300'],
            'settings.member_name_caption' => ['nullable', 'string', 'max:300'],
        ]);

        ApplicationReportFooter::query()->create([
            'name' => $validated['name'],
            'settings' => $this->normalizeFooterSettings($validated['settings'] ?? []),
        ]);

        return redirect()
            ->route('applications.report.footers.index')
            ->with('status', 'Подвал сохранён.');
    }

    public function edit(ApplicationReportFooter $footer): View
    {
        $settings = $footer->mergedSettings();
        $fontOptions = ReportFontChoices::options();

        return view('applications.report.footers.edit', compact('footer', 'settings', 'fontOptions'));
    }

    public function update(Request $request, ApplicationReportFooter $footer): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'settings' => ['nullable', 'array'],
            'settings.font_family' => ['required', Rule::in(ReportFontChoices::values())],
            'settings.chairman_align' => ['required', Rule::in(['left', 'center', 'right'])],
            'settings.members_align' => ['required', Rule::in(['left', 'center', 'right'])],
            'settings.chairman_label' => ['nullable', 'string', 'max:200'],
            'settings.chairman_sig_caption' => ['nullable', 'string', 'max:300'],
            'settings.chairman_name_caption' => ['nullable', 'string', 'max:300'],
            'settings.members_label' => ['nullable', 'string', 'max:200'],
            'settings.members_count' => ['nullable', 'integer', 'min:1', 'max:12'],
            'settings.member_sig_caption' => ['nullable', 'string', 'max:300'],
            'settings.member_name_caption' => ['nullable', 'string', 'max:300'],
        ]);

        $footer->update([
            'name' => $validated['name'],
            'settings' => $this->normalizeFooterSettings($validated['settings'] ?? []),
        ]);

        return redirect()
            ->route('applications.report.footers.index')
            ->with('status', 'Подвал обновлён.');
    }

    public function destroy(ApplicationReportFooter $footer): RedirectResponse
    {
        $footer->delete();

        return redirect()
            ->route('applications.report.footers.index')
            ->with('status', 'Подвал удалён.');
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private function normalizeFooterSettings(array $input): array
    {
        $merged = array_replace_recursive(ApplicationReportFooter::defaultSettings(), $input);
        $merged['members_count'] = max(1, min(12, (int) ($merged['members_count'] ?? 3)));

        return $merged;
    }

}
