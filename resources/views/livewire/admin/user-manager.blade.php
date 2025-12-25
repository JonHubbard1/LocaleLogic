<div class="py-12">
    <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
        <div class="flex items-center justify-between mb-6">
            <div>
                <flux:heading size="xl">User Management</flux:heading>
                <flux:subheading>Manage user accounts and permissions</flux:subheading>
            </div>
            <flux:button wire:click="$set('showCreateModal', true)" variant="primary" icon="user-plus">
                Create New User
            </flux:button>
        </div>

        {{-- Success Message --}}
        @if (session()->has('message'))
            <div class="mb-6 rounded-lg bg-green-50 p-4 dark:bg-green-900/20">
                <div class="flex items-start gap-3">
                    <flux:icon.check-circle class="h-5 w-5 text-green-600 dark:text-green-400 flex-shrink-0 mt-0.5" />
                    <div class="text-sm text-green-800 dark:text-green-200">{{ session('message') }}</div>
                </div>
            </div>
        @endif>

        {{-- Users Table --}}
        <flux:card>
            @if($users->count() > 0)
                <div class="overflow-x-auto">
                    <table class="w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead>
                            <tr class="bg-gray-50 dark:bg-gray-800">
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Name</th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Email</th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">API Tokens</th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Created</th>
                                <th class="px-6 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700 bg-white dark:bg-gray-900">
                            @foreach ($users as $user)
                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-800">
                                    <td class="px-6 py-4 text-sm">
                                        <div class="flex items-center gap-2">
                                            <flux:icon.user class="h-4 w-4 text-gray-400" />
                                            <span class="font-medium text-gray-900 dark:text-gray-100">{{ $user->name }}</span>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-600 dark:text-gray-400">
                                        {{ $user->email }}
                                    </td>
                                    <td class="px-6 py-4 text-sm">
                                        @if($user->tokens_count > 0)
                                            <span class="inline-flex items-center rounded-md bg-blue-50 dark:bg-blue-900/20 px-2 py-1 text-xs font-medium text-blue-700 dark:text-blue-400 ring-1 ring-inset ring-blue-600/20 dark:ring-blue-500/20">
                                                {{ $user->tokens_count }} {{ $user->tokens_count === 1 ? 'token' : 'tokens' }}
                                            </span>
                                        @else
                                            <span class="text-gray-400 dark:text-gray-500">No tokens</span>
                                        @endif
                                    </td>
                                    <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-500 dark:text-gray-400">
                                        <div>{{ $user->created_at->format('d/m/Y') }}</div>
                                        <div class="text-gray-400 dark:text-gray-500">{{ $user->created_at->format('H:i') }}</div>
                                    </td>
                                    <td class="whitespace-nowrap px-6 py-4 text-right text-sm">
                                        <div class="flex gap-2 justify-end">
                                            <flux:button wire:click="editUser({{ $user->id }})" variant="ghost" size="sm" icon="pencil">
                                                Edit
                                            </flux:button>
                                            <flux:button wire:click="confirmDelete({{ $user->id }})" variant="danger" size="sm" icon="trash">
                                                Delete
                                            </flux:button>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                {{-- Pagination --}}
                @if($users->hasPages())
                    <div class="mt-6">
                        {{ $users->links() }}
                    </div>
                @endif
            @else
                <div class="py-12 text-center">
                    <flux:icon.user class="mx-auto h-12 w-12 text-gray-400" />
                    <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">No users found</p>
                </div>
            @endif
        </flux:card>
    </div>

    {{-- Create User Modal --}}
    <flux:modal wire:model="showCreateModal" class="max-w-md">
        <form wire:submit="createUser">
            <flux:heading size="lg">Create New User</flux:heading>

            <div class="mt-6 space-y-6">
                <flux:input
                    wire:model="name"
                    label="Name"
                    placeholder="John Smith"
                    required
                />

                <flux:input
                    wire:model="email"
                    label="Email"
                    type="email"
                    placeholder="john@example.com"
                    required
                />

                <flux:input
                    wire:model="password"
                    label="Password"
                    type="password"
                    placeholder="••••••••"
                    required
                />

                <flux:input
                    wire:model="password_confirmation"
                    label="Confirm Password"
                    type="password"
                    placeholder="••••••••"
                    required
                />
            </div>

            <div class="mt-6 flex gap-3 justify-end">
                <flux:button wire:click="closeCreateModal" variant="ghost">
                    Cancel
                </flux:button>
                <flux:button type="submit" variant="primary">
                    Create User
                </flux:button>
            </div>
        </form>
    </flux:modal>

    {{-- Edit User Modal --}}
    <flux:modal wire:model="showEditModal" class="max-w-md">
        <form wire:submit="updateUser">
            <flux:heading size="lg">Edit User</flux:heading>

            <div class="mt-6 space-y-6">
                <flux:input
                    wire:model="name"
                    label="Name"
                    placeholder="John Smith"
                    required
                />

                <flux:input
                    wire:model="email"
                    label="Email"
                    type="email"
                    placeholder="john@example.com"
                    required
                />

                <flux:input
                    wire:model="password"
                    label="New Password"
                    type="password"
                    placeholder="••••••••"
                    description="Leave blank to keep current password"
                />

                <flux:input
                    wire:model="password_confirmation"
                    label="Confirm New Password"
                    type="password"
                    placeholder="••••••••"
                />
            </div>

            <div class="mt-6 flex gap-3 justify-end">
                <flux:button wire:click="closeEditModal" variant="ghost">
                    Cancel
                </flux:button>
                <flux:button type="submit" variant="primary">
                    Update User
                </flux:button>
            </div>
        </form>
    </flux:modal>

    {{-- Delete User Modal --}}
    <flux:modal wire:model="showDeleteModal" class="max-w-md">
        <flux:heading size="lg">Delete User</flux:heading>

        @if($userToDelete)
            <div class="mt-6 space-y-4">
                <div class="rounded-lg bg-red-50 p-4 dark:bg-red-900/20">
                    <div class="flex items-start gap-3">
                        <flux:icon.exclamation-triangle class="h-5 w-5 text-red-600 dark:text-red-400 flex-shrink-0 mt-0.5" />
                        <div class="text-sm text-red-800 dark:text-red-200">
                            This action cannot be undone. This will permanently delete the user account and revoke all associated API tokens.
                        </div>
                    </div>
                </div>

                <div class="bg-gray-50 dark:bg-gray-900 rounded-lg p-4 space-y-2">
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-600 dark:text-gray-400">Name:</span>
                        <span class="font-medium">{{ $userToDelete->name }}</span>
                    </div>
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-600 dark:text-gray-400">Email:</span>
                        <span class="font-medium">{{ $userToDelete->email }}</span>
                    </div>
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-600 dark:text-gray-400">API Tokens:</span>
                        <span class="font-medium">{{ $userToDelete->tokens->count() }}</span>
                    </div>
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-600 dark:text-gray-400">Created:</span>
                        <span class="font-medium">{{ $userToDelete->created_at->format('d/m/Y H:i') }}</span>
                    </div>
                </div>

                @if($userToDelete->tokens->count() > 0)
                    <div class="rounded-lg bg-yellow-50 p-4 dark:bg-yellow-900/20">
                        <div class="flex items-start gap-3">
                            <flux:icon.exclamation-triangle class="h-5 w-5 text-yellow-600 dark:text-yellow-400 flex-shrink-0 mt-0.5" />
                            <div class="text-sm text-yellow-800 dark:text-yellow-200">
                                This user has {{ $userToDelete->tokens->count() }} active API {{ $userToDelete->tokens->count() === 1 ? 'token' : 'tokens' }} that will be revoked.
                            </div>
                        </div>
                    </div>
                @endif

                <p class="text-sm text-gray-700 dark:text-gray-300">
                    Are you sure you want to delete this user?
                </p>
            </div>

            <div class="mt-6 flex gap-3 justify-end">
                <flux:button wire:click="closeDeleteModal" variant="ghost">
                    Cancel
                </flux:button>
                <flux:button wire:click="deleteUser" variant="danger">
                    Delete User
                </flux:button>
            </div>
        @endif
    </flux:modal>
</div>
