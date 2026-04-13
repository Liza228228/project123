<?php

namespace App\Http\Controllers;

use App\Models\Application;
use App\Models\ApplicationReportFooter;
use App\Models\ApplicationReportHeader;
use App\Models\ApplicationReportTemplate;
use App\Support\ReportFontChoices;
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
        $fontOptions = ReportFontChoices::options();

        $applications = Application::listingQuery($request)
            ->with(['subdivision', 'responsibleUser', 'items.equipment', 'user', 'approvedBy', 'transportOption', 'applicationStatus'])
            ->orderByDesc('created_at')
            ->get();

        return view('applications.report.index', compact(
            'template',
            'headers',
            'footers',
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
            'main_font_family' => ['required', Rule::in(ReportFontChoices::values())],
            'table_font_family' => ['required', Rule::in(ReportFontChoices::values())],
        ]);

        $row = ApplicationReportTemplate::current();
        $row->update([
            'report_header_id' => $validated['report_header_id'] ?? null,
            'report_footer_id' => $validated['report_footer_id'] ?? null,
            'main_body_text' => $validated['main_body_text'] ?? null,
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
        $request->merge([
            'report_header_id' => $request->filled('report_header_id') ? (int) $request->input('report_header_id') : null,
            'report_footer_id' => $request->filled('report_footer_id') ? (int) $request->input('report_footer_id') : null,
        ]);

        $validated = $request->validate([
            'report_header_id' => ['nullable', 'integer', 'exists:application_report_headers,id'],
            'report_footer_id' => ['nullable', 'integer', 'exists:application_report_footers,id'],
            'main_body_text' => ['nullable', 'string', 'max:60000'],
            'main_font_family' => ['required', Rule::in(ReportFontChoices::values())],
            'table_font_family' => ['required', Rule::in(ReportFontChoices::values())],
            'application_ids' => ['required', 'array', 'min:1'],
            'application_ids.*' => ['integer', 'exists:applications,id'],
        ]);

        $applications = Application::query()
            ->whereIn('id', $validated['application_ids'])
            ->with(['subdivision', 'responsibleUser', 'items.equipment', 'user', 'approvedBy', 'transportOption', 'applicationStatus'])
            ->orderByDesc('created_at')
            ->get();

        $header = isset($validated['report_header_id'])
            ? ApplicationReportHeader::query()->find($validated['report_header_id'])
            : null;
        $footer = isset($validated['report_footer_id'])
            ? ApplicationReportFooter::query()->find($validated['report_footer_id'])
            : null;

        $headerSettings = $header?->mergedSettings();
        $footerSettings = $footer?->mergedSettings();

        $tableRows = $this->buildActTableRows($applications);
        $blankTailRows = 5;

        return view('applications.report.preview', [
            'headerSettings' => $headerSettings,
            'footerSettings' => $footerSettings,
            'mainBodyText' => $validated['main_body_text'] ?? '',
            'mainFont' => $validated['main_font_family'],
            'tableFont' => $validated['table_font_family'],
            'applications' => $applications,
            'tableRows' => $tableRows,
            'blankTailRows' => $blankTailRows,
        ]);
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
}
