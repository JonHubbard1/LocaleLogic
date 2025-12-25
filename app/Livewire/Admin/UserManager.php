<?php

namespace App\Livewire\Admin;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
#[Title('User Management')]
class UserManager extends Component
{
    use WithPagination;

    public $showCreateModal = false;
    public $showEditModal = false;
    public $showDeleteModal = false;

    public $userId = null;
    public $name = '';
    public $email = '';
    public $password = '';
    public $password_confirmation = '';

    public $userToDelete = null;

    public function createUser()
    {
        $this->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:8|confirmed',
        ]);

        User::create([
            'name' => $this->name,
            'email' => $this->email,
            'password' => Hash::make($this->password),
        ]);

        $this->closeCreateModal();
        session()->flash('message', 'User created successfully.');
    }

    public function editUser($userId)
    {
        $user = User::findOrFail($userId);
        $this->userId = $user->id;
        $this->name = $user->name;
        $this->email = $user->email;
        $this->showEditModal = true;
    }

    public function updateUser()
    {
        $this->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email,' . $this->userId,
            'password' => 'nullable|string|min:8|confirmed',
        ]);

        $user = User::findOrFail($this->userId);
        $user->name = $this->name;
        $user->email = $this->email;

        if ($this->password) {
            $user->password = Hash::make($this->password);
        }

        $user->save();

        $this->closeEditModal();
        session()->flash('message', 'User updated successfully.');
    }

    public function confirmDelete($userId)
    {
        $this->userToDelete = User::with('tokens')->findOrFail($userId);
        $this->showDeleteModal = true;
    }

    public function deleteUser()
    {
        if ($this->userToDelete) {
            // Revoke all tokens first
            $this->userToDelete->tokens()->delete();

            // Delete the user
            $this->userToDelete->delete();

            $this->closeDeleteModal();
            session()->flash('message', 'User deleted successfully.');
        }
    }

    public function closeCreateModal()
    {
        $this->showCreateModal = false;
        $this->reset(['name', 'email', 'password', 'password_confirmation']);
    }

    public function closeEditModal()
    {
        $this->showEditModal = false;
        $this->reset(['userId', 'name', 'email', 'password', 'password_confirmation']);
    }

    public function closeDeleteModal()
    {
        $this->showDeleteModal = false;
        $this->userToDelete = null;
    }

    public function render()
    {
        $users = User::withCount('tokens')
            ->orderBy('created_at', 'desc')
            ->paginate(15);

        return view('livewire.admin.user-manager', [
            'users' => $users,
        ]);
    }
}
