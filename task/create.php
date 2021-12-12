<?php

$rules = 'CREATE TABLE ' . $_POST['table'];

$columns = [];
foreach ($_POST['column']['name'] as $k => $v) {
    $columns[] = $v . ' ' . $_POST['column']['type'][$k] . (isset($_POST['column']['primary'][$k]) ? ' PRIMARY KEY' : "");
}

if ($columns) {
    $rules .= '(' . implode(', ', $columns) . ')';
}

if ($base->exec($rules)) {
    header('location: ' . $p);
    exit;
}

$_SESSION['alert'] = 'Could not create table "' . $_POST['table'] . '" :(';

header('location: ' . $p);

exit;