{{-- Payment Successful Email Template --}}

{{ $htmlHeader ?? '' }}

<!-- Payment Successful Content -->
<div class="u-section">
    <div class="u-row">
        <div class="u-column u-xs-width u-width-lg-100">
            <h2 class="u-heading-2">Payment Successful</h2>

            <p class="u-text">
                Hello {{ $user->name }},
            </p>

            <p class="u-text">
                Your payment has been processed successfully.
            </p>

            <table class="u-table">
                <thead>
                    <tr>
                        <th class="u-table-cell">Details</th>
                        <th class="u-table-cell">Amount</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td class="u-table-cell">Transaction ID</td>
                        <td class="u-table-cell">{{ $transactionId }}</td>
                    </tr>
                    <tr>
                        <td class="u-table-cell">Amount Paid</td>
                        <td class="u-table-cell">${{ $amount }}</td>
                    </tr>
                    <tr>
                        <td class="u-table-cell">Date</td>
                        <td class="u-table-cell">{{ $paymentDate }}</td>
                    </tr>
                </tbody>
            </table>

            <p class="u-text">
                Thank you for your payment. A receipt has been generated for your records.
            </p>

            <a href="{{ route('dashboard') }}" class="u-btn">View Receipt</a>

            <p class="u-text-muted">
                If you have any questions about this payment, please contact support at <a href="mailto:{{ config('mail.from.address') }}">{{ config('mail.from.address') }}</a>.
            </p>
        </div>
    </div>
</div>