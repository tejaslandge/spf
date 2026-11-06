{{-- Password Changed Email Template --}}

{{ $htmlHeader ?? '' }}

<!-- Password Changed Content -->
<div class="u-section">
    <div class="u-row">
        <div class="u-column u-xs-width u-width-lg-100">
            <h2 class="u-heading-2">Password Changed</h2>

            <p class="u-text">
                Hello {{ $user->name }},
            </p>

            <p class="u-text">
                Your password has been successfully changed. This action was initiated from: <strong>{{ $initiatedFrom }}</strong>.
            </p>

            <p class="u-text">
                If you did not initiate this password change, please:
            </p>
            <ul class="u-text">
                <li>Immediately reset your password using the "Forgot Password" link</li>
                <li>Review your account security settings</li>
                <li>Contact support at <a href="mailto:{{ config('mail.from.address') }}">{{ config('mail.from.address') }}</a></li>
            </ul>

            <a href="{{ route('login') }}" class="u-btn">Log In</a>

            <p class="u-text-muted">
                If you believe this change was made in error, please secure your account immediately and contact our support team.
            </p>

            <p class="u-text-muted">
                Best regards,<br/>
                {{ $appName }} Security Team
            </p>
        </div>
    </div>
</div>