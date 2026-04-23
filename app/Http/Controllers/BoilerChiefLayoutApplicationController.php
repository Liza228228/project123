<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreLayoutApplicationRequest;
use App\Models\Application;
use App\Models\RequestLayout;
use App\Models\RequestSubmission;
use App\Models\User;
use App\Support\RequestLayoutDocumentBuilder;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class BoilerChiefLayoutApplicationController extends Controller
{
    public function index(Request $request): View
    {
        $userId = (int) $request->user()->id;
        $submissions = RequestSubmission::query()
            ->whereHas('requestLayout', fn ($q) => $q->where('user_assigner_id', $userId))
            ->with(['requestLayout', 'creator'])
            ->orderByDesc('id')
            ->paginate(20);

        return view('boiler-chief.layout-applications.index', [
            'submissions' => $submissions,
        ]);
    }

    public function create(Request $request): View
    {
        $layouts = RequestLayout::query()
            ->where('user_assigner_id', $request->user()->id)
            ->orderBy('title')
            ->get();

        $users = User::query()
            ->with('role')
            ->orderBy('surname')
            ->orderBy('name')
            ->limit(500)
            ->get();
        $applications = Application::query()
            ->with(['subdivision:id,name', 'items'])
            ->orderByDesc('id')
            ->limit(300)
            ->get();

        return view('boiler-chief.layout-applications.create', [
            'layouts' => $layouts,
            'users' => $users,
            'applications' => $applications,
        ]);
    }

    public function store(
        StoreLayoutApplicationRequest $request,
        RequestLayoutDocumentBuilder $builder
    ): SymfonyResponse {
        $layout = $request->layout();
        $values = $request->fieldValues($layout);

        foreach ([1, 2, 3] as $i) {
            $k = 'signer_'.$i.'_user_id';
            if ($request->filled($k)) {
                $values[$k] = (int) $request->input($k);
            }
        }

        if ($request->boolean('use_current_date')) {
            $values['_document_date'] = now()->format('d.m.Y');
        } elseif ($request->filled('form_document_date')) {
            $values['_document_date'] = $request->date('form_document_date')->format('d.m.Y');
        }

        if ($request->filled('recipient_user_id')) {
            $ru = User::query()->find((int) $request->input('recipient_user_id'));
            if ($ru instanceof User) {
                $values['recipient_name'] = $ru->fullName();
            }
        }

        $registryNumber = RequestSubmission::allocateRegistryNumber();
        RequestSubmission::query()->create([
            'registry_number' => $registryNumber,
            'data' => $values,
            'created_by' => $request->user()->id,
            'request_layout_id' => $layout->id,
            'recipient_user_id' => $request->filled('recipient_user_id')
                ? (int) $request->input('recipient_user_id')
                : null,
        ]);

        return $this->streamPdfResponse($layout, $values, $registryNumber, $builder);
    }

    public function pdf(
        Request $request,
        RequestSubmission $submission,
        RequestLayoutDocumentBuilder $builder
    ): SymfonyResponse {
        $this->authorizeSubmission($submission, $request->user());

        $layout = $submission->requestLayout;
        if (! $layout instanceof RequestLayout) {
            abort(404);
        }

        $values = is_array($submission->data) ? $submission->data : [];
        if ($submission->recipient_user_id) {
            $ru = User::query()->find((int) $submission->recipient_user_id);
            if ($ru instanceof User) {
                $values['recipient_name'] = $ru->fullName();
            }
        }

        return $this->streamPdfResponse($layout, $values, $submission->registry_number, $builder);
    }

    public function destroy(Request $request, RequestSubmission $submission): RedirectResponse
    {
        $this->authorizeSubmission($submission, $request->user());
        $submission->delete();

        return redirect()->route('boiler-chief.layout-applications.index')
            ->with('status', 'Заявка по макету удалена.');
    }

    private function streamPdfResponse(
        RequestLayout $layout,
        array $values,
        ?int $registryNumber,
        RequestLayoutDocumentBuilder $builder
    ): SymfonyResponse {
        $layout->load(['approver', 'divisionAssigner', 'documentHeaderLayout']);
        $parts = $builder->pdfParts($layout, $values);
        $structuredHeaderHtml = $parts['structuredHeaderHtml'] ?? null;
        $showHeader = trim($parts['headerText']) !== '' || ($structuredHeaderHtml ?? '') !== '';

        $viewData = [
            'layoutTitle' => $layout->title,
            'documentTitle' => $parts['documentTitle'],
            'showHeading' => trim($parts['headingText']) !== '',
            'showHeader' => $showHeader,
            'showFooter' => trim($parts['footerLeftText']) !== '' || trim($parts['signatureText']) !== '',
            'headingHtml' => $this->pdfPlainToHtml($parts['headingText']),
            'structuredHeaderHtml' => $structuredHeaderHtml,
            'headerHtml' => $structuredHeaderHtml !== null && $structuredHeaderHtml !== ''
                ? $structuredHeaderHtml
                : $this->pdfPlainToHtml($parts['headerText']),
            'bodyHtml' => $builder->bodyHtmlForPdf($parts['bodyText']),
            'footerLeftHtml' => $this->pdfPlainToHtml($parts['footerLeftText']),
            'signatureHtml' => $this->pdfPlainToHtml($parts['signatureText']),
            'pdfHeaderAlign' => $parts['pdfHeaderAlign'],
            'pdfBodyAlign' => $parts['pdfBodyAlign'],
            'pdfFooterLeftAlign' => $parts['pdfFooterLeftAlign'],
            'pdfFooterRightAlign' => $parts['pdfFooterRightAlign'],
            'headerUsesStructuredLayout' => $structuredHeaderHtml !== null && $structuredHeaderHtml !== '',
            'presentationTitleSizePt' => $parts['presentationHeadingSizePt'] ?? 15,
            'presentationSubtitleSizePt' => $parts['presentationSubtitleSizePt'] ?? 12,
        ];

        if ($registryNumber !== null) {
            $viewData['registryNumber'] = $registryNumber;
        }

        $pdf = Pdf::loadView('boiler-chief.request-layouts.pdf', $viewData)->setPaper('a4', 'portrait');

        $fileName = 'zajavka-'.($registryNumber ?? now()->format('YmdHis')).'.pdf';

        return response($pdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$fileName.'"',
        ]);
    }

    private function pdfPlainToHtml(?string $plain): string
    {
        $plain = (string) ($plain ?? '');
        if ($plain === '') {
            return '';
        }

        $withBr = nl2br(e($plain), false);

        return str_replace(["\r\n", "\r", "\n"], '', $withBr);
    }

    private function authorizeSubmission(RequestSubmission $submission, ?User $user): void
    {
        if (! $user) {
            abort(403);
        }
        $submission->loadMissing('requestLayout');
        $layout = $submission->requestLayout;
        if (! $layout) {
            abort(404);
        }
        $isOwner = (int) $layout->user_assigner_id === (int) $user->id;
        $isCreator = (int) $submission->created_by === (int) $user->id;
        if (! $isOwner && ! $isCreator) {
            abort(403);
        }
    }
}
