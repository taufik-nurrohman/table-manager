<?php

$rules = 'CREATE TABLE ' . $_POST['table'];

$columns = [];
foreach ($_POST['column']['name'] as $k => $v) {
    $rule = $v . ' ' . $_POST['column']['type'][$k];
    if (isset($_POST['column']['primary'][$k])) {
        $rule .= ' PRIMARY KEY';
    }
    if (isset($_POST['column']['increment'][$k])) {
        $rule .= ' AUTOINCREMENT';
    }
    if (isset($_POST['column']['vital'][$k])) {
        $rule .= ' NOT NULL';
    }
    $columns[] = $rule;
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