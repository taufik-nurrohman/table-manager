<?php

if (isset($_POST['delete'])) {
    if (Base::delete($_POST['table'], (int) $_POST['delete'])) {
        $_SESSION['alert'] = 'Deleted 1 row in table <code>' . $_POST['table'] . '</code>.';
        header('location: ' . $p . '?table=' . $_POST['table'] . '&task=update');
        exit;
    }
    $_SESSION['alert'] = 'Could not delete row in table <code>' . $_POST['table'] . '</code>.';
    $_SESSION['alert'] .= '<br><b>DEBUG:</b> ' . Base::$error;
    header('location: ' . $p . '?table=' . $_POST['table'] . '&task=update');
    exit;
}

$values = [];
if (!empty($_POST['values'])) {
    foreach ($_POST['values'] as $k => $v) {
        if (isset($_FILES['values']['name'][$k])) {
            continue;
        }
        $values[$k] = $v;
    }
}

if (!empty($_FILES['values'])) {
    foreach ($_FILES['values']['name'] as $k => $v) {
        if (!empty($_FILES['values']['error'][$k])) {
            continue;
        }
        $values[$k] = 'data:' . $_FILES['values']['type'][$k] . ',' . base64_encode(file_get_contents($_FILES['values']['tmp_name'][$k]));
    }
}

if (Base::insert($_POST['table'], $values)) {
    $_SESSION['alert'] = 'Inserted 1 row to table <code>' . $_POST['table'] . '</code>.';
    header('location: ' . $p . '?table=' . $_POST['table'] . '&task=update');
    exit;
}

$_SESSION['alert'] = 'Could not insert row into table <code>' . $_POST['table'] . '</code>.';
$_SESSION['alert'] .= '<br><b>DEBUG:</b> ' . Base::$error;
header('location: ' . $p . '?table=' . $_POST['table'] . '&task=update');
exit;