<?php

namespace App\Http\Controllers;

use App\Models\ApplicationReportFooter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
        $fontSize = 14;

        return view('applications.report.footers.create', compact('fontSize'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'font_size' => ['required', 'integer', 'min:8', 'max:36'],
        ]);

        ApplicationReportFooter::query()->create([
            'name' => $validated['name'],
            'font_size' => (int) $validated['font_size'],
        ]);

        return redirect()
            ->route('applications.report.footers.index')
            ->with('status', 'Подвал сохранён.');
    }

    public function edit(ApplicationReportFooter $footer): View
    {
        $fontSize = max(8, min(36, (int) ($footer->font_size ?? 14)));

        return view('applications.report.footers.edit', compact('footer', 'fontSize'));
    }

    public function update(Request $request, ApplicationReportFooter $footer): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'font_size' => ['required', 'integer', 'min:8', 'max:36'],
        ]);

        $footer->update([
            'name' => $validated['name'],
            'font_size' => (int) $validated['font_size'],
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

}
