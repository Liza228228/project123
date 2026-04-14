<?php

namespace App\Http\Controllers;

use App\Models\ApplicationReportHeader;
use App\Models\Role;
use App\Models\User;
use App\Support\ReportFontChoices;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ApplicationReportHeaderController extends Controller
{
    public function index(): View
    {
        $headers = ApplicationReportHeader::query()->orderBy('name')->get();

        return view('applications.report.headers.index', compact('headers'));
    }

    public function create(): View
    {
        $settings = ApplicationReportHeader::defaultSettings();
        $fontOptions = ReportFontChoices::options();
        [$approverRoles, $approversByRole] = $this->approverMeta();

        return view('applications.report.headers.create', compact('settings', 'fontOptions', 'approverRoles', 'approversByRole'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'settings' => ['nullable', 'array'],
            'settings.font_family' => ['required', Rule::in(ReportFontChoices::values())],
            'settings.title_font_family' => ['nullable', 'string', 'max:120'],
            'settings.org_align' => ['required', Rule::in(['left', 'center', 'right'])],
            'settings.approval_align' => ['required', Rule::in(['left', 'center', 'right'])],
            'settings.title_align' => ['required', Rule::in(['left', 'center', 'right'])],
            'settings.org_name' => ['nullable', 'string', 'max:2000'],
            'settings.org_caption' => ['nullable', 'string', 'max:500'],
            'settings.approval_label' => ['nullable', 'string', 'max:200'],
            'settings.approval_position' => ['nullable', 'string', 'max:500'],
            'settings.approval_name' => ['nullable', 'string', 'max:500'],
            'settings.approval_position_caption' => ['nullable', 'string', 'max:500'],
            'settings.approval_name_caption' => ['nullable', 'string', 'max:500'],
            'settings.title' => ['nullable', 'string', 'max:2000'],
            'settings.title_font_pt' => ['nullable', 'integer', 'min:8', 'max:36'],
            'settings.date_text' => ['nullable', 'date_format:Y-m-d'],
            'settings.city_text' => ['nullable', 'string', 'max:500'],
        ]);

        $settings = $this->normalizeHeaderSettings($validated['settings'] ?? []);

        ApplicationReportHeader::query()->create([
            'name' => $validated['name'],
            'settings' => $settings,
        ]);

        return redirect()
            ->route('applications.report.headers.index')
            ->with('status', 'Шапка сохранена.');
    }

    public function edit(ApplicationReportHeader $header): View
    {
        $settings = $header->mergedSettings();
        $fontOptions = ReportFontChoices::options();
        [$approverRoles, $approversByRole] = $this->approverMeta();

        return view('applications.report.headers.edit', compact('header', 'settings', 'fontOptions', 'approverRoles', 'approversByRole'));
    }

    public function update(Request $request, ApplicationReportHeader $header): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'settings' => ['nullable', 'array'],
            'settings.font_family' => ['required', Rule::in(ReportFontChoices::values())],
            'settings.title_font_family' => ['nullable', 'string', 'max:120'],
            'settings.org_align' => ['required', Rule::in(['left', 'center', 'right'])],
            'settings.approval_align' => ['required', Rule::in(['left', 'center', 'right'])],
            'settings.title_align' => ['required', Rule::in(['left', 'center', 'right'])],
            'settings.org_name' => ['nullable', 'string', 'max:2000'],
            'settings.org_caption' => ['nullable', 'string', 'max:500'],
            'settings.approval_label' => ['nullable', 'string', 'max:200'],
            'settings.approval_position' => ['nullable', 'string', 'max:500'],
            'settings.approval_name' => ['nullable', 'string', 'max:500'],
            'settings.approval_position_caption' => ['nullable', 'string', 'max:500'],
            'settings.approval_name_caption' => ['nullable', 'string', 'max:500'],
            'settings.title' => ['nullable', 'string', 'max:2000'],
            'settings.title_font_pt' => ['nullable', 'integer', 'min:8', 'max:36'],
            'settings.date_text' => ['nullable', 'date_format:Y-m-d'],
            'settings.city_text' => ['nullable', 'string', 'max:500'],
        ]);

        $header->update([
            'name' => $validated['name'],
            'settings' => $this->normalizeHeaderSettings($validated['settings'] ?? []),
        ]);

        return redirect()
            ->route('applications.report.headers.index')
            ->with('status', 'Шапка обновлена.');
    }

    public function destroy(ApplicationReportHeader $header): RedirectResponse
    {
        $header->delete();

        return redirect()
            ->route('applications.report.headers.index')
            ->with('status', 'Шапка удалена.');
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private function normalizeHeaderSettings(array $input): array
    {
        $merged = array_replace_recursive(ApplicationReportHeader::defaultSettings(), $input);
        $merged['title_font_pt'] = max(8, min(36, (int) ($merged['title_font_pt'] ?? 14)));
        $titleFf = trim((string) ($merged['title_font_family'] ?? ''));
        if ($titleFf !== '' && ! in_array($titleFf, ReportFontChoices::values(), true)) {
            $titleFf = '';
        }
        $merged['title_font_family'] = $titleFf;

        return $merged;
    }

    /**
     * @return array{0:\Illuminate\Support\Collection<int,\App\Models\Role>,1:array<int,list<array{id:int,fio:string}>>}
     */
    private function approverMeta(): array
    {
        $approverRoles = Role::query()
            ->where('id', '!=', 3)
            ->orderBy('name')
            ->get(['id', 'name']);

        $users = User::query()
            ->where('role_id', '!=', 3)
            ->orderBy('surname')
            ->orderBy('name')
            ->get(['id', 'surname', 'name', 'patronymic', 'role_id']);

        $approversByRole = [];
        foreach ($users as $user) {
            $approversByRole[(int) $user->role_id][] = [
                'id' => (int) $user->id,
                'fio' => trim($user->surname.' '.$user->name.' '.$user->patronymic),
            ];
        }

        return [$approverRoles, $approversByRole];
    }
}
