{{-- Login Alert Email Template --}}

{{ $htmlHeader ?? '' }}

<!-- Login Alert Content -->
<div class="u-section">
    <div class="u-row">
        <div class="u-column u-xs-width u-width-lg-100">
            <h2 class="u-heading-2">{{ $wasNewDevice ? 'New Device Login' : 'Login Successful' }}</h2>

            <p class="u-text">
                Hello {{ $user->name }},<br/>
                We wanted to confirm your recent login activity.
            </p>

            {{ $deviceInfo ? '<p class="u-text"><strong>Device Information:</strong> '.$deviceInfo.'</p>' : '' }}

            <p class="u-text">
                If you recognize this login activity, no further action is needed. Your account remains secure.
            </p>

            <p class="u-text">
                <strong>If you did not initiate this login:</strong>
            </p>
            <ul class="u-text">
                <li>Immediately change your password</li>
                <li>Review your account security settings</li>
                <li>Contact support at <a href="mailto:{{ config('mail.from.address') }}">{{ config('mail.from.address') }}</a></li>
            </ul>

            <a href="{{ route('dashboard') }}" class="u-btn">View Account</a>

            <p class="u-text-muted">
                This is an automated notification from {{ $appName }}. Please do not reply to this email.
            </p>
        </div>
    </div>
</div>