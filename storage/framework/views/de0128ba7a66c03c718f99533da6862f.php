



<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag; ?>
<?php foreach($attributes->onlyProps([
    'icon' => null,
    'name' => null,
]) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
} ?>
<?php $attributes = $attributes->exceptProps([
    'icon' => null,
    'name' => null,
]); ?>
<?php foreach (array_filter(([
    'icon' => null,
    'name' => null,
]), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
} ?>
<?php $__defined_vars = get_defined_vars(); ?>
<?php foreach ($attributes as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
} ?>
<?php unset($__defined_vars); ?>

<?php
$icon = $name ?? $icon;
?>

<?php if (!Flux::componentExists($name = 'icon.' . $icon)) throw new \Exception("Flux component [{$name}] does not exist."); ?><?php if (isset($component)) { $__componentOriginal99f5bdde02e072cb5fe2c95dd124b389 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal99f5bdde02e072cb5fe2c95dd124b389 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve([
    'view' => (app()->version() >= 12 ? hash('xxh128', 'flux') : md5('flux')) . '::' . 'icon.' . $icon,
    'data' => $__env->getCurrentComponentData(),
] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? (array) $attributes->getIterator() : [])); ?>
<?php $component->withName('flux::' . 'icon.' . $icon); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php $component->withAttributes($attributes->getAttributes()); ?><?php echo e($slot); ?> <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal99f5bdde02e072cb5fe2c95dd124b389)): ?>
<?php $attributes = $__attributesOriginal99f5bdde02e072cb5fe2c95dd124b389; ?>
<?php unset($__attributesOriginal99f5bdde02e072cb5fe2c95dd124b389); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal99f5bdde02e072cb5fe2c95dd124b389)): ?>
<?php $component = $__componentOriginal99f5bdde02e072cb5fe2c95dd124b389; ?>
<?php unset($__componentOriginal99f5bdde02e072cb5fe2c95dd124b389); ?>
<?php endif; ?>
<?php /**PATH /home/ploi/dev.localelogic.uk/vendor/livewire/flux/src/../stubs/resources/views/flux/icon/index.blade.php ENDPATH**/ ?>