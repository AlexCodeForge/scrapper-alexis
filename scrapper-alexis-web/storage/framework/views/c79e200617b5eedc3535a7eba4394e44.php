<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag;

$__newAttributes = [];
$__propNames = \Illuminate\View\ComponentAttributeBag::extractPropNames(([
    'orientation' => 'horizontal',
    'decorative' => true,
]));

foreach ($attributes->all() as $__key => $__value) {
    if (in_array($__key, $__propNames)) {
        $$__key = $$__key ?? $__value;
    } else {
        $__newAttributes[$__key] = $__value;
    }
}

$attributes = new \Illuminate\View\ComponentAttributeBag($__newAttributes);

unset($__propNames);
unset($__newAttributes);

foreach (array_filter(([
    'orientation' => 'horizontal',
    'decorative' => true,
]), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
}

$__defined_vars = get_defined_vars();

foreach ($attributes->all() as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
}

unset($__defined_vars, $__key, $__value); ?>
<div
    <?php if(!$decorative): ?> aria-orientation="<?php echo e($orientation); ?>"
        role="separator" <?php endif; ?>
    <?php echo e($attributes->twMerge([
        'shrink-0 bg-border',
        'h-px w-full' => $orientation == 'horizontal',
        'h-full w-px' => $orientation == 'vertical',
    ])); ?>

>
</div>
<?php /**PATH /var/www/alexis-scrapper-docker/scrapper-alexis-web/resources/views/components/separator/index.blade.php ENDPATH**/ ?>