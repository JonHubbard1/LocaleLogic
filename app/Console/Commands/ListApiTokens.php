<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class ListApiTokens extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'api:list-tokens {email? : The email address of the user}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'List all API tokens for a user or all users';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $email = $this->argument('email');

        if ($email) {
            // Show tokens for specific user
            $user = User::where('email', $email)->first();

            if (!$user) {
                $this->error("User with email '{$email}' not found.");
                return 1;
            }

            $this->showTokensForUser($user);
        } else {
            // Show tokens for all users
            $users = User::has('tokens')->with('tokens')->get();

            if ($users->isEmpty()) {
                $this->info('No API tokens found.');
                return 0;
            }

            foreach ($users as $user) {
                $this->showTokensForUser($user);
                $this->line('');
            }
        }

        return 0;
    }

    /**
     * Display tokens for a specific user
     */
    private function showTokensForUser(User $user): void
    {
        $tokens = $user->tokens;

        if ($tokens->isEmpty()) {
            $this->info("No tokens found for {$user->name} ({$user->email})");
            return;
        }

        $this->info("{$user->name} ({$user->email})");
        $this->line('');

        $headers = ['ID', 'Name', 'Created', 'Last Used'];
        $rows = [];

        foreach ($tokens as $token) {
            $rows[] = [
                $token->id,
                $token->name,
                $token->created_at->format('Y-m-d H:i'),
                $token->last_used_at ? $token->last_used_at->format('Y-m-d H:i') : 'Never',
            ];
        }

        $this->table($headers, $rows);
    }
}
