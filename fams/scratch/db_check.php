<?php
$pdo = new PDO('sqlite:fams.db');
$res = $pdo->query("SELECT name FROM sqlite_master WHERE type='table';")->fetchAll(PDO::FETCH_ASSOC);
print_r($res);
