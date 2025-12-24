

<?php foreach (([ 'variant' ]) as $__key => $__value) {
    $__consumeVariable = is_string($__key) ? $__key : $__value;
    $$__consumeVariable = is_string($__key) ? $__env->getConsumableComponentData($__key, $__value) : $__env->getConsumableComponentData($__value);
} ?>

<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag; ?>
<?php foreach($attributes->onlyProps([
    'variant' => 'default',
]) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
} ?>
<?php $attributes = $attributes->exceptProps([
    'variant' => 'default',
]); ?>
<?php foreach (array_filter(([
    'variant' => 'default',
]), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
} ?>
<?php $__defined_vars = get_defined_vars(); ?>
<?php foreach ($attributes as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
} ?>
<?php unset($__defined_vars); ?>

<?php
// This prevents variants picked up by `@aware()` from other wrapping components like flux::modal from being used here...
$variant = $variant !== 'default' && Flux::componentExists('checkbox.variants.' . $variant)
    ? $variant
    : 'default';
?>

<?php if (!Flux::componentExists($name = 'checkbox.variants.' . $variant)) throw new \Exception("Flux component [{$name}] does not exist."); ?><?php if (isset($component)) { $__componentOriginale96b33a22b0abf65a3b62dc1a6ecf912 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginale96b33a22b0abf65a3b62dc1a6ecf912 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve([
    'view' => (app()->version() >= 12 ? hash('xxh128', 'flux') : md5('flux')) . '::' . 'checkbox.variants.' . $variant,
    'data' => $__env->getCurrentComponentData(),
] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? (array) $attributes->getIterator() : [])); ?>
<?php $component->withName('flux::' . 'checkbox.variants.' . $variant); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php $component->withAttributes($attributes->getAttributes()); ?><?php echo e($slot); ?> <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginale96b33a22b0abf65a3b62dc1a6ecf912)): ?>
<?php $attributes = $__attributesOriginale96b33a22b0abf65a3b62dc1a6ecf912; ?>
<?php unset($__attributesOriginale96b33a22b0abf65a3b62dc1a6ecf912); ?>
<?php endif; ?>
<?php if (isset($__componentOriginale96b33a22b0abf65a3b62dc1a6ecf912)): ?>
<?php $component = $__componentOriginale96b33a22b0abf65a3b62dc1a6ecf912; ?>
<?php unset($__componentOriginale96b33a22b0abf65a3b62dc1a6ecf912); ?>
<?php endif; ?>
<?php /**PATH /home/ploi/dev.localelogic.uk/vendor/livewire/flux/src/../stubs/resources/views/flux/checkbox/index.blade.php ENDPATH**/ ?>