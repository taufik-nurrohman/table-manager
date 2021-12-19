<?php

if (isset($_POST['drop'])) {
    if (Base::drop($_POST['drop'])) {
        $_SESSION['alert'] = 'Dropped table <code>' . $_POST['drop'] . '</code>.';
        header('location: ' . $p);
        exit;
    }
    $_SESSION['alert'] = 'Could not drop table <code>' . $_POST['drop'] . '</code>.';
    $_SESSION['alert'] .= '<br><b>DEBUG:</b> ' . Base::$error;
    header('location: ' . $p);
    exit;
}

$keys = [];
foreach ($_POST['keys']['name'] as $k => $v) {
    $rule = "";
    if (isset($_POST['keys']['auto-increment'][$k])) {
        $rule .= 'AUTOINCREMENT';
    }
    if (isset($_POST['keys']['primary-key'][$k])) {
        $rule .= ' PRIMARY KEY';
    }
    if (isset($_POST['keys']['unique'][$k])) {
        $rule .= ' UNIQUE';
    }
    if (isset($_POST['keys']['not-null'][$k])) {
        $rule .= ' NOT NULL';
    }
    $keys[$v] = trim(($_POST['keys']['type'][$k] ?? 'TEXT') . ' ' . $rule);
}

if (Base::create($_POST['table'], $keys)) {
    $_SESSION['alert'] = 'Created table <code>' . $_POST['table'] . '</code>.';
    header('location: ' . $p);
    exit;
}

$_SESSION['alert'] = 'Could not create table <code>' . $_POST['table'] . '</code>.';
$_SESSION['alert'] .= '<br><b>DEBUG:</b> ' . Base::$error;
header('location: ' . $p);
exit;