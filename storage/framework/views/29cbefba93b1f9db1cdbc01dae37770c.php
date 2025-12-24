

<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag; ?>
<?php foreach($attributes->onlyProps([
    'expandable' => false,
    'expanded' => true,
    'heading' => null,
]) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
} ?>
<?php $attributes = $attributes->exceptProps([
    'expandable' => false,
    'expanded' => true,
    'heading' => null,
]); ?>
<?php foreach (array_filter(([
    'expandable' => false,
    'expanded' => true,
    'heading' => null,
]), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
} ?>
<?php $__defined_vars = get_defined_vars(); ?>
<?php foreach ($attributes as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
} ?>
<?php unset($__defined_vars); ?>

<?php if ($expandable && $heading): ?>
    <ui-disclosure <?php echo e($attributes->class('group/disclosure')); ?> <?php if($expanded === true): ?> open <?php endif; ?> data-flux-navlist-group>
        <button type="button" class="w-full h-10 lg:h-8 flex items-center group/disclosure-button mb-[2px] rounded-lg hover:bg-zinc-800/5 dark:hover:bg-white/[7%] text-zinc-500 hover:text-zinc-800 dark:text-white/80 dark:hover:text-white">
            <div class="ps-3 pe-4">
                <?php if (isset($component)) { $__componentOriginal298ff21bbc41cebb188cbb18c6c11bc0 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal298ff21bbc41cebb188cbb18c6c11bc0 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'ab18b3e58a3b1bb5106ced208a8bd460::icon.chevron-down','data' => ['class' => 'size-3! hidden group-data-open/disclosure-button:block']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? (array) $attributes->getIterator() : [])); ?>
<?php $component->withName('flux::icon.chevron-down'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag && $constructor = (new ReflectionClass(Illuminate\View\AnonymousComponent::class))->getConstructor()): ?>
<?php $attributes = $attributes->except(collect($constructor->getParameters())->map->getName()->all()); ?>
<?php endif; ?>
<?php $component->withAttributes(['class' => 'size-3! hidden group-data-open/disclosure-button:block']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal298ff21bbc41cebb188cbb18c6c11bc0)): ?>
<?php $attributes = $__attributesOriginal298ff21bbc41cebb188cbb18c6c11bc0; ?>
<?php unset($__attributesOriginal298ff21bbc41cebb188cbb18c6c11bc0); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal298ff21bbc41cebb188cbb18c6c11bc0)): ?>
<?php $component = $__componentOriginal298ff21bbc41cebb188cbb18c6c11bc0; ?>
<?php unset($__componentOriginal298ff21bbc41cebb188cbb18c6c11bc0); ?>
<?php endif; ?>
                <?php if (isset($component)) { $__componentOriginal31cb76c8d087d4f00797aeea7232b4c3 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal31cb76c8d087d4f00797aeea7232b4c3 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'ab18b3e58a3b1bb5106ced208a8bd460::icon.chevron-right','data' => ['class' => 'size-3! block group-data-open/disclosure-button:hidden rtl:rotate-180']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? (array) $attributes->getIterator() : [])); ?>
<?php $component->withName('flux::icon.chevron-right'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag && $constructor = (new ReflectionClass(Illuminate\View\AnonymousComponent::class))->getConstructor()): ?>
<?php $attributes = $attributes->except(collect($constructor->getParameters())->map->getName()->all()); ?>
<?php endif; ?>
<?php $component->withAttributes(['class' => 'size-3! block group-data-open/disclosure-button:hidden rtl:rotate-180']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal31cb76c8d087d4f00797aeea7232b4c3)): ?>
<?php $attributes = $__attributesOriginal31cb76c8d087d4f00797aeea7232b4c3; ?>
<?php unset($__attributesOriginal31cb76c8d087d4f00797aeea7232b4c3); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal31cb76c8d087d4f00797aeea7232b4c3)): ?>
<?php $component = $__componentOriginal31cb76c8d087d4f00797aeea7232b4c3; ?>
<?php unset($__componentOriginal31cb76c8d087d4f00797aeea7232b4c3); ?>
<?php endif; ?>
            </div>

            <span class="text-sm font-medium leading-none"><?php echo e($heading); ?></span>
        </button>

        <div class="relative hidden data-open:block space-y-[2px] ps-7" <?php if($expanded === true): ?> data-open <?php endif; ?>>
            <div class="absolute inset-y-[3px] w-px bg-zinc-200 dark:bg-white/30 start-0 ms-4"></div>

            <?php echo e($slot); ?>

        </div>
    </ui-disclosure>
<?php elseif ($heading): ?>
    <div <?php echo e($attributes->class('block space-y-[2px]')); ?>>
        <div class="px-3 py-2">
            <div class="text-sm text-zinc-400 font-medium leading-none"><?php echo e($heading); ?></div>
        </div>

        <div>
            <?php echo e($slot); ?>

        </div>
    </div>
<?php else: ?>
    <div <?php echo e($attributes->class('block space-y-[2px]')); ?>>
        <?php echo e($slot); ?>

    </div>
<?php endif; ?>
<?php /**PATH /home/ploi/dev.localelogic.uk/vendor/livewire/flux/src/../stubs/resources/views/flux/navlist/group.blade.php ENDPATH**/ ?>