{{-- KYC Verified Email Template --}}

{{ $htmlHeader ?? '' }}

<!-- KYC Verified Content -->
<div class="u-section">
    <div class="u-row">
        <div class="u-column u-xs-width u-width-lg-100">
            <h2 class="u-heading-2">KYC Verification Complete</h2>

            <p class="u-text">
                Hello {{ $user->name }},
            </p>

            <p class="u-text">
                Your KYC (Know Your Customer) verification has been completed successfully.
            </p>

            <table class="u-table">
                <thead>
                    <tr>
                        <th class="u-table-cell">Document</th>
                        <th class="u-table-cell">Status</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($documents as $doc)
                    <tr>
                        <td class="u-table-cell">{{ $doc['type'] }}</td>
                        <td class="u-table-cell">{{ ucfirst($doc['status']) }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>

            <p class="u-text">
                You can now enjoy full access to all platform features.
            </p>

            <a href="{{ route('dashboard') }}" class="u-btn">Continue</a>

            <p class="u-text-muted">
                {{ $appName }} Team
            </p>
        </div>
    </div>
</div>