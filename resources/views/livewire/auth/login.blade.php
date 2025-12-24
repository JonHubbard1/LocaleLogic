<div class="flex min-h-screen items-center justify-center bg-gray-50 dark:bg-gray-900">
    <flux:card class="w-full max-w-md">
        <flux:heading>Welcome to LocaleLogic</flux:heading>
        <flux:subheading>Sign in to your account</flux:subheading>

        <form wire:submit="login" class="mt-6 space-y-6">
            <flux:field>
                <flux:label>Email</flux:label>
                <flux:input wire:model="email" type="email" placeholder="your@email.com" />
                <flux:error name="email" />
            </flux:field>

            <flux:field>
                <flux:label>Password</flux:label>
                <flux:input wire:model="password" type="password" />
                <flux:error name="password" />
            </flux:field>

            <flux:checkbox wire:model="remember">Remember me</flux:checkbox>

            <flux:button type="submit" variant="primary" class="w-full">Sign in</flux:button>
        </form>
    </flux:card>
</div>
