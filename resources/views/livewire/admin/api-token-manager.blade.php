<div class="py-12">
    <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
        <div class="flex items-center justify-between mb-6">
            <div>
                <flux:heading size="xl">API Token Management</flux:heading>
                <flux:subheading>Manage API access tokens for external applications</flux:subheading>
            </div>
            <flux:button wire:click="$set('showCreateModal', true)" variant="primary" icon="plus">
                Create New Token
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

        {{-- Tokens Table --}}
        <flux:card>
            @if($tokens->count() > 0)
                <div class="overflow-x-auto">
                    <table class="w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead>
                            <tr class="bg-gray-50 dark:bg-gray-800">
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Token Name</th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">User</th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Created</th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Last Used</th>
                                <th class="px-6 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700 bg-white dark:bg-gray-900">
                            @foreach ($tokens as $token)
                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-800">
                                    <td class="px-6 py-4 text-sm">
                                        <div class="flex items-center gap-2">
                                            <flux:icon.key class="h-4 w-4 text-gray-400" />
                                            <span class="font-medium text-gray-900 dark:text-gray-100">{{ $token->name }}</span>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 text-sm">
                                        <div>
                                            <div class="font-medium text-gray-900 dark:text-gray-100">
                                                {{ $token->tokenable->name }}
                                            </div>
                                            <div class="text-gray-500 dark:text-gray-400">
                                                {{ $token->tokenable->email }}
                                            </div>
                                        </div>
                                    </td>
                                    <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-500 dark:text-gray-400">
                                        <div>{{ $token->created_at->format('d/m/Y') }}</div>
                                        <div class="text-gray-400 dark:text-gray-500">{{ $token->created_at->format('H:i') }}</div>
                                    </td>
                                    <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-500 dark:text-gray-400">
                                        @if($token->last_used_at)
                                            <div>{{ $token->last_used_at->format('d/m/Y') }}</div>
                                            <div class="text-gray-400 dark:text-gray-500">{{ $token->last_used_at->format('H:i') }}</div>
                                        @else
                                            <span class="text-gray-400 dark:text-gray-500">Never</span>
                                        @endif
                                    </td>
                                    <td class="whitespace-nowrap px-6 py-4 text-right text-sm">
                                        <flux:button wire:click="confirmRevoke({{ $token->id }})" variant="danger" size="sm" icon="trash">
                                            Revoke
                                        </flux:button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                {{-- Pagination --}}
                @if($tokens->hasPages())
                    <div class="mt-6">
                        {{ $tokens->links() }}
                    </div>
                @endif
            @else
                <div class="py-12 text-center">
                    <flux:icon.key class="mx-auto h-12 w-12 text-gray-400" />
                    <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">No API tokens found</p>
                    <p class="text-xs text-gray-500 dark:text-gray-500">Create a new token to get started</p>
                </div>
            @endif
        </flux:card>
    </div>

    {{-- Create Token Modal --}}
    <flux:modal wire:model="showCreateModal" class="max-w-md">
        <form wire:submit="createToken">
            <flux:heading size="lg">
                @if($newTokenValue)
                    API Token Created
                @else
                    Create New API Token
                @endif
            </flux:heading>

            @if($newTokenValue)
                {{-- Show the newly created token --}}
                <div class="mt-6 space-y-4">
                    <div class="rounded-lg bg-green-50 p-4 dark:bg-green-900/20">
                        <div class="flex items-start gap-3">
                            <flux:icon.check-circle class="h-5 w-5 text-green-600 dark:text-green-400 flex-shrink-0 mt-0.5" />
                            <div class="text-sm text-green-800 dark:text-green-200">
                                Token created successfully! Copy it now - it won't be shown again.
                            </div>
                        </div>
                    </div>

                    <div>
                        <flux:label>Your New Token:</flux:label>
                        <div class="mt-2 rounded-lg bg-gray-900 p-4">
                            <code class="block break-all text-sm text-green-400 font-mono">{{ $newTokenValue }}</code>
                        </div>
                        <flux:description>Store this token securely in your application's environment file.</flux:description>
                    </div>

                    <div class="rounded-lg bg-yellow-50 p-4 dark:bg-yellow-900/20">
                        <div class="flex items-start gap-3">
                            <flux:icon.exclamation-triangle class="h-5 w-5 text-yellow-600 dark:text-yellow-400 flex-shrink-0 mt-0.5" />
                            <div class="text-sm text-yellow-800 dark:text-yellow-200">
                                This token will only be displayed once. Make sure to copy it before closing this dialog.
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mt-6 flex justify-end">
                    <flux:button wire:click="closeCreateModal" variant="primary">
                        Done
                    </flux:button>
                </div>
            @else
                {{-- Create token form --}}
                <div class="mt-6 space-y-6">
                    <flux:select wire:model="selectedUser" label="User" placeholder="Select a user...">
                        @foreach($users as $user)
                            <option value="{{ $user->id }}">{{ $user->name }} ({{ $user->email }})</option>
                        @endforeach
                    </flux:select>

                    <flux:input
                        wire:model="tokenName"
                        label="Token Name"
                        placeholder="e.g., CaseMate, LeafletApp"
                        description="A descriptive name to identify this token"
                    />
                </div>

                <div class="mt-6 flex gap-3 justify-end">
                    <flux:button wire:click="closeCreateModal" variant="ghost">
                        Cancel
                    </flux:button>
                    <flux:button type="submit" variant="primary">
                        Create Token
                    </flux:button>
                </div>
            @endif
        </form>
    </flux:modal>

    {{-- Revoke Token Modal --}}
    <flux:modal wire:model="showRevokeModal" class="max-w-md">
        <flux:heading size="lg">Revoke API Token</flux:heading>

        @if($tokenToRevoke)
            <div class="mt-6 space-y-4">
                <div class="rounded-lg bg-red-50 p-4 dark:bg-red-900/20">
                    <div class="flex items-start gap-3">
                        <flux:icon.exclamation-triangle class="h-5 w-5 text-red-600 dark:text-red-400 flex-shrink-0 mt-0.5" />
                        <div class="text-sm text-red-800 dark:text-red-200">
                            This action cannot be undone. Applications using this token will immediately lose API access.
                        </div>
                    </div>
                </div>

                <div class="bg-gray-50 dark:bg-gray-900 rounded-lg p-4 space-y-2">
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-600 dark:text-gray-400">Token Name:</span>
                        <span class="font-medium">{{ $tokenToRevoke->name }}</span>
                    </div>
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-600 dark:text-gray-400">User:</span>
                        <span class="font-medium">{{ $tokenToRevoke->tokenable->name }}</span>
                    </div>
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-600 dark:text-gray-400">Created:</span>
                        <span class="font-medium">{{ $tokenToRevoke->created_at->format('d/m/Y H:i') }}</span>
                    </div>
                </div>

                <p class="text-sm text-gray-700 dark:text-gray-300">
                    Are you sure you want to revoke this token?
                </p>
            </div>

            <div class="mt-6 flex gap-3 justify-end">
                <flux:button wire:click="closeRevokeModal" variant="ghost">
                    Cancel
                </flux:button>
                <flux:button wire:click="revokeToken" variant="danger">
                    Revoke Token
                </flux:button>
            </div>
        @endif
    </flux:modal>
</div>
