

<?php $srOnly = $srOnly ??= $attributes->pluck('sr-only'); ?>

<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag; ?>
<?php foreach($attributes->onlyProps([
    'srOnly' => null,
]) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
} ?>
<?php $attributes = $attributes->exceptProps([
    'srOnly' => null,
]); ?>
<?php foreach (array_filter(([
    'srOnly' => null,
]), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
} ?>
<?php $__defined_vars = get_defined_vars(); ?>
<?php foreach ($attributes as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
} ?>
<?php unset($__defined_vars); ?>

<?php
$classes = Flux::classes()
    ->add('text-sm text-zinc-500 dark:text-white/60')
    ->add($srOnly ? 'sr-only' : '')
    ;
?>

<ui-description <?php echo e($attributes->class($classes)); ?> data-flux-description>
    <?php echo e($slot); ?>

</ui-description>
<?php /**PATH /home/ploi/dev.localelogic.uk/vendor/livewire/flux/src/../stubs/resources/views/flux/description.blade.php ENDPATH**/ ?>