<?php

namespace App\Http\Controllers;

use App\Models\Application;
use App\Models\ApplicationReportFooter;
use App\Models\ApplicationReportHeader;
use App\Models\ApplicationReportTemplate;
use App\Models\User;
use App\Support\ReportFontChoices;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ApplicationReportController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->input('q', ''));
        $equipmentFilter = (string) $request->input('equipment_filter', 'all');
        $allowedFilters = ['all', 'has_approved', 'has_not_approved', 'fully_approved', 'on_approval'];
        if (! in_array($equipmentFilter, $allowedFilters, true)) {
            $equipmentFilter = 'all';
        }

        $template = ApplicationReportTemplate::current();
        $template->load(['reportHeader', 'reportFooter']);

        $headers = ApplicationReportHeader::query()->orderBy('name')->get();
        $footers = ApplicationReportFooter::query()->orderBy('name')->get();
        $directors = User::query()
            ->where('role_id', 1)
            ->orderBy('surname')
            ->orderBy('name')
            ->get();
        $fontOptions = ReportFontChoices::options();

        $applications = Application::listingQuery($request)
            ->with(['subdivision', 'responsibleUser', 'items.equipment', 'user', 'approvedBy', 'transportOption', 'applicationStatus'])
            ->orderByDesc('created_at')
            ->paginate(20)
            ->withQueryString();

        return view('applications.report.index', compact(
            'template',
            'headers',
            'footers',
            'directors',
            'fontOptions',
            'applications',
            'search',
            'equipmentFilter'
        ));
    }

    public function updateLayout(Request $request): RedirectResponse
    {
        $request->merge([
            'report_header_id' => $request->filled('report_header_id') ? (int) $request->input('report_header_id') : null,
            'report_footer_id' => $request->filled('report_footer_id') ? (int) $request->input('report_footer_id') : null,
        ]);

        $validated = $request->validate([
            'report_header_id' => ['nullable', 'integer', 'exists:application_report_headers,id'],
            'report_footer_id' => ['nullable', 'integer', 'exists:application_report_footers,id'],
            'main_body_text' => ['nullable', 'string', 'max:60000'],
            'footer_text' => ['nullable', 'string', 'max:60000'],
            'main_font_family' => ['required', Rule::in(ReportFontChoices::values())],
            'table_font_family' => ['required', Rule::in(ReportFontChoices::values())],
        ]);

        $row = ApplicationReportTemplate::current();
        $row->update([
            'report_header_id' => $validated['report_header_id'] ?? null,
            'report_footer_id' => $validated['report_footer_id'] ?? null,
            'main_body_text' => $validated['main_body_text'] ?? null,
            'footer_text' => $validated['footer_text'] ?? null,
            'main_font_family' => $validated['main_font_family'],
            'table_font_family' => $validated['table_font_family'],
        ]);

        $query = [];
        if (trim((string) $request->input('q', '')) !== '') {
            $query['q'] = $request->input('q');
        }
        $ef = (string) $request->input('equipment_filter', 'all');
        if ($ef !== '' && $ef !== 'all') {
            $query['equipment_filter'] = $ef;
        }

        return redirect()
            ->route('applications.report.index', $query)
            ->with('status', 'Настройки отчёта сохранены.');
    }

    public function preview(Request $request): View
    {
        return view('applications.report.preview', $this->buildPreviewPayload($request) + [
            'showToolbar' => true,
        ]);
    }

    public function pdf(Request $request)
    {
        $payload = $this->buildPreviewPayload($request);
        $payload = $this->forcePdfSafeFonts($payload);
        $pdf = Pdf::loadView('applications.report.preview', $payload + ['showToolbar' => false])
            ->setOption('defaultFont', 'DejaVu Sans')
            ->setPaper('a4');

        return $pdf->download('applications-report-'.now()->format('Ymd_His').'.pdf');
    }

    /**
     * @param  \Illuminate\Support\Collection<int, Application>  $applications
     * @return list<array{n:int,name:string,unit:string,qty:string,note:string}>
     */
    private function buildActTableRows($applications): array
    {
        $rows = [];
        $n = 1;
        foreach ($applications as $application) {
            if ($application->items->isEmpty()) {
                $rows[] = [
                    'n' => $n++,
                    'name' => '— (в заявке нет позиций)',
                    'unit' => '—',
                    'qty' => '—',
                    'note' => 'Заявка №'.$application->id,
                ];

                continue;
            }
            $sub = $application->subdivision->name ?? '—';
            foreach ($application->items as $item) {
                $rr = trim((string) ($item->reason_not_selected ?? ''));
                if ($item->is_checked) {
                    $st = 'одобрено';
                } elseif ($rr !== '') {
                    $st = 'не согласовано: '.$rr;
                } else {
                    $st = 'на согласовании';
                }
                $rows[] = [
                    'n' => $n++,
                    'name' => $item->equipment_display_name,
                    'unit' => 'шт.',
                    'qty' => (string) $item->quantity,
                    'note' => 'Заявка №'.$application->id.', '.$sub.' — '.$st,
                ];
            }
        }

        return $rows;
    }

    /**
     * @return array<string, mixed>
     */
    private function buildPreviewPayload(Request $request): array
    {
        $request->merge([
            'report_header_id' => $request->filled('report_header_id') ? (int) $request->input('report_header_id') : null,
            'report_footer_id' => $request->filled('report_footer_id') ? (int) $request->input('report_footer_id') : null,
        ]);

        $validated = $request->validate([
            'report_header_id' => ['nullable', 'integer', 'exists:application_report_headers,id'],
            'report_footer_id' => ['nullable', 'integer', 'exists:application_report_footers,id'],
            'director_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'main_body_text' => ['nullable', 'string', 'max:60000'],
            'footer_text' => ['nullable', 'string', 'max:60000'],
            'include_applications_table' => ['nullable', 'boolean'],
            'main_font_family' => ['required', Rule::in(ReportFontChoices::values())],
            'table_font_family' => ['required', Rule::in(ReportFontChoices::values())],
        ]);

        $includeApplicationsTable = (bool) ($validated['include_applications_table'] ?? false);
        $applications = collect();

        if ($includeApplicationsTable) {
            $appValidated = $request->validate([
                'application_ids' => ['required', 'array', 'min:1'],
                'application_ids.*' => ['integer', 'exists:applications,id'],
            ]);

            $applications = Application::query()
                ->whereIn('id', $appValidated['application_ids'])
                ->with(['subdivision', 'responsibleUser', 'items.equipment', 'user', 'approvedBy', 'transportOption', 'applicationStatus'])
                ->orderByDesc('created_at')
                ->get();
        }

        $header = isset($validated['report_header_id'])
            ? ApplicationReportHeader::query()->find($validated['report_header_id'])
            : null;
        $footer = isset($validated['report_footer_id'])
            ? ApplicationReportFooter::query()->find($validated['report_footer_id'])
            : null;
        $director = isset($validated['director_user_id'])
            ? User::query()->find($validated['director_user_id'])
            : null;
        $directorFio = $director ? $this->formatUserFio($director) : '';

        $headerSettings = $header?->mergedSettings();
        if (is_array($headerSettings)) {
            if ($directorFio !== '') {
                $headerSettings['approval_name'] = $directorFio;
            }
            $headerSettings = $this->replaceDirectorPlaceholderInArray($headerSettings, $directorFio);
        }

        $footerSettings = $footer?->mergedSettings();
        if (is_array($footerSettings)) {
            $footerSettings = $this->replaceDirectorPlaceholderInArray($footerSettings, $directorFio);
        }

        return [
            'headerSettings' => $headerSettings,
            'footerSettings' => $footerSettings,
            'mainBodyText' => str_replace('{{director_fio}}', $directorFio, (string) ($validated['main_body_text'] ?? '')),
            'footerText' => str_replace('{{director_fio}}', $directorFio, (string) ($validated['footer_text'] ?? '')),
            'includeApplicationsTable' => $includeApplicationsTable,
            'directorFio' => $directorFio,
            'mainFont' => $validated['main_font_family'],
            'tableFont' => $validated['table_font_family'],
            'applications' => $applications,
            'tableRows' => $this->buildActTableRows($applications),
            'blankTailRows' => 5,
        ];
    }

    private function formatUserFio(User $user): string
    {
        return trim(implode(' ', array_filter([
            trim((string) $user->surname),
            trim((string) $user->name),
            trim((string) $user->patronymic),
        ], fn ($part) => $part !== '')));
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function replaceDirectorPlaceholderInArray(array $data, string $directorFio): array
    {
        foreach ($data as $key => $value) {
            if (is_string($value)) {
                $data[$key] = str_replace('{{director_fio}}', $directorFio, $value);
                continue;
            }
            if (is_array($value)) {
                $data[$key] = $this->replaceDirectorPlaceholderInArray($value, $directorFio);
            }
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function forcePdfSafeFonts(array $payload): array
    {
        $pdfFont = 'DejaVu Sans, sans-serif';

        $payload['mainFont'] = $pdfFont;
        $payload['tableFont'] = $pdfFont;

        if (is_array($payload['headerSettings'] ?? null)) {
            $payload['headerSettings']['font_family'] = $pdfFont;
            $payload['headerSettings']['title_font_family'] = $pdfFont;
        }

        if (is_array($payload['footerSettings'] ?? null)) {
            $payload['footerSettings']['font_family'] = $pdfFont;
        }

        return $payload;
    }
}
