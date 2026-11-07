<?php

use App\Enums\DailyTaskPriority;
use App\Enums\DailyTaskStatus;
use App\Enums\DailyTaskType;
use App\Enums\MessageChannel;
use App\Models\Borrower;
use App\Models\DailyTask;
use App\Models\EmiSchedule;
use App\Models\Investor;
use App\Models\InvestorPayout;
use App\Models\KycDocument;
use App\Models\Loan;
use App\Models\User;
use App\Services\DailyTaskGenerator;
use App\Support\Money;
use Flux\Flux;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Daily Operations')] class extends Component
{
    use WithPagination;

    #[Url(as: 'date')]
    public string $date = '';

    public string $statusFilter = 'pending';

    public string $typeFilter = '';

    public string $search = '';

    public bool $showTaskModal = false;

    public string $title = '';

    public string $description = '';

    public string $priority = 'normal';

    public string $assigned_to = '';

    public string $task_date = '';

    public bool $showReminderModal = false;

    public bool $showBulkModal = false;

    public bool $showCommunicateModal = false;

    public string $commChannel = 'whatsapp';

    public string $commSearch = '';

    public ?int $commBorrowerId = null;

    public string $commMessage = '';

    public string $commSubject = '';

    public ?int $reminderTaskId = null;

    public string $channel = 'whatsapp';

    public string $message = '';

    public string $reminderSubject = '';

    public function mount(): void
    {
        if ($this->date === '' || ! $this->isValidDate($this->date)) {
            $filter = (string) app(\App\Services\Settings\UserPreferenceService::class)
                ->get(auth()->user(), 'date_filter', 'today');

            $this->date = \App\Support\Regional::filterStartDate($filter)->toDateString();
        }
    }

    private function isValidDate(string $value): bool
    {
        try {
            $parsed = Carbon::createFromFormat('Y-m-d', $value);

            return $parsed !== false && $parsed->format('Y-m-d') === $value;
        } catch (\Throwable) {
            return false;
        }
    }

    private function taskDate(): Carbon
    {
        return Carbon::createFromFormat('Y-m-d', $this->date)->startOfDay();
    }

    public function updatedDate(): void
    {
        if (! $this->isValidDate($this->date)) {
            $this->date = now()->format('Y-m-d');
        }

        $this->resetPage();
    }

    public function previousDay(): void
    {
        $this->date = $this->taskDate()->subDay()->format('Y-m-d');
        $this->resetPage();
    }

    public function nextDay(): void
    {
        $this->date = $this->taskDate()->addDay()->format('Y-m-d');
        $this->resetPage();
    }

    public function goToToday(): void
    {
        $this->date = now()->format('Y-m-d');
        $this->resetPage();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedStatusFilter(): void
    {
        $this->resetPage();
    }

    public function updatedTypeFilter(): void
    {
        $this->resetPage();
    }

    /**
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator<int, DailyTask>
     */
    #[Computed]
    public function tasks()
    {
        $term = '%'.strtolower(trim($this->search)).'%';

        return DailyTask::query()
            ->forDate($this->taskDate()->toDateString())
            ->when($this->statusFilter !== 'all', fn ($query) => $query->where('status', $this->statusFilter))
            ->when($this->typeFilter !== '', fn ($query) => $query->ofType($this->typeFilter))
            ->when(trim($this->search) !== '', fn ($query) => $query->where(
                fn ($query) => $query
                    ->whereRaw('LOWER(title) LIKE ?', [$term])
                    ->orWhereRaw('LOWER(COALESCE(description, \'\')) LIKE ?', [$term])
            ))
            ->with([
                'related' => fn (MorphTo $morphTo) => $morphTo->morphWith([
                    EmiSchedule::class => ['loan.borrower'],
                    Loan::class => ['borrower'],
                    KycDocument::class => ['documentable'],
                    InvestorPayout::class => ['investor'],
                ]),
                'assignedTo:id,name',
                'completedBy:id,name',
            ])
            ->orderByRaw("CASE WHEN status = 'pending' THEN 0 WHEN status = 'skipped' THEN 1 ELSE 2 END")
            ->orderByRaw("CASE priority WHEN 'high' THEN 0 WHEN 'normal' THEN 1 ELSE 2 END")
            ->orderBy('id')
            ->paginate(\App\Services\Settings\UserPreferenceService::rowsPerPageFor(auth()->user(), 12));
    }

    /**
     * @return array{total: int, pending: int, done: int, skipped: int, remindersPending: int, highPending: int, progress: int}
     */
    #[Computed]
    public function counts(): array
    {
        $byStatus = DailyTask::query()
            ->forDate($this->taskDate()->toDateString())
            ->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $total = (int) $byStatus->sum();
        $pending = (int) ($byStatus[DailyTaskStatus::Pending->value] ?? 0);
        $done = (int) ($byStatus[DailyTaskStatus::Done->value] ?? 0);
        $skipped = (int) ($byStatus[DailyTaskStatus::Skipped->value] ?? 0);

        $remindersPending = DailyTask::query()
            ->forDate($this->taskDate()->toDateString())
            ->pending()
            ->ofType(DailyTaskType::EmiReminder)
            ->count();

        $highPending = DailyTask::query()
            ->forDate($this->taskDate()->toDateString())
            ->pending()
            ->where('priority', DailyTaskPriority::High->value)
            ->count();

        return [
            'total' => $total,
            'pending' => $pending,
            'done' => $done,
            'skipped' => $skipped,
            'remindersPending' => $remindersPending,
            'highPending' => $highPending,
            'progress' => $total > 0 ? (int) round($done / $total * 100) : 0,
        ];
    }

    /**
     * @return \Illuminate\Support\Collection<int, User>
     */
    #[Computed]
    public function staff()
    {
        return User::query()->orderBy('name')->get(['id', 'name']);
    }

    public function generateTasks(): void
    {
        $created = app(DailyTaskGenerator::class)->generateForDate($this->taskDate(), auth()->id());

        Flux::toast(
            variant: 'success',
            text: $created > 0
                ? __(':count new task(s) generated from system data.', ['count' => $created])
                : __('Everything is already up to date — no new tasks to add.'),
        );

        $this->resetPage();
        unset($this->tasks, $this->counts);
    }

    public function toggleTask(int $taskId): void
    {
        $task = DailyTask::query()->findOrFail($taskId);

        if ($task->status === DailyTaskStatus::Pending) {
            $task->update([
                'status' => DailyTaskStatus::Done,
                'completed_at' => now(),
                'completed_by' => auth()->id(),
            ]);
        } else {
            $task->update([
                'status' => DailyTaskStatus::Pending,
                'completed_at' => null,
                'completed_by' => null,
            ]);
        }

        unset($this->tasks, $this->counts);
    }

    public function skipTask(int $taskId): void
    {
        $task = DailyTask::query()->findOrFail($taskId);

        if ($task->status !== DailyTaskStatus::Pending) {
            return;
        }

        $task->update(['status' => DailyTaskStatus::Skipped]);

        unset($this->tasks, $this->counts);
    }

    public function deleteTask(int $taskId): void
    {
        $task = DailyTask::query()->findOrFail($taskId);

        if ($task->auto_generated) {
            Flux::toast(variant: 'danger', text: __('Generated tasks cannot be deleted — mark them as skipped instead.'));

            return;
        }

        $task->delete();
        Flux::toast(variant: 'success', text: __('Task removed.'));

        unset($this->tasks, $this->counts);
    }

    public function openTaskModal(): void
    {
        $this->reset(['title', 'description', 'assigned_to']);
        $this->priority = DailyTaskPriority::Normal->value;
        $this->task_date = $this->date;
        $this->showTaskModal = true;
    }

    public function saveTask(): void
    {
        $validated = $this->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'priority' => ['required', Rule::enum(DailyTaskPriority::class)],
            'assigned_to' => ['nullable', Rule::exists('users', 'id')],
            'task_date' => ['required', 'date'],
        ]);

        DailyTask::query()->create([
            ...$validated,
            'assigned_to' => $validated['assigned_to'] !== '' ? $validated['assigned_to'] : null,
            'type' => DailyTaskType::Manual,
            'status' => DailyTaskStatus::Pending,
            'auto_generated' => false,
            'created_by' => auth()->id(),
        ]);

        $this->showTaskModal = false;
        Flux::toast(variant: 'success', text: __('Task added.'));

        if ($validated['task_date'] === $this->date) {
            $this->resetPage();
        }

        unset($this->tasks, $this->counts);
    }

    /**
     * @return array{mobile: ?string, email: ?string, name: string, body: string, subject: string}|null
     */
    private function reminderContext(DailyTask $task): ?array
    {
        $related = $task->related;

        if ($related instanceof EmiSchedule) {
            $loan = $related->loan;
            $borrower = $loan?->borrower;

            return [
                'mobile' => $borrower?->mobile,
                'email' => $borrower?->email,
                'name' => $borrower?->full_name ?? __('borrower'),
                'subject' => sprintf('EMI reminder — %s', $loan?->loan_code ?? ''),
                'body' => sprintf(
                    'Dear %s, this is a reminder that your EMI of %s for loan %s (installment #%d) is due on %s. Please arrange payment. If already paid, please ignore. — %s',
                    $borrower?->full_name ?? 'borrower',
                    Money::format($related->remainingAmount()),
                    $loan?->loan_code ?? '',
                    $related->installment_no,
                    $related->due_date->format(\App\Support\Regional::dateFormat()),
                    config('app.name')
                ),
            ];
        }

        if ($related instanceof Loan) {
            $borrower = $related->borrower;
            $overdue = $related->schedules()->overdue()->get();
            $total = $overdue->sum(fn (EmiSchedule $schedule): float => (float) $schedule->remainingAmount());

            return [
                'mobile' => $borrower?->mobile,
                'email' => $borrower?->email,
                'name' => $borrower?->full_name ?? __('borrower'),
                'subject' => sprintf('Overdue EMI reminder — %s', $related->loan_code),
                'body' => sprintf(
                    'Dear %s, our records show your EMI(s) for loan %s are overdue totalling %s. Please clear the outstanding at the earliest to avoid penalty. — %s',
                    $borrower?->full_name ?? 'borrower',
                    $related->loan_code,
                    Money::format((string) $total),
                    config('app.name')
                ),
            ];
        }

        return null;
    }

    /**
     * Normalise an Indian mobile number for wa.me/sms links (10-digit → country code prefix).
     */
    private function phoneForLink(string $mobile): string
    {
        $digits = preg_replace('/\D+/', '', $mobile) ?? '';

        if (strlen($digits) === 10) {
            return '91'.$digits;
        }

        return $digits;
    }

    /**
     * Build the manual link for the currently selected channel. Nothing is sent from the server.
     *
     * @return array{url: string, label: string, recipient: string}|null
     */
    #[Computed]
    public function reminderLink(): ?array
    {
        if ($this->reminderTaskId === null) {
            return null;
        }

        $task = DailyTask::query()->with('related')->find($this->reminderTaskId);
        $context = $task ? $this->reminderContext($task) : null;

        if ($context === null) {
            return null;
        }

        $body = $this->message;

        return match ($this->channel) {
            MessageChannel::Whatsapp->value => filled($context['mobile'] ?? null) ? [
                'url' => 'https://wa.me/'.$this->phoneForLink((string) $context['mobile']).'?text='.rawurlencode($body),
                'label' => __('Open WhatsApp'),
                'recipient' => (string) $context['mobile'],
            ] : null,
            MessageChannel::Sms->value => filled($context['mobile'] ?? null) ? [
                'url' => 'sms:'.$this->phoneForLink((string) $context['mobile']).'?body='.rawurlencode($body),
                'label' => __('Open SMS app'),
                'recipient' => (string) $context['mobile'],
            ] : null,
            MessageChannel::Email->value => filled($context['email'] ?? null) ? [
                'url' => 'mailto:'.$context['email'].'?subject='.rawurlencode($this->reminderSubject).'&body='.rawurlencode($body),
                'label' => __('Open Email app'),
                'recipient' => (string) $context['email'],
            ] : null,
            default => null,
        };
    }

    /**
     * @return array{name: string, mobile: ?string, email: ?string}|null
     */
    #[Computed]
    public function reminderContact(): ?array
    {
        if ($this->reminderTaskId === null) {
            return null;
        }

        $task = DailyTask::query()->with('related')->find($this->reminderTaskId);
        $context = $task ? $this->reminderContext($task) : null;

        if ($context === null) {
            return null;
        }

        return [
            'name' => $context['name'],
            'mobile' => $context['mobile'],
            'email' => $context['email'],
        ];
    }

    public function openReminder(int $taskId): void
    {
        $task = DailyTask::query()->with('related')->findOrFail($taskId);
        $context = $this->reminderContext($task);

        if (! $context) {
            Flux::toast(variant: 'danger', text: __('This task does not have a borrower to message.'));

            return;
        }

        if (blank($context['mobile'])) {
            Flux::toast(variant: 'danger', text: __('No mobile number on file for this borrower.'));

            return;
        }

        $this->reminderTaskId = $task->getKey();
        $this->channel = 'whatsapp';
        $this->message = $context['body'];
        $this->reminderSubject = $context['subject'];
        $this->showReminderModal = true;
    }

    /**
     * Manually mark the reminder as sent and complete the task. Nothing is sent from the server.
     */
    public function markReminderSent(): void
    {
        $task = DailyTask::query()->with('related')->find($this->reminderTaskId);

        if ($task === null) {
            $this->showReminderModal = false;

            return;
        }

        $context = $this->reminderContext($task);

        $this->completeTask($task);

        $this->showReminderModal = false;
        $this->reminderTaskId = null;

        Flux::toast(variant: 'success', text: __('Marked reminder for :name as sent. Task marked done.', ['name' => $context['name'] ?? __('borrower')]));

        unset($this->tasks, $this->counts);
    }

    public function openBulkModal(): void
    {
        unset($this->pendingReminders);
        $this->showBulkModal = true;
    }

    /**
     * Pending EMI reminders for the selected day with their prebuilt manual links.
     *
     * @return array<int, array{id: int, name: string, mobile: ?string, email: ?string, whatsapp: string, sms: string, mailto: ?string}>
     */
    #[Computed]
    public function pendingReminders(): array
    {
        $items = [];

        DailyTask::query()
            ->forDate($this->taskDate()->toDateString())
            ->pending()
            ->ofType(DailyTaskType::EmiReminder)
            ->with(['related' => fn (MorphTo $morphTo) => $morphTo->morphWith([
                EmiSchedule::class => ['loan.borrower'],
                Loan::class => ['borrower'],
            ])])
            ->orderBy('id')
            ->get()
            ->each(function (DailyTask $task) use (&$items): void {
                $context = $this->reminderContext($task);

                if ($context === null) {
                    return;
                }

                $items[] = [
                    'id' => $task->getKey(),
                    'name' => $context['name'],
                    'mobile' => $context['mobile'],
                    'email' => $context['email'],
                    'whatsapp' => filled($context['mobile'] ?? null)
                        ? 'https://wa.me/'.$this->phoneForLink((string) $context['mobile']).'?text='.rawurlencode($context['body'])
                        : '#',
                    'sms' => filled($context['mobile'] ?? null)
                        ? 'sms:'.$this->phoneForLink((string) $context['mobile']).'?body='.rawurlencode($context['body'])
                        : '#',
                    'mailto' => filled($context['email'] ?? null)
                        ? 'mailto:'.$context['email'].'?subject='.rawurlencode($context['subject']).'&body='.rawurlencode($context['body'])
                        : null,
                ];
            });

        return $items;
    }

    /**
     * @return \Illuminate\Support\Collection<int, Borrower>
     */
    #[Computed]
    public function commBorrowers()
    {
        $query = Borrower::query()->active()->orderBy('full_name');

        $term = trim($this->commSearch);

        if ($term !== '') {
            $query->search($term);
        }

        return $query->limit(15)->get();
    }

    #[Computed]
    public function commBorrower(): ?Borrower
    {
        if ($this->commBorrowerId === null) {
            return null;
        }

        return Borrower::query()->find($this->commBorrowerId);
    }

    /**
     * @return array{url: string, label: string}|null
     */
    #[Computed]
    public function commLink(): ?array
    {
        $borrower = $this->commBorrower;

        if ($borrower === null || trim($this->commMessage) === '') {
            return null;
        }

        $body = $this->commMessage;

        return match ($this->commChannel) {
            MessageChannel::Whatsapp->value => filled($borrower->mobile) ? [
                'url' => 'https://wa.me/'.$this->phoneForLink($borrower->mobile).'?text='.rawurlencode($body),
                'label' => __('Open WhatsApp'),
            ] : null,
            MessageChannel::Sms->value => filled($borrower->mobile) ? [
                'url' => 'sms:'.$this->phoneForLink($borrower->mobile).'?body='.rawurlencode($body),
                'label' => __('Open SMS app'),
            ] : null,
            MessageChannel::Email->value => filled($borrower->email) ? [
                'url' => 'mailto:'.$borrower->email.'?subject='.rawurlencode($this->commSubject !== '' ? $this->commSubject : __('Regarding your loan')).'&body='.rawurlencode($body),
                'label' => __('Open Email app'),
            ] : null,
            default => null,
        };
    }

    /**
     * @return array<string, array{label: string, subject: string, body: string}>
     */
    #[Computed]
    public function commTemplates(): array
    {
        $app = config('app.name');

        return [
            'emi_reminder' => [
                'label' => __('EMI reminder'),
                'subject' => __('EMI reminder'),
                'body' => "Dear {name}, this is a reminder that your EMI is due soon. Please arrange payment on time. If already paid, please ignore. — {app}",
            ],
            'overdue' => [
                'label' => __('Pending dues'),
                'subject' => __('Overdue EMI reminder'),
                'body' => "Dear {name}, our records show overdue EMIs on your loan account. Please clear the outstanding at the earliest to avoid penalty. — {app}",
            ],
            'payment_confirmation' => [
                'label' => __('Payment confirmation'),
                'subject' => __('Payment received'),
                'body' => "Dear {name}, we have received your payment. Thank you for your prompt clearance. — {app}",
            ],
            'general' => [
                'label' => __('General update'),
                'subject' => __('Loan update'),
                'body' => "Dear {name}, this is an update regarding your loan account. — {app}",
            ],
        ];
    }

    public function openCommunicate(string $channel = 'whatsapp'): void
    {
        $this->commChannel = in_array($channel, ['whatsapp', 'sms', 'email'], true) ? $channel : 'whatsapp';
        $this->showCommunicateModal = true;
    }

    public function selectCommBorrower(int $borrowerId): void
    {
        $this->commBorrowerId = $borrowerId;

        if (trim($this->commMessage) === '') {
            $borrower = Borrower::query()->find($borrowerId);

            if ($borrower !== null) {
                $this->commMessage = str_replace(
                    ['{name}', '{app}'],
                    [$borrower->full_name, config('app.name')],
                    __('Dear {name}, this is a reminder regarding your loan account. — {app}'),
                );
            }
        }
    }

    public function applyCommTemplate(string $key): void
    {
        $templates = $this->commTemplates;

        if (! isset($templates[$key])) {
            return;
        }

        $name = $this->commBorrower?->full_name ?? __('borrower');
        $app = config('app.name');

        $this->commMessage = str_replace(['{name}', '{app}'], [$name, $app], $templates[$key]['body']);
        $this->commSubject = $templates[$key]['subject'];
    }

    /**
     * Manually mark one bulk reminder as sent. Nothing is sent from the server.
     */
    public function bulkMarkSent(int $taskId): void
    {
        $task = DailyTask::query()->find($taskId);

        if ($task === null) {
            return;
        }

        $this->completeTask($task);
        Flux::toast(variant: 'success', text: __('Marked as sent. Task marked done.'));

        unset($this->tasks, $this->counts, $this->pendingReminders);
    }

    private function completeTask(DailyTask $task): void
    {
        $task->update([
            'status' => DailyTaskStatus::Done,
            'completed_at' => now(),
            'completed_by' => auth()->id(),
        ]);
    }

    /**
     * @return array{url: string, label: string}|null
     */
    public function relatedLink(DailyTask $task): ?array
    {
        $related = $task->related;

        return match (true) {
            $related instanceof EmiSchedule => $related->loan
                ? ['url' => route('loans.show', $related->loan), 'label' => $related->loan->loan_code]
                : null,
            $related instanceof Loan => ['url' => route('loans.show', $related), 'label' => $related->loan_code],
            $related instanceof KycDocument => $this->kycLink($related),
            $related instanceof InvestorPayout => ['url' => route('payouts.index'), 'label' => $related->payout_code],
            default => null,
        };
    }

    /**
     * @return array{url: string, label: string}|null
     */
    private function kycLink(KycDocument $document): ?array
    {
        $owner = $document->documentable;

        return match (true) {
            $owner instanceof Borrower => ['url' => route('borrowers.show', $owner), 'label' => $owner->full_name],
            $owner instanceof Investor => ['url' => route('investors.show', $owner), 'label' => $owner->name],
            default => null,
        };
    }
};
?>

