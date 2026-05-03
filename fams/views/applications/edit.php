<?php require __DIR__ . '/../layout/header.php'; ?>
<?php require __DIR__ . '/../layout/sidebar.php'; ?>
<?php
$isEdit = true;
$d = array_merge((array)($applicant??[]), (array)($app??[]), $_POST);
$dependants = $dependants ?? [];
?>
<?php require __DIR__ . '/_form.php'; ?>
<?php require __DIR__ . '/../layout/footer.php'; ?>
