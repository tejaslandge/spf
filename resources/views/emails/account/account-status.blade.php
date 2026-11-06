{{-- Account Status Email Template --}}

{{ $htmlHeader ?? '' }}

<!-- Account Status Content -->
<div class="u-section">
    <div class="u-row">
        <div class="u-column u-xs-width u-width-lg-100">
            <h2 class="u-heading-2">Account Status Update</h2>

            <p class="u-text">
                Hello {{ $user->name }},
            </p>

            <p class="u-text">
                Your account status has been updated from <strong>{{ $oldStatus }}</strong> to <strong>{{ $statusDisplay }}</strong>.
            </p>

            {{ $reason ? '<p class="u-text"><strong>Reason:</strong> '.$reason.'</p>' : '' }}

            <p class="u-text">
                If you believe this change was made in error, please contact support immediately at <a href="mailto:{{ config('mail.from.address') }}">{{ config('mail.from.address') }}</a>.
            </p>

            <a href="{{ route('dashboard') }}" class="u-btn">View Account</a>

            <p class="u-text-muted">
                Best regards,<br/>
                {{ $appName }} Team
            </p>
        </div>
    </div>
</div>