<div class="mx-auto max-w-[1400px] space-y-5">
    <div class="flex flex-col justify-between gap-4 2xl:flex-row 2xl:items-end">
        <div>
            <p class="fc-eyebrow">{{ __('Operations') }}</p>
            <h2 class="mt-1 text-2xl font-semibold tracking-tight">{{ __('Daily Operations') }}</h2>
            <p class="mt-1 text-sm text-slate-500">{{ __('Today’s to-dos: EMI reminders, overdue follow-ups, KYC reviews and everything else the day demands.') }}</p>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <div class="flex items-center gap-1 rounded-xl border border-slate-200 bg-white p-1 shadow-sm" role="group" aria-label="{{ __('Communicate with a borrower') }}">
                <span class="hidden px-2 text-[10px] font-bold uppercase tracking-[0.14em] text-slate-400 sm:inline">{{ __('Reach out') }}</span>
                <button type="button" wire:click="openCommunicate('whatsapp')" class="flex items-center gap-1.5 rounded-lg px-2.5 py-2 text-[11px] font-semibold text-emerald-700 transition hover:bg-emerald-50" title="{{ __('Message a borrower on WhatsApp') }}" aria-label="{{ __('WhatsApp a borrower') }}">
                    <flux:icon.chat-bubble-left-right class="size-4" />
                    <span class="hidden sm:inline">{{ __('WhatsApp') }}</span>
                </button>
                <button type="button" wire:click="openCommunicate('sms')" class="flex items-center gap-1.5 rounded-lg px-2.5 py-2 text-[11px] font-semibold text-blue-700 transition hover:bg-blue-50" title="{{ __('Message a borrower by SMS') }}" aria-label="{{ __('SMS a borrower') }}">
                    <flux:icon.device-phone-mobile class="size-4" />
                    <span class="hidden sm:inline">{{ __('SMS') }}</span>
                </button>
                <button type="button" wire:click="openCommunicate('email')" class="flex items-center gap-1.5 rounded-lg px-2.5 py-2 text-[11px] font-semibold text-violet-700 transition hover:bg-violet-50" title="{{ __('Email a borrower') }}" aria-label="{{ __('Email a borrower') }}">
                    <flux:icon.envelope class="size-4" />
                    <span class="hidden sm:inline">{{ __('Email') }}</span>
                </button>
            </div>

            <div class="flex items-center rounded-xl border border-slate-200 bg-white p-1 shadow-sm">
                <button type="button" wire:click="previousDay" class="rounded-lg p-2 text-slate-500 transition hover:bg-slate-100 hover:text-slate-700" aria-label="{{ __('Previous day') }}">
                    <flux:icon.chevron-left class="size-4" />
                </button>
                <input type="date" wire:model.live="date" class="w-[9.5rem] border-0 bg-transparent px-1 py-1.5 text-center text-sm font-medium text-slate-700 focus:outline-none focus:ring-0" aria-label="{{ __('Selected day') }}">
                <button type="button" wire:click="nextDay" class="rounded-lg p-2 text-slate-500 transition hover:bg-slate-100 hover:text-slate-700" aria-label="{{ __('Next day') }}">
                    <flux:icon.chevron-right class="size-4" />
                </button>
            </div>

            @if ($this->date !== now()->format('Y-m-d'))
                <button type="button" wire:click="goToToday" class="rounded-xl border border-slate-200 bg-white px-3.5 py-2.5 text-xs font-semibold text-slate-600 shadow-sm transition hover:bg-slate-50">
                    {{ __('Today') }}
                </button>
            @endif

            <button type="button" wire:click="generateTasks" class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-3.5 py-2.5 text-xs font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50">
                <flux:icon.arrow-path class="size-4 text-slate-500" />
                {{ __('Generate from system') }}
            </button>
            <button type="button" wire:click="openTaskModal" class="inline-flex items-center gap-2 rounded-xl bg-navy-900 px-3.5 py-2.5 text-xs font-semibold text-white shadow-lg shadow-navy-900/20 transition hover:bg-navy-800">
                <flux:icon.plus class="size-4" />
                {{ __('Add task') }}
            </button>
        </div>
    </div>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-stat-card :label="__('Pending tasks')" :value="(string) $this->counts['pending']" :hint="__('Action needed today')" icon="clipboard-document-list" tone="amber" />
        <x-stat-card :label="__('Completed')" :value="(string) $this->counts['done']" :hint="__('Done on selected day')" icon="check-circle" tone="emerald" />
        <x-stat-card :label="__('EMI reminders pending')" :value="(string) $this->counts['remindersPending']" :hint="__('Due on selected day')" icon="bell-alert" tone="blue" />
        <x-stat-card :label="__('High priority pending')" :value="(string) $this->counts['highPending']" :hint="__('Mostly overdue follow-ups')" icon="exclamation-triangle" tone="rose" />
    </div>

    <div class="fc-card px-5 py-4">
        <div class="flex items-center justify-between gap-4">
            <div>
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">{{ __('Day progress') }}</p>
                <p class="mt-0.5 text-[13px] text-slate-500">{{ __(':done of :total task(s) completed · :skipped skipped', ['done' => $this->counts['done'], 'total' => $this->counts['total'], 'skipped' => $this->counts['skipped']]) }}</p>
            </div>
            <span class="text-sm font-semibold tabular-nums text-slate-700">{{ $this->counts['progress'] }}%</span>
        </div>
        <div class="mt-2.5 h-2 w-full overflow-hidden rounded-full bg-slate-100">
            <div class="h-full rounded-full bg-emerald-500 transition-all duration-500" style="width: {{ $this->counts['progress'] }}%"></div>
        </div>
    </div>

    <div class="fc-card overflow-hidden">
        <div class="flex flex-col gap-3 border-b border-slate-100 p-4 lg:flex-row lg:items-center lg:justify-between">
            <div class="flex flex-1 flex-col gap-3 sm:flex-row sm:items-center">
                <div class="relative w-full sm:max-w-xs">
                    <flux:icon.magnifying-glass class="pointer-events-none absolute start-3 top-1/2 size-4 -translate-y-1/2 text-slate-400" />
                    <input type="search" wire:model.live.debounce.300ms="search" placeholder="{{ __('Search tasks…') }}" class="w-full rounded-xl border border-slate-200 bg-slate-50 py-2.5 pe-3 ps-9 text-sm text-slate-700 placeholder:text-slate-400 focus:border-blue-500 focus:bg-white focus:outline-none focus:ring-2 focus:ring-blue-500/20">
                </div>

                <select wire:model.live="typeFilter" class="rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-600 focus:border-blue-500 focus:outline-none">
                    <option value="">{{ __('All types') }}</option>
                    @foreach (\App\Enums\DailyTaskType::options() as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>

                <select wire:model.live="statusFilter" class="rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-600 focus:border-blue-500 focus:outline-none">
                    <option value="all">{{ __('All statuses') }}</option>
                    @foreach (\App\Enums\DailyTaskStatus::options() as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            @if ($this->counts['remindersPending'] > 0)
                <button type="button" wire:click="openBulkModal" class="inline-flex shrink-0 items-center gap-2 rounded-xl border border-blue-200 bg-blue-50 px-3.5 py-2.5 text-xs font-semibold text-blue-700 transition hover:bg-blue-100">
                    <flux:icon.chat-bubble-left-right class="size-4" />
                    {{ __('Manual reminders (:count)', ['count' => $this->counts['remindersPending']]) }}
                </button>
            @endif
        </div>

        <div class="divide-y divide-slate-100">
            @forelse ($this->tasks as $task)
                @php
                    $typeColor = match ($task->type->color()) {
                        'blue' => 'bg-blue-50 text-blue-600 ring-blue-600/10',
                        'rose' => 'bg-rose-50 text-rose-600 ring-rose-600/10',
                        'amber' => 'bg-amber-50 text-amber-700 ring-amber-600/15',
                        'purple' => 'bg-purple-50 text-purple-600 ring-purple-600/10',
                        'cyan' => 'bg-cyan-50 text-cyan-700 ring-cyan-600/10',
                        default => 'bg-slate-100 text-slate-500 ring-slate-500/10',
                    };
                    $link = $this->relatedLink($task);
                    $isDone = $task->isDone();
                @endphp

                <div wire:key="task-{{ $task->id }}" class="group flex items-start gap-3 px-4 py-3.5 transition hover:bg-slate-50/60 sm:px-5 {{ $isDone ? 'opacity-60' : '' }}">
                    <button type="button" wire:click="toggleTask({{ $task->id }})" aria-pressed="{{ $isDone ? 'true' : 'false' }}" aria-label="{{ $isDone ? __('Reopen task') : __('Complete task') }}"
                        class="mt-0.5 flex size-5 shrink-0 items-center justify-center rounded-md border transition {{ $isDone ? 'border-emerald-500 bg-emerald-500 text-white' : 'border-slate-300 bg-white text-transparent hover:border-emerald-500 hover:text-emerald-500' }}">
                        <flux:icon.check class="size-3.5" stroke-width="3" />
                    </button>

                    <span class="mt-0.5 flex size-9 shrink-0 items-center justify-center rounded-lg ring-1 {{ $typeColor }}" title="{{ $task->type->label() }}">
                        <x-dynamic-component :component="'flux::icon.'.$task->type->icon()" class="size-4.5" />
                    </span>

                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-x-2 gap-y-1">
                            <p class="text-[13px] font-semibold leading-snug text-slate-800 {{ $isDone ? 'line-through decoration-slate-300' : '' }}">{{ $task->title }}</p>

                            @if ($task->priority === \App\Enums\DailyTaskPriority::High)
                                <span class="rounded-full bg-rose-50 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-rose-600 ring-1 ring-rose-600/15">{{ __('High') }}</span>
                            @endif

                            @if ($task->auto_generated)
                                <span class="rounded-full bg-slate-100 px-2 py-0.5 text-[9px] font-semibold uppercase tracking-[0.12em] text-slate-400" title="{{ __('Generated from system data') }}">{{ __('Auto') }}</span>
                            @endif

                            @if ($task->status === \App\Enums\DailyTaskStatus::Skipped)
                                <span class="rounded-full bg-slate-100 px-2 py-0.5 text-[9px] font-semibold uppercase tracking-[0.12em] text-slate-400">{{ __('Skipped') }}</span>
                            @endif
                        </div>

                        @if ($task->description)
                            <p class="mt-1 text-xs leading-relaxed text-slate-500">{{ $task->description }}</p>
                        @endif

                        <div class="mt-1.5 flex flex-wrap items-center gap-x-3 gap-y-1 text-[11px] text-slate-400">
                            @if ($link)
                                <a href="{{ $link['url'] }}" wire:navigate class="inline-flex items-center gap-1 font-medium text-blue-600 hover:text-blue-700">
                                    <flux:icon.arrow-top-right-on-square class="size-3" />
                                    {{ $link['label'] }}
                                </a>
                            @endif

                            @if ($task->assignedTo)
                                <span class="inline-flex items-center gap-1">
                                    <flux:icon.user class="size-3" />
                                    {{ __('Assigned to :name', ['name' => $task->assignedTo->name]) }}
                                </span>
                            @endif

                            @if ($isDone && $task->completedBy)
                                <span class="inline-flex items-center gap-1">
                                    <flux:icon.check-badge class="size-3" />
                                    {{ __('Done by :name', ['name' => $task->completedBy->name]) }}
                                </span>
                            @endif
                        </div>
                    </div>

                    <div class="flex shrink-0 flex-col items-end gap-2 sm:flex-row sm:items-center">
                        @if ($task->status === \App\Enums\DailyTaskStatus::Pending)
                            @if ($task->type->isMessageable())
                                <button type="button" wire:click="openReminder({{ $task->id }})" class="inline-flex items-center gap-1.5 rounded-lg border border-blue-200 bg-white px-2.5 py-1.5 text-[11px] font-semibold text-blue-700 shadow-sm transition hover:border-blue-300 hover:bg-blue-50">
                                    <flux:icon.chat-bubble-left-right class="size-3.5" />
                                    {{ __('Remind') }}
                                </button>
                            @endif

                            <button type="button" wire:click="skipTask({{ $task->id }})" class="rounded-lg px-2.5 py-1.5 text-[11px] font-semibold text-slate-400 transition hover:bg-slate-100 hover:text-slate-600">
                                {{ __('Skip') }}
                            </button>
                        @else
                            <button type="button" wire:click="toggleTask({{ $task->id }})" class="inline-flex items-center gap-1.5 rounded-lg px-2.5 py-1.5 text-[11px] font-semibold text-slate-400 transition hover:bg-slate-100 hover:text-slate-600">
                                <flux:icon.arrow-uturn-left class="size-3.5" />
                                {{ __('Reopen') }}
                            </button>
                        @endif

                        @unless ($task->auto_generated)
                            <button type="button" wire:click="deleteTask({{ $task->id }})" wire:confirm="{{ __('Delete this task?') }}" class="rounded-lg p-1.5 text-slate-300 transition hover:bg-rose-50 hover:text-rose-500" aria-label="{{ __('Delete task') }}">
                                <flux:icon.trash class="size-4" />
                            </button>
                        @endunless
                    </div>
                </div>
            @empty
                <div class="px-5 py-14 text-center">
                    <span class="mx-auto flex size-12 items-center justify-center rounded-2xl bg-slate-100 text-slate-400">
                        <flux:icon.clipboard-document-check class="size-6" />
                    </span>
                    <p class="mt-3 text-sm font-semibold text-slate-600">{{ __('Nothing on the list') }}</p>
                    <p class="mx-auto mt-1 max-w-sm text-xs text-slate-400">{{ __('No tasks match this view. Generate tasks from system data or add a manual one.') }}</p>
                    <button type="button" wire:click="generateTasks" class="mt-4 inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-2 text-xs font-semibold text-slate-600 shadow-sm transition hover:bg-slate-50">
                        <flux:icon.arrow-path class="size-4" />
                        {{ __('Generate from system') }}
                    </button>
                </div>
            @endforelse
        </div>

        @if ($this->tasks->hasPages())
            <div class="border-t border-slate-100 px-5 py-3">{{ $this->tasks->links() }}</div>
        @endif
    </div>

    @if ($showTaskModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-slate-900/50 backdrop-blur-sm" wire:click="$set('showTaskModal', false)"></div>

            <div class="relative w-full max-w-md rounded-2xl bg-white p-5 shadow-2xl">
                <div class="flex items-start justify-between">
                    <div>
                        <h3 class="text-base font-semibold">{{ __('Add task') }}</h3>
                        <p class="mt-0.5 text-xs text-slate-400">{{ __('A manual to-do for the selected day.') }}</p>
                    </div>
                    <button type="button" wire:click="$set('showTaskModal', false)" class="rounded-lg p-1.5 text-slate-400 hover:bg-slate-100"><flux:icon.x-mark class="size-4" /></button>
                </div>

                <form wire:submit="saveTask" class="mt-4 space-y-3">
                    <flux:input wire:model="title" :label="__('Task')" placeholder="{{ __('e.g. Call borrower about overdue EMI') }}" maxlength="255" required />
                    @error('title') <p class="text-xs text-rose-600">{{ $message }}</p> @enderror

                    <flux:textarea wire:model="description" :label="__('Notes')" rows="2" maxlength="1000" placeholder="{{ __('Optional details…') }}" />
                    @error('description') <p class="text-xs text-rose-600">{{ $message }}</p> @enderror

                    <div class="grid grid-cols-2 gap-3">
                        <flux:select wire:model="priority" :label="__('Priority')">
                            @foreach (\App\Enums\DailyTaskPriority::options() as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </flux:select>
                        <flux:select wire:model="assigned_to" :label="__('Assign to')">
                            <option value="">{{ __('Unassigned') }}</option>
                            @foreach ($this->staff as $member)
                                <option value="{{ $member->id }}">{{ $member->name }}</option>
                            @endforeach
                        </flux:select>
                    </div>
                    @error('priority') <p class="text-xs text-rose-600">{{ $message }}</p> @enderror
                    @error('assigned_to') <p class="text-xs text-rose-600">{{ $message }}</p> @enderror

                    <flux:input wire:model="task_date" :label="__('For date')" type="date" required />
                    @error('task_date') <p class="text-xs text-rose-600">{{ $message }}</p> @enderror

                    <div class="flex items-center justify-end gap-2 pt-1">
                        <button type="button" wire:click="$set('showTaskModal', false)" class="rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-xs font-semibold text-slate-600">{{ __('Cancel') }}</button>
                        <flux:button type="submit" variant="primary" class="!rounded-xl">{{ __('Add task') }}</flux:button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    @if ($showReminderModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4" x-data="{ copied: false }">
            <div class="absolute inset-0 bg-slate-900/50 backdrop-blur-sm" wire:click="$set('showReminderModal', false)"></div>

            <div class="relative w-full max-w-lg rounded-2xl bg-white p-5 shadow-2xl">
                <div class="flex items-start justify-between">
                    <div>
                        <h3 class="text-base font-semibold">{{ __('Manual communication') }}</h3>
                        <p class="mt-0.5 text-xs text-slate-400">{{ __('Review and edit, then open the app to send. Nothing is sent until you confirm yourself.') }}</p>
                    </div>
                    <button type="button" wire:click="$set('showReminderModal', false)" class="rounded-lg p-1.5 text-slate-400 hover:bg-slate-100"><flux:icon.x-mark class="size-4" /></button>
                </div>

                <div class="mt-4 rounded-xl border border-slate-200 bg-slate-50/70 p-3 text-xs">
                    <p class="font-semibold text-slate-800">{{ $this->reminderContact['name'] ?? __('Borrower') }}</p>
                    <dl class="mt-1.5 space-y-1 text-[11px] text-slate-500">
                        <div class="flex justify-between gap-3"><dt>{{ __('Mobile') }}</dt><dd class="font-medium tabular-nums text-slate-700">{{ $this->reminderContact['mobile'] ?? '—' }}</dd></div>
                        <div class="flex justify-between gap-3"><dt>{{ __('Email') }}</dt><dd class="break-all font-medium text-slate-700">{{ $this->reminderContact['email'] ?? __('Not on file') }}</dd></div>
                    </dl>
                </div>

                <div class="mt-4 space-y-3">
                    <div class="grid grid-cols-3 gap-2">
                        @foreach (['whatsapp' => 'WhatsApp', 'sms' => 'SMS', 'email' => 'Email'] as $value => $label)
                            @php
                                $disabled = $value === 'email' && blank($this->reminderContact['email'] ?? null);
                            @endphp
                            <label class="flex cursor-pointer flex-col items-center gap-1.5 rounded-xl border p-2.5 text-center transition {{ $channel === $value ? 'border-blue-500 bg-blue-50/50 ring-2 ring-blue-500/20' : 'border-slate-200 hover:border-slate-300' }} {{ $disabled ? 'cursor-not-allowed opacity-40' : '' }}">
                                <input type="radio" wire:model.live="channel" value="{{ $value }}" @disabled($disabled) class="sr-only">
                                <span class="flex size-8 items-center justify-center rounded-lg {{ $channel === $value ? 'bg-blue-600 text-white' : 'bg-slate-100 text-slate-500' }}">
                                    <flux:icon.chat-bubble-left-right class="size-4" />
                                </span>
                                <span class="text-[11px] font-semibold text-slate-700">{{ $label }}</span>
                            </label>
                        @endforeach
                    </div>

                    @if ($channel === 'email')
                        <flux:input wire:model="reminderSubject" :label="__('Subject')" maxlength="255" required />
                    @endif

                    <flux:textarea wire:model="message" :label="__('Message')" rows="4" maxlength="1000" required x-ref="messageField" />
                    @error('message') <p class="text-xs text-rose-600">{{ $message }}</p> @enderror

                    <div class="flex flex-wrap items-center justify-between gap-2 pt-1">
                        <button type="button" x-on:click="navigator.clipboard.writeText($refs.messageField.value); copied = true; setTimeout(() => copied = false, 1500)"
                                class="inline-flex items-center gap-1.5 rounded-xl border border-slate-200 bg-white px-3.5 py-2.5 text-xs font-semibold text-slate-600 transition hover:border-slate-300 hover:text-slate-900">
                            <flux:icon.clipboard-document class="size-4" />
                            <span x-text="copied ? @js(__('Copied!')) : @js(__('Copy message'))"></span>
                        </button>

                        <div class="flex items-center gap-2">
                            <button type="button" wire:click="$set('showReminderModal', false)" class="rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-xs font-semibold text-slate-600">{{ __('Close') }}</button>
                            @if ($this->reminderLink)
                                <a href="{{ $this->reminderLink['url'] }}" target="_blank" rel="noopener" class="inline-flex items-center gap-1.5 rounded-xl bg-navy-900 px-4 py-2.5 text-xs font-semibold text-white transition hover:bg-navy-800">
                                    {{ $this->reminderLink['label'] }}
                                    <flux:icon.arrow-top-right-on-square class="size-4" />
                                </a>
                            @endif
                        </div>
                    </div>

                    <div class="flex items-center justify-between gap-3 border-t border-slate-100 pt-3">
                        <p class="text-[11px] leading-relaxed text-slate-400">{{ __('Message is opened in your SMS/WhatsApp/Email app. Review it, then send manually. Nothing is sent automatically.') }}</p>
                        <button type="button" wire:click="markReminderSent" class="inline-flex shrink-0 items-center gap-1.5 rounded-xl bg-emerald-600 px-3.5 py-2.5 text-xs font-semibold text-white transition hover:bg-emerald-700">
                            <flux:icon.check class="size-4" />
                            {{ __('Mark as sent') }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif

    @if ($showCommunicateModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4" x-data="{ copied: false }">
            <div class="absolute inset-0 bg-slate-900/50 backdrop-blur-sm" wire:click="$set('showCommunicateModal', false)"></div>

            <div class="relative flex max-h-[92vh] w-full max-w-3xl flex-col overflow-hidden rounded-2xl bg-white shadow-2xl">
                <div class="flex items-start justify-between gap-3 border-b border-slate-100 p-5">
                    <div>
                        <h3 class="text-base font-semibold">{{ __('Communicate with a borrower') }}</h3>
                        <p class="mt-0.5 text-xs text-slate-400">{{ __('Pick a borrower, edit the message, then open your app. Nothing is sent until you send it yourself.') }}</p>
                    </div>
                    <button type="button" wire:click="$set('showCommunicateModal', false)" class="rounded-lg p-1.5 text-slate-400 hover:bg-slate-100"><flux:icon.x-mark class="size-4" /></button>
                </div>

                <div class="grid overflow-y-auto lg:grid-cols-[280px_1fr]">
                    <div class="border-b border-slate-100 p-4 lg:border-b-0 lg:border-r">
                        <label class="relative block">
                            <flux:icon.magnifying-glass class="pointer-events-none absolute start-3 top-1/2 size-4 -translate-y-1/2 text-slate-400" />
                            <input type="search" wire:model.live.debounce.300ms="commSearch" placeholder="{{ __('Search borrower…') }}" class="w-full rounded-xl border border-slate-200 bg-slate-50 py-2.5 pe-3 ps-9 text-sm text-slate-700 placeholder:text-slate-400 focus:border-blue-500 focus:bg-white focus:outline-none focus:ring-2 focus:ring-blue-500/20" aria-label="{{ __('Search borrowers') }}">
                        </label>

                        <ul class="mt-3 max-h-[52vh] space-y-1.5 overflow-y-auto">
                            @forelse ($this->commBorrowers as $borrower)
                                <li wire:key="comm-borrower-{{ $borrower->id }}">
                                    <button type="button" wire:click="selectCommBorrower({{ $borrower->id }})" class="flex w-full items-center gap-2.5 rounded-xl border px-3 py-2.5 text-start transition {{ $commBorrowerId === $borrower->id ? 'border-navy-900 bg-navy-900/5 ring-1 ring-navy-900/10' : 'border-slate-200 bg-white hover:border-slate-300' }}">
                                        <flux:avatar :name="$borrower->full_name" :initials="str($borrower->full_name)->substr(0, 1)" size="xs" />
                                        <span class="min-w-0 flex-1">
                                            <span class="block truncate text-xs font-semibold text-slate-800">{{ $borrower->full_name }}</span>
                                            <span class="mt-0.5 block truncate text-[10px] tabular-nums text-slate-400">{{ $borrower->mobile }}@if ($borrower->email) · {{ $borrower->email }} @endif</span>
                                        </span>
                                        @if ($commBorrowerId === $borrower->id)
                                            <flux:icon.check class="size-4 shrink-0 text-navy-900" />
                                        @endif
                                    </button>
                                </li>
                            @empty
                                <li class="rounded-xl border border-dashed border-slate-200 px-3 py-6 text-center text-[11px] text-slate-400">{{ __('No borrowers match your search.') }}</li>
                            @endforelse
                        </ul>
                    </div>

                    <div class="p-4 sm:p-5">
                        @if ($this->commBorrower)
                            @php $commBorrower = $this->commBorrower; @endphp
                            <div class="rounded-xl border border-slate-200 bg-slate-50/70 p-3">
                                <p class="text-xs font-semibold text-slate-800">{{ $commBorrower->full_name }} · {{ $commBorrower->borrower_code }}</p>
                                <dl class="mt-1.5 space-y-1 text-[11px] text-slate-500">
                                    <div class="flex justify-between gap-3"><dt>{{ __('Mobile') }}</dt><dd class="font-medium tabular-nums text-slate-700">{{ $commBorrower->mobile }}</dd></div>
                                    <div class="flex justify-between gap-3"><dt>{{ __('Email') }}</dt><dd class="break-all font-medium text-slate-700">{{ $commBorrower->email ?: __('Not on file') }}</dd></div>
                                </dl>
                            </div>

                            <div class="mt-3 grid grid-cols-3 gap-2">
                                @foreach (['whatsapp' => 'WhatsApp', 'sms' => 'SMS', 'email' => 'Email'] as $value => $label)
                                    @php
                                        $disabled = $value === 'email' && blank($commBorrower->email);
                                    @endphp
                                    <label class="flex cursor-pointer flex-col items-center gap-1 rounded-xl border p-2.5 text-center transition {{ $commChannel === $value ? 'border-blue-500 bg-blue-50/50 ring-2 ring-blue-500/20' : 'border-slate-200 hover:border-slate-300' }} {{ $disabled ? 'cursor-not-allowed opacity-40' : '' }}">
                                        <input type="radio" wire:model.live="commChannel" value="{{ $value }}" @disabled($disabled) class="sr-only">
                                        <span class="flex size-8 items-center justify-center rounded-lg {{ $commChannel === $value ? 'bg-blue-600 text-white' : 'bg-slate-100 text-slate-500' }}">
                                            <flux:icon.chat-bubble-left-right class="size-4" />
                                        </span>
                                        <span class="text-[11px] font-semibold text-slate-700">{{ $label }}</span>
                                    </label>
                                @endforeach
                            </div>

                            <div class="mt-3 flex flex-wrap gap-1.5">
                                @foreach ($this->commTemplates as $key => $template)
                                    <button type="button" wire:click="applyCommTemplate('{{ $key }}')" class="rounded-full border border-slate-200 bg-white px-2.5 py-1.5 text-[10px] font-semibold text-slate-600 transition hover:border-slate-300 hover:text-slate-900">{{ $template['label'] }}</button>
                                @endforeach
                            </div>

                            @if ($commChannel === 'email')
                                <div class="mt-3">
                                    <flux:input wire:model="commSubject" :label="__('Subject')" maxlength="255" />
                                </div>
                            @endif

                            <flux:textarea wire:model="commMessage" :label="__('Message')" rows="5" maxlength="1000" class="mt-3" x-ref="commField" />

                            <div class="mt-3 flex flex-wrap items-center justify-between gap-2">
                                <button type="button" x-on:click="navigator.clipboard.writeText($refs.commField.value); copied = true; setTimeout(() => copied = false, 1500)" class="inline-flex items-center gap-1.5 rounded-xl border border-slate-200 bg-white px-3.5 py-2.5 text-xs font-semibold text-slate-600 transition hover:border-slate-300 hover:text-slate-900">
                                    <flux:icon.clipboard-document class="size-4" />
                                    <span x-text="copied ? @js(__('Copied!')) : @js(__('Copy message'))"></span>
                                </button>

                                <div class="flex items-center gap-2">
                                    <button type="button" wire:click="$set('showCommunicateModal', false)" class="rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-xs font-semibold text-slate-600">{{ __('Close') }}</button>
                                    @if ($this->commLink)
                                        <a href="{{ $this->commLink['url'] }}" target="_blank" rel="noopener" class="inline-flex items-center gap-1.5 rounded-xl bg-navy-900 px-4 py-2.5 text-xs font-semibold text-white transition hover:bg-navy-800">
                                            {{ $this->commLink['label'] }}
                                            <flux:icon.arrow-top-right-on-square class="size-4" />
                                        </a>
                                    @endif
                                </div>
                            </div>

                            <p class="mt-3 text-[10px] leading-relaxed text-slate-400">{{ __('Manual communication only — messages open in your own SMS/WhatsApp/Email app and are never sent automatically.') }}</p>
                        @else
                            <div class="flex h-full min-h-[240px] flex-col items-center justify-center rounded-2xl border border-dashed border-slate-200 p-6 text-center">
                                <span class="flex size-12 items-center justify-center rounded-2xl bg-slate-100 text-slate-400"><flux:icon.user class="size-6" /></span>
                                <p class="mt-3 text-sm font-semibold text-slate-600">{{ __('Choose a borrower') }}</p>
                                <p class="mt-1 max-w-xs text-[11px] text-slate-400">{{ __('Search or pick a borrower on the left to view their contact details and compose a message.') }}</p>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    @endif

    @if ($showBulkModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-slate-900/50 backdrop-blur-sm" wire:click="$set('showBulkModal', false)"></div>

            <div class="relative flex max-h-[90vh] w-full max-w-2xl flex-col overflow-hidden rounded-2xl bg-white shadow-2xl">
                <div class="flex items-start justify-between gap-3 border-b border-slate-100 p-5">
                    <div>
                        <h3 class="text-base font-semibold">{{ __('Manual EMI reminders') }}</h3>
                        <p class="mt-0.5 text-xs text-slate-400">{{ __('Open each conversation yourself and review before sending — nothing is sent automatically.') }}</p>
                    </div>
                    <button type="button" wire:click="$set('showBulkModal', false)" class="rounded-lg p-1.5 text-slate-400 hover:bg-slate-100"><flux:icon.x-mark class="size-4" /></button>
                </div>

                <div class="overflow-y-auto p-5">
                    @if (count($this->pendingReminders) === 0)
                        <p class="rounded-xl border border-dashed border-slate-200 px-3 py-8 text-center text-xs text-slate-400">{{ __('No pending EMI reminders for this day.') }}</p>
                    @else
                        <ul class="divide-y divide-slate-100 rounded-xl border border-slate-200">
                            @foreach ($this->pendingReminders as $reminder)
                                <li class="flex flex-wrap items-center gap-3 px-4 py-3" wire:key="bulk-reminder-{{ $reminder['id'] }}">
                                    <div class="min-w-0 flex-1">
                                        <p class="truncate text-xs font-semibold text-slate-800">{{ $reminder['name'] }}</p>
                                        <p class="mt-0.5 truncate text-[11px] text-slate-400">
                                            {{ $reminder['mobile'] ? __('Mobile :mobile', ['mobile' => $reminder['mobile']]) : __('No mobile on file') }}
                                            @if ($reminder['email']) · {{ $reminder['email'] }} @endif
                                        </p>
                                    </div>
                                    <div class="flex shrink-0 items-center gap-1.5">
                                        @if ($reminder['mobile'])
                                            <a href="{{ $reminder['whatsapp'] }}" target="_blank" rel="noopener" aria-label="{{ __('Open WhatsApp') }}" title="{{ __('Open WhatsApp') }}" class="flex size-8 items-center justify-center rounded-lg bg-emerald-50 text-emerald-600 transition hover:bg-emerald-100">
                                                <flux:icon.chat-bubble-left-right class="size-4" />
                                            </a>
                                            <a href="{{ $reminder['sms'] }}" aria-label="{{ __('Open SMS') }}" title="{{ __('Open SMS') }}" class="flex size-8 items-center justify-center rounded-lg bg-blue-50 text-blue-600 transition hover:bg-blue-100">
                                                <flux:icon.device-phone-mobile class="size-4" />
                                            </a>
                                        @endif
                                        @if ($reminder['mailto'])
                                            <a href="{{ $reminder['mailto'] }}" aria-label="{{ __('Open Email') }}" title="{{ __('Open Email') }}" class="flex size-8 items-center justify-center rounded-lg bg-violet-50 text-violet-600 transition hover:bg-violet-100">
                                                <flux:icon.envelope class="size-4" />
                                            </a>
                                        @endif
                                        <button type="button" wire:click="bulkMarkSent({{ $reminder['id'] }})" class="inline-flex items-center gap-1 rounded-lg border border-emerald-200 bg-white px-2.5 py-1.5 text-[10px] font-semibold text-emerald-700 transition hover:bg-emerald-50">
                                            <flux:icon.check class="size-3.5" />
                                            {{ __('Sent') }}
                                        </button>
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>

                <div class="flex items-center justify-end border-t border-slate-100 p-4">
                    <button type="button" wire:click="$set('showBulkModal', false)" class="rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-xs font-semibold text-slate-600">{{ __('Close') }}</button>
                </div>
            </div>
        </div>
    @endif
</div>
