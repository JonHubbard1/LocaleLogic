<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class CreateApiToken extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'api:create-token {email : The email address of the user} {--name= : Name for the token (e.g., CaseMate, LeafletApp)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a new API token for a user to access the API from external applications';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $email = $this->argument('email');
        $tokenName = $this->option('name') ?? 'api-token';

        // Find user by email
        $user = User::where('email', $email)->first();

        if (!$user) {
            $this->error("User with email '{$email}' not found.");
            return 1;
        }

        // Create token
        $token = $user->createToken($tokenName)->plainTextToken;

        $this->info("API token created successfully for {$user->name} ({$user->email})");
        $this->line('');
        $this->line("Token Name: {$tokenName}");
        $this->line('Token:');
        $this->line($token);
        $this->line('');
        $this->warn('Store this token securely. It will not be displayed again.');
        $this->line('');
        $this->comment('Use this token in the Authorization header:');
        $this->line('Authorization: Bearer ' . $token);

        return 0;
    }
}
