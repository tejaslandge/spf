<?php

namespace App\Enums;

enum ApplicationStatus: string
{
    case Submitted = 'submitted';
    case UnderReview = 'under_review';
    case OfferGenerated = 'offer_generated';
    case Accepted = 'accepted';
    case Rejected = 'rejected';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Submitted => 'Submitted',
            self::UnderReview => 'Under review',
            self::OfferGenerated => 'Offer generated',
            self::Accepted => 'Accepted',
            self::Rejected => 'Rejected',
            self::Cancelled => 'Cancelled',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Submitted => 'amber',
            self::UnderReview => 'blue',
            self::OfferGenerated => 'purple',
            self::Accepted => 'emerald',
            self::Rejected => 'rose',
            self::Cancelled => 'zinc',
        };
    }

    /**
     * Statuses in which the loan proceeds through funding and disbursement.
     */
    public function allowsProcessing(): bool
    {
        return $this === self::Accepted;
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $status) => [$status->value => $status->label()])->all();
    }
}
