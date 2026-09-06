<?php
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo e($page_title); ?> — <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/style.css">
    <?php if ($role_css !== ''): ?>
        <link rel="stylesheet" href="<?php echo BASE_URL . 'assets/css/' . e($role_css); ?>.css">
    <?php endif; ?>
</head>

<body class="<?php echo e(trim(($role_css !== '' ? 'role-' . $role_css . ' ' : '') . $body_class)); ?>">
<?php
if (empty($bare)) {
    require __DIR__ . '/navbar.php';
}
?>
