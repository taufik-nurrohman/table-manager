<?php

if (isset($_POST['drop'])) {
    $rules = "DROP TABLE " . $_POST['drop'];
    if ($base->exec($rules)) {
        $_SESSION['alert'] = 'Dropped table <code>' . $_POST['drop'] . '</code>.';
        header('location: ' . $p);
        exit;
    }
    $_SESSION['alert'] = 'Could not drop table <code>' . $_POST['drop'] . '</code>.';
    $_SESSION['alert'] .= '<br><b>DEBUG:</b> ' . $base->lastErrorMsg();
    header('location: ' . $p);
    exit;
}

$rules = 'CREATE TABLE ' . $_POST['table'];

$keys = [];
foreach ($_POST['keys']['name'] as $k => $v) {
    $rule = $v . ' ' . $_POST['keys']['type'][$k];
    if (isset($_POST['keys']['primary-key'][$k])) {
        $rule .= ' PRIMARY KEY';
    }
    if (isset($_POST['keys']['auto-increment'][$k])) {
        $rule .= ' AUTOINCREMENT';
    }
    if (isset($_POST['keys']['not-null'][$k])) {
        $rule .= ' NOT NULL';
    }
    $keys[] = $rule;
}

if (!empty($keys)) {
    $rules .= '(' . implode(', ', $keys) . ')';
}

if ($base->exec($rules)) {
    $_SESSION['alert'] = 'Created table <code>' . $_POST['table'] . '</code>.';
    header('location: ' . $p);
    exit;
}

$_SESSION['alert'] = 'Could not create table <code>' . $_POST['table'] . '</code>.';
$_SESSION['alert'] .= '<br><b>DEBUG:</b> ' . $base->lastErrorMsg();
header('location: ' . $p);
exit;