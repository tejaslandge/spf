<?php

namespace App\Http\Controllers;

use App\Enums\KycStatus;
use App\Http\Requests\ApproveVerificationRequest;
use App\Http\Requests\RejectVerificationRequest;
use App\Http\Requests\UpdateKycDocumentStatusRequest;
use App\Models\Borrower;
use App\Models\Investor;
use App\Models\KycDocument;
use App\Models\KycVerificationHistory;
use App\Services\KycVerificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class VerificationController extends Controller
{
    public function borrowers(Request $request): JsonResponse
    {
        $query = Borrower::query()->withCount('kycDocuments');
        $status = $request->query('status');

        if (in_array($status, ['pending', 'verified', 'rejected'], true)) {
            $query->whereHas('kycDocuments', function ($q) use ($status) {
                $q->where('status', $status);
            });
        }

        if ($request->filled('search')) {
            $query->search((string) $request->query('search'));
        }

        return response()->json($query->latest()->paginate(20));
    }

    public function investors(Request $request): JsonResponse
    {
        $query = Investor::query()->withCount('kycDocuments');
        $status = $request->query('status');

        if (in_array($status, ['pending', 'verified', 'rejected'], true)) {
            $query->whereHas('kycDocuments', function ($q) use ($status) {
                $q->where('status', $status);
            });
        }

        if ($request->filled('search')) {
            $query->search((string) $request->query('search'));
        }

        return response()->json($query->latest()->paginate(20));
    }

    public function subjectDocuments(string $type, int $id): JsonResponse
    {
        $subjectType = $type === 'borrower' ? Borrower::class : ($type === 'investor' ? Investor::class : null);
        abort_if($subjectType === null, 404);

        $docs = KycDocument::query()
            ->where('documentable_type', $subjectType)
            ->where('documentable_id', $id)
            ->with('verifiedBy')
            ->latest()
            ->get();

        return response()->json([
            'documents' => $docs,
            'overall' => KycDocument::overallStatusFor($subjectType, $id)->value,
        ]);
    }

    public function preview(KycDocument $document): StreamedResponse|Response
    {
        abort_unless(Storage::disk('local')->exists($document->file_path), 404);

        $response = Storage::disk('local')->response($document->file_path, $document->original_name, [
            'Content-Disposition' => $document->isPdf() ? 'inline' : 'attachment',
        ]);

        return $response;
    }

    public function updateDocument(UpdateKycDocumentStatusRequest $request, KycDocument $document, KycVerificationService $service): JsonResponse
    {
        $updated = $service->setDocumentStatus(
            $document,
            KycStatus::from((string) $request->validated('status')),
            $request->validated('rejection_reason') ?? null,
            $request->user(),
        );

        return response()->json(['document' => $updated]);
    }

    public function approve(ApproveVerificationRequest $request, string $type, int $id, KycVerificationService $service): JsonResponse
    {
        $subjectType = $type === 'borrower' ? Borrower::class : Investor::class;

        try {
            $result = $service->approveSubject($subjectType, $id, $request->user(), $request->validated('notes') ?? null);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json($result);
    }

    public function reject(RejectVerificationRequest $request, string $type, int $id, KycVerificationService $service): JsonResponse
    {
        $subjectType = $type === 'borrower' ? Borrower::class : Investor::class;
        $service->rejectSubject($subjectType, $id, (string) $request->validated('rejection_reason'), $request->user());

        return response()->json(['message' => 'Verification rejected.']);
    }

    public function history(string $type, int $id): JsonResponse
    {
        $subjectType = $type === 'borrower' ? Borrower::class : Investor::class;

        return response()->json([
            'history' => KycVerificationHistory::query()
                ->where('subject_type', $subjectType)
                ->where('subject_id', $id)
                ->with(['document', 'actedBy'])
                ->latest()
                ->paginate(20),
        ]);
    }
}
