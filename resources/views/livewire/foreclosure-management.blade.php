<x-layout>
    @push('scripts')
        <script>
            // Initialize flatpickr for date pickers in the admin section
            document.addEventListener('DOMContentLoaded', function() {
                if (flatpickr) {
                    // Flatpickr will be initialized by Livewire on form render
                    document.querySelectorAll('.flatpickr-admin').forEach(el => {
                        flatpickr.parse(el);
                    });
                }
            });
        </script>
    @endpush

    @section('content')
    <div class="px-4 py-6">
        @auth
            @can('manage foreclosure requests')
                <div class="flex flex-col sm:flex-row gap-3 mb-4">
                    @if (auth()->user()->isSuperAdmin())
                        <flux:button wire:click="loadSettings" variant="outline" class="!rounded-xl text-sm">
                            {{ __('Load Settings') }}
                        </flux:button>
                    @endif
                </div>
            @endcan
        @endauth

        <div class="fc-card p-5 sm:p-6">
            <div class="flex flex-col sm:flex-row justify-between items-start gap-4 mb-5">
                <div>
                    <h2 class="text-xl font-semibold tracking-tight">{{ __('Foreclosure Management') }}</h2>
                    <p class="mt-1 text-sm text-slate-500">{{ __('Manage foreclosure requests for all loans') }}</p>
                </div>

                @if ($isSuperAdmin)
                    <flux:button wire:click="loadSettings" variant="outline" class="!rounded-xl text-sm">
                        {{ __('Load Settings') }}
                    </flux:button>
                @endif
            </div>

            <!-- Pending Requests Section -->
            @if (count($pendingRequests) > 0)
                <div class="fc-card p-4 mb-4">
                    <div class="flex justify-between items-center mb-3">
                        <h3 class="text-sm font-semibold uppercase tracking-wider text-navy-900">{{ __('Pending Requests ({$count})', ['count' => count($pendingRequests)]) }}</h3>
                        <a href="#" wire:click="openRequestDetails($pendingRequests->first())" class="text-[11px] font-semibold text-navy-900 hover:underline">{{ __('View all') }}</a>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead class="fc-table-head">
                                <tr>
                                    <th class="px-4 py-3 text-left">{{ __('Loan') }}</th>
                                    <th class="px-4 py-3 text-end">{{ __('Principal') }}</th>
                                    <th class="px-4 py-3">{{ __('Outstanding Principal') }}</th>
                                    <th class="px-4 py-3">{{ __('Accrued Interest') }}</th>
                                    <th class="px-4 py-3">{{ __('Charges') }}</th>
                                    <th class="px-4 py-3">{{ __('Total Settlement') }}</th>
                                    <th class="px-4 py-3">{{ __('Requested Date') }}</th>
                                    <th class="px-4 py-3">{{ __('Status') }}</th>
                                    <th class="px-4 py-3 text-end">{{ __('Actions') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                @foreach ($pendingRequests as $req)
                                    <tr class="transition hover:bg-slate-50/70">
                                        <td class="px-4 py-3">
                                            <a href="{{ route('loans.show', $req->loan) }}" class="font-medium text-blue-600 hover:text-blue-700">{{ $req->loan->loan_code }}</a>
                                            <p class="text-[11px] text-slate-400">{{ $req->loan->borrower->full_name }}</p>
                                        </td>
                                        <td class="whitespace-nowrap px-4 py-3 text-end tabular-nums text-slate-700">{{ \App\Support\Money::format($req->outstanding_principal) }}</td>
                                        <td class="whitespace-nowrap px-4 py-3 text-end font-medium tabular-nums text-rose-600">{{ \App\Support\Money::format($req->outstanding_principal) }}</td>
                                        <td class="whitespace-nowrap px-4 py-3 text-end tabular-nums text-slate-600">{{ \App\Support\Money::format($req->accrued_interest) }}</td>
                                        <td class="whitespace-nowrap px-4 py-3 text-end tabular-nums text-rose-600">{{ \App\Support\Money::format($req->foreclosure_charges) }}</td>
                                        <td class="whitespace-nowrap px-4 py-3 text-end font-medium tabular-nums text-navy-800">{{ \App\Support\Money::format($req->total_settlement) }}</td>
                                        <td class="px-4 py-3 text-slate-500">{{ \Illuminate\Support\Carbon::parse($req->foreclosure_request_date)->format('d M, Y') }}</td>
                                        <td class="px-4 py-3">
                                            <x-status-badge :status="\App\Enums\LoanStatus::Pending" class="text-[10px]" />
                                        </td>
                                        <td class="px-4 py-3 text-end">
                                            <flux:button wire:click="openRequestDetails($req)" variant="outline" size="sm" class="!rounded-xl text-[11px]">Details</flux:button>
                                            @can('approve')
                                                <flux:button wire:click="approve" variant="success" size="sm" class="!rounded-xl text-[11px] ml-1">Approve</flux:button>
                                            @endcan
                                            @can('reject')
                                                <flux:button wire:click="reject" variant="danger" size="sm" class="!rounded-xl text-[11px] ml-1">Reject</flux:button>
                                            @endcan
                                            @can('process payment')
                                                <flux:button wire:click="processPayment" variant="info" size="sm" class="!rounded-xl text-[11px] ml-1">Pay</flux:button>
                                            @endcan
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @else
                <div class="fc-card p-8 text-center text-slate-500">
                    <flux:icon-check-circle class="size-6 mb-3" />
                    <p>{{ __('No pending foreclosure requests.') }}</p>
                </div>
            @endif

            <!-- Approved Requests Section -->
            @if (count($approvedRequests) > 0)
                <div class="fc-card p-4 mb-4">
                    <div class="flex justify-between items-center mb-3">
                        <h3 class="text-sm font-semibold uppercase tracking-wider text-emerald-900">{{ __('Approved Foreclosures ({$count})', ['count' => count($approvedRequests)]) }}</h3>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead class="fc-table-head">
                                <tr>
                                    <th class="px-4 py-3 text-left">{{ __('Loan') }}</th>
                                    <th class="px-4 py-3 text-end">{{ __('Principal') }}</th>
                                    <th class="px-4 py-3">{{ __('Total Settlement') }}</th>
                                    <th class="px-4 py-3">{{ __('Approval Date') }}</th>
                                    <th class="px-4 py-3">{{ __('Payment Date') }}</th>
                                    <th class="px-4 py-3">{{ __('Closure Date') }}</th>
                                    <th class="px-4 py-3">{{ __('Status') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                @foreach ($approvedRequests as $req)
                                    <tr class="transition hover:bg-slate-50/70">
                                        <td class="px-4 py-3">
                                            <a href="{{ route('loans.show', $req->loan) }}" class="font-medium text-blue-600 hover:text-blue-700">{{ $req->loan->loan_code }}</a>
                                        </td>
                                        <td class="whitespace-nowrap px-4 py-3 text-end tabular-nums text-slate-700">{{ \App\Support\Money::format($req->outstanding_principal) }}</td>
                                        <td class="whitespace-nowrap px-4 py-3 text-end font-medium tabular-nums text-navy-800">{{ \App\Support\Money::format($req->total_settlement) }}</td>
                                        <td class="px-4 py-3 text-slate-500">{{ \Illuminate\Support\Carbon::parse($req->updated_at ?? $req->foreclosure_request_date)->format('d M, Y') }}</td>
                                        <td class="whitespace-nowrap px-4 py-3 text-end tabular-nums text-slate-600">{{ $req->payment_date ? \Illuminate\Support\Carbon::parse($req->payment_date)->format('d M, Y') : '—' }}</td>
                                        <td class="whitespace-nowrap px-4 py-3 text-end tabular-nums text-emerald-600">{{ $req->actual_closure_date ? \Illuminate\Support\Carbon::parse($req->actual_closure_date)->format('d M, Y') : '—' }}</td>
                                        <td class="px-4 py-3">
                                            <x-status-badge :status="\App\Enums\LoanStatus::Foreclosed" class="text-[10px]" />
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @else
                <div class="fc-card p-8 text-center text-slate-500">
                    <flux:icon-check-circle class="size-6 mb-3" />
                    <p>{{ __('No approved foreclosure requests.') }}</p>
                </div>
            @endif

            <!-- Rejected Requests Section -->
            @if (count($rejectedRequests) > 0)
                <div class="fc-card p-4 mb-4">
                    <div class="flex justify-between items-center mb-3">
                        <h3 class="text-sm font-semibold uppercase tracking-wider text-rose-900">{{ __('Rejected Requests ({$count})', ['count' => count($rejectedRequests)]) }}</h3>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead class="fc-table-head">
                                <tr>
                                    <th class="px-4 py-3 text-left">{{ __('Loan') }}</th>
                                    <th class="px-4 py-3">{{ __('Rejection Reason') }}</th>
                                    <th class="px-4 py-3">{{ __('Requested Date') }}</th>
                                    <th class="px-4 py-3">{{ __('Rejection Date') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                @foreach ($rejectedRequests as $req)
                                    <tr class="transition hover:bg-slate-50/70">
                                        <td class="px-4 py-3">
                                            <a href="{{ route('loans.show', $req->loan) }}" class="font-medium text-blue-600 hover:text-blue-700">{{ $req->loan->loan_code }}</a>
                                        </td>
                                        <td class="whitespace-nowrap px-4 py-3 text-sm text-rose-600">{{ $req->rejection_reason ?: '—' }}</td>
                                        <td class="px-4 py-3 text-slate-500">{{ \Illuminate\Support\Carbon::parse($req->foreclosure_request_date)->format('d M, Y') }}</td>
                                        <td class="px-4 py-3 text-slate-500">{{ \Illuminate\Support\Carbon::parse($req->updated_at)->format('d M, Y') }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @else
                <div class="fc-card p-8 text-center text-slate-500">
                    <flux:icon-check-circle class="size-6 mb-3" />
                    <p>{{ __('No rejected foreclosure requests.') }}</p>
                </div>
            @endif

            <!-- Paid Foreclosure Requests Section -->
            @if (count($paidRequests) > 0)
                <div class="fc-card p-4">
                    <div class="flex justify-between items-center mb-3">
                        <h3 class="text-sm font-semibold uppercase tracking-wider text-emerald-900">{{ __('Paid Foreclosures ({$count})', ['count' => count($paidRequests)]) }}</h3>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead class="fc-table-head">
                                <tr>
                                    <th class="px-4 py-3 text-left">{{ __('Loan') }}</th>
                                    <th class="px-4 py-3 text-end">{{ __('Principal') }}</th>
                                    <th class="px-4 py-3">{{ __('Total Settlement') }}</th>
                                    <th class="px-4 py-3">{{ __('Payment Date') }}</th>
                                    <th class="px-4 py-3">{{ __('Closure Date') }}</th>
                                    <th class="px-4 py-3">{{ __('Status') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                @foreach ($paidRequests as $req)
                                    <tr class="transition hover:bg-slate-50/70">
                                        <td class="px-4 py-3">
                                            <a href="{{ route('loans.show', $req->loan) }}" class="font-medium text-blue-600 hover:text-blue-700">{{ $req->loan->loan_code }}</a>
                                        </td>
                                        <td class="whitespace-nowrap px-4 py-3 text-end tabular-nums text-slate-700">{{ \App\Support\Money::format($req->outstanding_principal) }}</td>
                                        <td class="whitespace-nowrap px-4 py-3 text-end font-medium tabular-nums text-navy-800">{{ \App\Support\Money::format($req->total_settlement) }}</td>
                                        <td class="whitespace-nowrap px-4 py-3 text-end tabular-nums text-slate-600">{{ $req->payment_date ? \Illuminate\Support\Carbon::parse($req->payment_date)->format('d M, Y') : '—' }}</td>
                                        <td class="whitespace-nowrap px-4 py-3 text-end tabular-nums text-emerald-600">{{ $req->actual_closure_date ? \Illuminate\Support\Carbon::parse($req->actual_closure_date)->format('d M, Y') : '—' }}</td>
                                        <td class="px-4 py-3">
                                            <x-status-badge :status="\App\Enums\LoanStatus::Foreclosed" class="text-[10px]" />
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @else
                <div class="fc-card p-8 text-center text-slate-500">
                    <flux:icon-check-circle class="size-6 mb-3" />
                    <p>{{ __('No paid foreclosure requests.') }}</p>
                </div>
            @endif
        </div>
    </div>
    @endsection
</x-layout>