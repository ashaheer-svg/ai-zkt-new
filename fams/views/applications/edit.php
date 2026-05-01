<?php require __DIR__ . '/../layout/header.php'; ?>
<?php require __DIR__ . '/../layout/sidebar.php'; ?>
<?php
$isEdit = true;
// Pre-fill $d from existing applicant record
$d = array_merge((array)$applicant, (array)$app);
?>
<?php require __DIR__ . '/_form.php'; ?>
<?php require __DIR__ . '/../layout/footer.php'; ?>
