<?php

namespace App\Models;

use App\Enums\KycDocumentType;
use App\Enums\KycStatus;
use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

#[Fillable([
    'type',
    'number',
    'file_path',
    'original_name',
    'mime_type',
    'size',
    'status',
    'verified_by',
    'verified_at',
    'notes',
])]
class KycDocument extends Model
{
    use Auditable;

    public const MANDATORY_BORROWER_TYPES = ['aadhaar', 'pan', 'electricity_bill'];

    public const MANDATORY_INVESTOR_TYPES = ['aadhaar', 'pan'];

    public function isImage(): bool
    {
        return filled($this->mime_type) && str_starts_with((string) $this->mime_type, 'image/');
    }

    public function isPdf(): bool
    {
        return $this->mime_type === 'application/pdf';
    }

    public function maskedNumber(?string $value = null): string
    {
        $number = (string) ($value ?? $this->number ?? '');

        if ($number === '') {
            return '—';
        }

        if (strlen($number) <= 4) {
            return str_repeat('*', strlen($number));
        }

        return 'XXXX-XXXX-'.substr($number, -4);
    }

    /** @return string[] */
    public static function mandatoryTypesFor(string $subjectType): array
    {
        return match ($subjectType) {
            Borrower::class => self::MANDATORY_BORROWER_TYPES,
            Investor::class => self::MANDATORY_INVESTOR_TYPES,
            default => [],
        };
    }

    /** @return array{pending: int, verified: int, rejected: int, total: int} */
    public static function docSummaryFor(string $subjectType, int $subjectId): array
    {
        $counts = static::query()
            ->where('documentable_type', $subjectType)
            ->where('documentable_id', $subjectId)
            ->selectRaw('status, COUNT(*) as cnt')
            ->groupBy('status')
            ->pluck('cnt', 'status');

        $pending = (int) ($counts[KycStatus::Pending->value] ?? 0);
        $verified = (int) ($counts[KycStatus::Verified->value] ?? 0);
        $rejected = (int) ($counts[KycStatus::Rejected->value] ?? 0);

        return [
            'pending' => $pending,
            'verified' => $verified,
            'rejected' => $rejected,
            'total' => $pending + $verified + $rejected,
        ];
    }

    public static function overallStatusFor(string $subjectType, int $subjectId): KycStatus
    {
        $summary = self::docSummaryFor($subjectType, $subjectId);
        $mandatory = self::mandatoryTypesFor($subjectType);

        if ($mandatory !== []) {
            $byType = static::query()
                ->where('documentable_type', $subjectType)
                ->where('documentable_id', $subjectId)
                ->whereIn('type', $mandatory)
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->get()
                ->groupBy(fn (self $doc): string => $doc->type->value);

            foreach ($mandatory as $type) {
                $latest = $byType->get($type)?->first();

                if (! $latest) {
                    return KycStatus::Pending;
                }

                if ($latest->status === KycStatus::Rejected) {
                    return KycStatus::Rejected;
                }

                if ($latest->status !== KycStatus::Verified) {
                    return KycStatus::Pending;
                }
            }

            return KycStatus::Verified;
        }

        if ($summary['rejected'] > 0) {
            return KycStatus::Rejected;
        }

        if ($summary['total'] > 0 && $summary['pending'] === 0) {
            return KycStatus::Verified;
        }

        return KycStatus::Pending;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => KycDocumentType::class,
            'status' => KycStatus::class,
            'verified_at' => 'datetime',
        ];
    }

    public function documentable(): MorphTo
    {
        return $this->morphTo();
    }

    public function verifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function verificationHistories(): HasMany
    {
        return $this->hasMany(KycVerificationHistory::class)->latest();
    }
}
