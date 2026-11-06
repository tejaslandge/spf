{{-- Welcome Email Template --}}

{{ $htmlHeader ?? '' }}

<!-- Welcome Content -->
<div class="u-section">
    <div class="u-row">
        <div class="u-column u-xs-width u-width-lg-100">
            <h2 class="u-heading-1">Welcome {{ $user->name }}</h2>

            <p class="u-text">
                Thank you for registering with {{ $appName }}. We're excited to have you join our community!
            </p>

            <p class="u-text">
                Your account has been successfully created. You can now log in using your credentials and start exploring our platform.
            </p>

            <p class="u-text">
                <strong>What's next?</strong>
            </p>
            <ul class="u-text">
                <li>Complete your profile information</li>
                <li>Set up your security preferences</li>
                <li>Explore the available features</li>
            </ul>

            {{#if $supportEmail}}
            <p class="u-text">
                If you have any questions, please contact our support team at <a href="mailto:{{ $supportEmail }}">{{ $supportEmail }}</a>.
            </p>
            {{/if}}

            <a href="{{ route('login') }}" class="u-btn">Log In</a>

            <p class="u-text-muted">
                Best regards,<br/>
                {{ $appName }} Team
            </p>
        </div>
    </div>
</div>