

<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag; ?>
<?php foreach($attributes->onlyProps([
    'variant' => null,
]) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
} ?>
<?php $attributes = $attributes->exceptProps([
    'variant' => null,
]); ?>
<?php foreach (array_filter(([
    'variant' => null,
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
    ->add('flex flex-col')
    ->add('overflow-visible min-h-auto')
    ;
?>

<nav <?php echo e($attributes->class($classes)); ?> data-flux-navlist>
    <?php echo e($slot); ?>

</nav>
<?php /**PATH /home/ploi/dev.localelogic.uk/vendor/livewire/flux/src/../stubs/resources/views/flux/navlist/index.blade.php ENDPATH**/ ?>