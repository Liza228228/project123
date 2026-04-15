<?php

namespace App\Http\Controllers;

use App\Models\ApplicationReportHeader;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
        $fontSize = 14;

        return view('applications.report.headers.create', compact('fontSize'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'font_size' => ['required', 'integer', 'min:8', 'max:36'],
        ]);

        ApplicationReportHeader::query()->create([
            'name' => $validated['name'],
            'font_size' => (int) $validated['font_size'],
        ]);

        return redirect()
            ->route('applications.report.headers.index')
            ->with('status', 'Шапка сохранена.');
    }

    public function edit(ApplicationReportHeader $header): View
    {
        $fontSize = max(8, min(36, (int) ($header->font_size ?? 14)));

        return view('applications.report.headers.edit', compact('header', 'fontSize'));
    }

    public function update(Request $request, ApplicationReportHeader $header): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'font_size' => ['required', 'integer', 'min:8', 'max:36'],
        ]);

        $header->update([
            'name' => $validated['name'],
            'font_size' => (int) $validated['font_size'],
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

}
