<?php

namespace App\Services;

use App\Enums\KycStatus;
use App\Models\Borrower;
use App\Models\Investor;
use App\Models\KycDocument;
use App\Models\KycVerificationHistory;
use App\Models\User;
use App\Support\AuditLogger;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

final class KycVerificationService
{
    public function setDocumentStatus(KycDocument $doc, KycStatus $status, ?string $reason, User $actor): KycDocument
    {
        if ($status === KycStatus::Rejected && blank($reason)) {
            throw ValidationException::withMessages([
                'rejection_reason' => 'A rejection reason is required.',
            ]);
        }

        return DB::transaction(function () use ($doc, $status, $reason, $actor) {
            $locked = KycDocument::query()->lockForUpdate()->findOrFail($doc->getKey());
            $old = $locked->status;

            $locked->update([
                'status' => $status,
                'verified_by' => $actor->getKey(),
                'verified_at' => now(),
                'notes' => $status === KycStatus::Rejected ? trim((string) $reason) : $locked->notes,
            ]);

            KycVerificationHistory::query()->create([
                'kyc_document_id' => $locked->getKey(),
                'subject_type' => $locked->documentable_type,
                'subject_id' => $locked->documentable_id,
                'action' => $status === KycStatus::Verified ? 'verified' : ($status === KycStatus::Rejected ? 'rejected' : 'status_changed'),
                'old_status' => $old->value,
                'new_status' => $status->value,
                'rejection_reason' => $status === KycStatus::Rejected ? trim((string) $reason) : null,
                'acted_by' => $actor->getKey(),
            ]);

            AuditLogger::log('kyc.document_status_changed', $locked, ['status' => $old->value], [
                'status' => $status->value,
                'rejection_reason' => $status === KycStatus::Rejected ? trim((string) $reason) : null,
            ]);

            return $locked->refresh();
        });
    }

    /**
     * @return array{verified: bool, overall: KycStatus, pending: int, rejected: int}
     */
    public function approveSubject(string $subjectType, int $subjectId, User $actor, ?string $notes = null): array
    {
        return DB::transaction(function () use ($subjectType, $subjectId, $actor, $notes) {
            $mandatory = KycDocument::mandatoryTypesFor($subjectType);
            $pending = 0;
            $rejected = 0;

            if ($mandatory !== []) {
                $byType = KycDocument::query()
                    ->where('documentable_type', $subjectType)
                    ->where('documentable_id', $subjectId)
                    ->whereIn('type', $mandatory)
                    ->orderByDesc('created_at')
                    ->orderByDesc('id')
                    ->get()
                    ->groupBy(fn (KycDocument $d): string => $d->type->value);

                foreach ($mandatory as $type) {
                    $latest = $byType->get($type)?->first();

                    if (! $latest) {
                        $pending++;
                    } elseif ($latest->status === KycStatus::Pending) {
                        $pending++;
                    } elseif ($latest->status === KycStatus::Rejected) {
                        $rejected++;
                    }
                }
            } else {
                $counts = KycDocument::query()
                    ->where('documentable_type', $subjectType)
                    ->where('documentable_id', $subjectId)
                    ->selectRaw('status, COUNT(*) as cnt')
                    ->groupBy('status')
                    ->pluck('cnt', 'status');

                $pending = (int) ($counts[KycStatus::Pending->value] ?? 0);
                $rejected = (int) ($counts[KycStatus::Rejected->value] ?? 0);
            }

            $overall = KycDocument::overallStatusFor($subjectType, $subjectId);

            if ($pending > 0 || $rejected > 0) {
                throw new InvalidArgumentException('All mandatory documents must be verified before approval. Pending: '.$pending.', Rejected: '.$rejected.'.');
            }

            $subject = $this->resolveSubject($subjectType, $subjectId);

            if ($subject !== null) {
                KycVerificationHistory::query()->create([
                    'kyc_document_id' => null,
                    'subject_type' => $subjectType,
                    'subject_id' => $subjectId,
                    'action' => 'approved',
                    'old_status' => $overall->value,
                    'new_status' => KycStatus::Verified->value,
                    'acted_by' => $actor->getKey(),
                    'notes' => $notes,
                ]);

                AuditLogger::log('kyc.approved', $subject, ['kyc_status' => $overall->value], [
                    'kyc_status' => KycStatus::Verified->value,
                ]);
            }

            return ['verified' => true, 'overall' => KycStatus::Verified, 'pending' => 0, 'rejected' => 0];
        });
    }

    public function rejectSubject(string $subjectType, int $subjectId, string $reason, User $actor): void
    {
        if (blank($reason)) {
            throw ValidationException::withMessages(['rejection_reason' => 'A rejection reason is required.']);
        }

        DB::transaction(function () use ($subjectType, $subjectId, $reason, $actor) {
            $overall = KycDocument::overallStatusFor($subjectType, $subjectId);
            $subject = $this->resolveSubject($subjectType, $subjectId);

            KycVerificationHistory::query()->create([
                'kyc_document_id' => null,
                'subject_type' => $subjectType,
                'subject_id' => $subjectId,
                'action' => 'rejected',
                'old_status' => $overall->value,
                'new_status' => KycStatus::Rejected->value,
                'rejection_reason' => trim($reason),
                'acted_by' => $actor->getKey(),
            ]);

            if ($subject !== null) {
                AuditLogger::log('kyc.rejected', $subject, ['kyc_status' => $overall->value], [
                    'kyc_status' => KycStatus::Rejected->value,
                    'rejection_reason' => trim($reason),
                ]);
            }
        });
    }

    private function resolveSubject(string $type, int $id): ?Model
    {
        return match ($type) {
            Borrower::class => Borrower::query()->find($id),
            Investor::class => Investor::query()->find($id),
            default => null,
        };
    }
}
