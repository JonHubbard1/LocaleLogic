<?php

namespace App\Livewire\Admin;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\PersonalAccessToken;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
#[Title('API Token Management')]
class ApiTokenManager extends Component
{
    use WithPagination;

    public $showCreateModal = false;
    public $showRevokeModal = false;
    public $selectedUser = null;
    public $tokenName = '';
    public $newTokenValue = null;
    public $tokenToRevoke = null;

    public function createToken()
    {
        $this->validate([
            'selectedUser' => 'required|exists:users,id',
            'tokenName' => 'required|string|min:3|max:50',
        ]);

        $user = User::find($this->selectedUser);
        $token = $user->createToken($this->tokenName);

        // Store the plain text token to show to the user
        $this->newTokenValue = $token->plainTextToken;

        // Reset form but keep modal open to show token
        $this->selectedUser = null;
        $this->tokenName = '';
    }

    public function confirmRevoke($tokenId)
    {
        $this->tokenToRevoke = PersonalAccessToken::with('tokenable')->find($tokenId);
        $this->showRevokeModal = true;
    }

    public function revokeToken()
    {
        if ($this->tokenToRevoke) {
            $this->tokenToRevoke->delete();
            $this->showRevokeModal = false;
            $this->tokenToRevoke = null;

            session()->flash('message', 'API token revoked successfully.');
        }
    }

    public function closeCreateModal()
    {
        $this->showCreateModal = false;
        $this->newTokenValue = null;
        $this->selectedUser = null;
        $this->tokenName = '';
    }

    public function closeRevokeModal()
    {
        $this->showRevokeModal = false;
        $this->tokenToRevoke = null;
    }

    public function render()
    {
        $tokens = PersonalAccessToken::with('tokenable')
            ->select('personal_access_tokens.*')
            ->join('users', 'users.id', '=', 'personal_access_tokens.tokenable_id')
            ->where('personal_access_tokens.tokenable_type', User::class)
            ->orderBy('personal_access_tokens.created_at', 'desc')
            ->paginate(15);

        $users = User::orderBy('name')->get();

        return view('livewire.admin.api-token-manager', [
            'tokens' => $tokens,
            'users' => $users,
        ]);
    }
}
