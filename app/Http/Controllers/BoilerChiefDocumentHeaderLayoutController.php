<?php

// контроллер
namespace App\Http\Controllers;

use App\Http\Requests\StoreDocumentHeaderLayoutRequest;
use App\Http\Requests\UpdateDocumentHeaderLayoutRequest;
use App\Models\DocumentHeaderLayout;
use App\Models\User;
use App\Support\RequestLayoutDocumentHeaderReturn;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BoilerChiefDocumentHeaderLayoutController extends Controller
{
    public function index(Request $request): View
    {
        $layouts = DocumentHeaderLayout::query()
            ->where('title', 'not like', '%оммерческ%')
            ->orderByDesc('updated_at')
            ->get();

        return view('boiler-chief.document-header-layouts.index', compact('layouts'));
    }

    public function create(Request $request): View
    {
        $returnTo = RequestLayoutDocumentHeaderReturn::fromRequest($request);

        return view('boiler-chief.document-header-layouts.create', [
            'returnTo' => $returnTo,
            'backHref' => $returnTo ?? route('boiler-chief.document-header-layouts.index'),
            'backLabel' => RequestLayoutDocumentHeaderReturn::backLabel($returnTo),
        ]);
    }

    public function store(StoreDocumentHeaderLayoutRequest $request): RedirectResponse
    {
        $payload = $request->payload();
        $layout = DocumentHeaderLayout::query()->create([
            'title' => $payload['title'],
            'schema' => $payload['schema'],
        ]);

        $returnTo = RequestLayoutDocumentHeaderReturn::fromRequest($request);
        if ($returnTo !== null) {
            return redirect()->to($returnTo.'?'.http_build_query([
                'document_header_layout_id' => $layout->id,
            ]))->with('status', 'Макет шапки сохранён. Он выбран в форме макета отчёта.');
        }

        return redirect()
            ->route('boiler-chief.document-header-layouts.index')
            ->with('status', 'Макет шапки сохранён.');
    }

    public function edit(Request $request, DocumentHeaderLayout $documentHeaderLayout): View
    {
        $this->assertReportLayoutDesigner($documentHeaderLayout, $request->user());
        $returnTo = RequestLayoutDocumentHeaderReturn::fromRequest($request);

        return view('boiler-chief.document-header-layouts.edit', [
            'layout' => $documentHeaderLayout,
            'returnTo' => $returnTo,
            'backHref' => $returnTo ?? route('boiler-chief.document-header-layouts.index'),
            'backLabel' => RequestLayoutDocumentHeaderReturn::backLabel($returnTo),
        ]);
    }

    public function update(
        UpdateDocumentHeaderLayoutRequest $request,
        DocumentHeaderLayout $documentHeaderLayout
    ): RedirectResponse {
        $this->assertReportLayoutDesigner($documentHeaderLayout, $request->user());
        $payload = $request->payload();
        $documentHeaderLayout->update([
            'title' => $payload['title'],
            'schema' => $payload['schema'],
        ]);

        $returnTo = RequestLayoutDocumentHeaderReturn::fromRequest($request);
        if ($returnTo !== null) {
            return redirect()->to($returnTo)
                ->with('status', 'Макет шапки обновлён.');
        }

        return redirect()
            ->route('boiler-chief.document-header-layouts.index')
            ->with('status', 'Макет шапки обновлён.');
    }

    public function destroy(Request $request, DocumentHeaderLayout $documentHeaderLayout): RedirectResponse
    {
        $this->assertReportLayoutDesigner($documentHeaderLayout, $request->user());
        $documentHeaderLayout->delete();

        return redirect()
            ->route('boiler-chief.document-header-layouts.index')
            ->with('status', 'Макет шапки удалён.');
    }

    private function assertReportLayoutDesigner(DocumentHeaderLayout $layout, ?User $user): void
    {
        if (! $user || ! $user->hasAnyRoleId(User::REPORT_LAYOUT_DESIGNER_ROLE_IDS)) {
            abort(403);
        }
    }
}
