<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreDocumentHeaderLayoutRequest;
use App\Http\Requests\UpdateDocumentHeaderLayoutRequest;
use App\Models\DocumentHeaderLayout;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BoilerChiefDocumentHeaderLayoutController extends Controller
{
    public function index(Request $request): View
    {
        $layouts = DocumentHeaderLayout::query()
            ->orderByDesc('updated_at')
            ->get();

        return view('boiler-chief.document-header-layouts.index', compact('layouts'));
    }

    public function create(Request $request): View
    {
        return view('boiler-chief.document-header-layouts.create');
    }

    public function store(StoreDocumentHeaderLayoutRequest $request): RedirectResponse
    {
        $payload = $request->payload();
        DocumentHeaderLayout::query()->create([
            'title' => $payload['title'],
            'schema' => $payload['schema'],
        ]);

        return redirect()
            ->route('boiler-chief.document-header-layouts.index')
            ->with('status', 'Макет шапки сохранён.');
    }

    public function edit(Request $request, DocumentHeaderLayout $documentHeaderLayout): View
    {
        $this->assertOwner($documentHeaderLayout, $request->user());

        return view('boiler-chief.document-header-layouts.edit', [
            'layout' => $documentHeaderLayout,
        ]);
    }

    public function update(
        UpdateDocumentHeaderLayoutRequest $request,
        DocumentHeaderLayout $documentHeaderLayout
    ): RedirectResponse {
        $this->assertOwner($documentHeaderLayout, $request->user());
        $payload = $request->payload();
        $documentHeaderLayout->update([
            'title' => $payload['title'],
            'schema' => $payload['schema'],
        ]);

        return redirect()
            ->route('boiler-chief.document-header-layouts.index')
            ->with('status', 'Макет шапки обновлён.');
    }

    public function destroy(Request $request, DocumentHeaderLayout $documentHeaderLayout): RedirectResponse
    {
        $this->assertOwner($documentHeaderLayout, $request->user());
        $documentHeaderLayout->delete();

        return redirect()
            ->route('boiler-chief.document-header-layouts.index')
            ->with('status', 'Макет шапки удалён.');
    }

    private function assertOwner(DocumentHeaderLayout $layout, ?User $user): void
    {
        if (! $user || ! $user->hasRoleId(7)) {
            abort(403);
        }
    }
}
