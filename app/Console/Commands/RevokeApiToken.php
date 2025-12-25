<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Laravel\Sanctum\PersonalAccessToken;

class RevokeApiToken extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'api:revoke-token {tokenId : The ID of the token to revoke}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Revoke an API token by its ID';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $tokenId = $this->argument('tokenId');

        // Find the token
        $token = PersonalAccessToken::find($tokenId);

        if (!$token) {
            $this->error("Token with ID '{$tokenId}' not found.");
            return 1;
        }

        // Get token details before deleting
        $tokenName = $token->name;
        $user = User::find($token->tokenable_id);
        $userName = $user ? $user->name : 'Unknown User';
        $userEmail = $user ? $user->email : 'Unknown';

        // Confirm deletion
        if (!$this->confirm("Revoke token '{$tokenName}' for {$userName} ({$userEmail})?", true)) {
            $this->info('Token revocation cancelled.');
            return 0;
        }

        // Delete the token
        $token->delete();

        $this->info("Token '{$tokenName}' has been revoked successfully.");
        $this->warn("Applications using this token will no longer be able to access the API.");

        return 0;
    }
}
