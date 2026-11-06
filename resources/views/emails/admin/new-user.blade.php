{{-- Admin New User Notification Template --}}

{{ $htmlHeader ?? '' }}

<!-- Admin New User Content -->
<div class="u-section">
    <div class="u-row">
        <div class="u-column u-xs-width u-width-lg-100">
            <h2 class="u-heading-2">New User Registration</h2>

            <p class="u-text">
                A new user has registered in the system.
            </p>

            <table class="u-table">
                <thead>
                    <tr>
                        <th class="u-table-cell">User Details</th>
                        <th class="u-table-cell">Information</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td class="u-table-cell"><strong>Name:</strong></td>
                        <td class="u-table-cell">{{ $user->name }}</td>
                    </tr>
                    <tr>
                        <td class="u-table-cell"><strong>Email:</strong></td>
                        <td class="u-table-cell">{{ $user->email }}</td>
                    </tr>
                    <tr>
                        <td class="u-table-cell"><strong>Role:</strong></td>
                        <td class="u-table-cell">{{ $user->role->label() }}</td>
                    </tr>
                    @if ($user->designation)
                    <tr>
                        <td class="u-table-cell"><strong>Designation:</strong></td>
                        <td class="u-table-cell">{{ $user->designation->label() }}</td>
                    </tr>
                    @endif
                    <tr>
                        <td class="u-table-cell"><strong>Status:</strong></td>
                        <td class="u-table-cell">{{ ucfirst($user->status) }}</td>
                    </tr>
                </tbody>
            </table>

            <p class="u-text">
                <strong>Action Required:</strong> Review the new user registration and approve if necessary.
            </p>

            <a href="{{ route('super-admin.users.index') }}" class="u-btn">View Users</a>

            <p class="u-text-muted">
                This is an automated admin notification from {{ $appName }}.
            </p>
        </div>
    </div>
</div>