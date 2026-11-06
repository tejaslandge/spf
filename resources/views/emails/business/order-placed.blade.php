{{-- Order Placed Email Template --}}

{{ $htmlHeader ?? '' }}

<!-- Order Placed Content -->
<div class="u-section">
    <div class="u-row">
        <div class="u-column u-xs-width u-width-lg-100">
            <h2 class="u-heading-2">Order Placed</h2>

            <p class="u-text">
                Hello {{ $user->name }},
            </p>

            <p class="u-text">
                Thank you for your order! We've received your purchase and are processing it.
            </p>

            <table class="u-table">
                <thead>
                    <tr>
                        <th class="u-table-cell">Item</th>
                        <th class="u-table-cell">Quantity</th>
                        <th class="u-table-cell">Price</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($items as $item)
                    <tr>
                        <td class="u-table-cell">{{ $item['name'] }}</td>
                        <td class="u-table-cell">{{ $item['quantity'] }}</td>
                        <td class="u-table-cell">${{ $item['price'] }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>

            <p class="u-text">
                <strong>Order Total:</strong> ${{ $total }}
            </p>

            <p class="u-text">
                You will receive another email when your order is confirmed and shipped.
            </p>

            <a href="{{ route('dashboard') }}" class="u-btn">View Order Status</a>

            <p class="u-text-muted">
                Thank you for shopping with {{ $appName }}.
            </p>
        </div>
    </div>
</div>