<?php

namespace App\Actions\Fortify;

use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Mail\WelcomeMail;
use App\Models\User;
use App\Services\EmailNotificationService;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules, ProfileValidationRules;

    /**
     * Validate and create a newly registered user.
     *
     * @param  array<string, string>  $input
     * @return \App\Models\User
     */
    public function create(array $input): User
    {
        Validator::make($input, [
            ...$this->profileRules(),
            'password' => $this->passwordRules(),
        ])->validate();

        $user = User::create([
            'name' => $input['name'],
            'email' => $input['email'],
            'password' => $input['password'],
        ]);

        // Send welcome email asynchronously via the centralized service
        // Using sync delivery to ensure the email is sent after the transaction commits
        // In production, consider dispatching to the queue instead
        try {
            // Use the service to send welcome email
            $service = app(EmailNotificationService::class);
            $service->welcome($user, false); // false = sync, not queued
        } catch (\Throwable $e) {
            // Log the failure but don't block user registration
            Log::warning('Failed to send welcome email during registration: '.$e->getMessage());
        }

        return $user;
    }
}
