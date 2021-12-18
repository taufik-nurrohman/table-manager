<?php

if (isset($_POST['delete'])) {
    $rules = "DELETE FROM " . $_POST['table'] . " WHERE id=" . $_POST['delete'];
    if ($base->exec($rules)) {
        $_SESSION['alert'] = 'Deleted 1 row in table <code>' . $_POST['table'] . '</code>.';
        header('location: ' . $p . '?table=' . $_POST['table'] . '&task=update');
        exit;
    }
    $_SESSION['alert'] = 'Could not delete row in table <code>' . $_POST['table'] . '</code>.';
    $_SESSION['alert'] .= '<br><b>DEBUG:</b> ' . $base->lastErrorMsg();
    header('location: ' . $p . '?table=' . $_POST['table'] . '&task=update');
    exit;
}

$rules = "INSERT INTO " . $_POST['table'];

$values = [];
if (!empty($_POST['values'])) {
    foreach ($_POST['values'] as $k => $v) {
        if (isset($_FILES['values']['name'][$k])) {
            continue;
        }
        $values[$k] = "'" . $v . "'";
    }
    $rules .= " (" . implode(', ', array_keys($values)) . ") VALUES (" . implode(', ', array_values($values)) . ")";
}

if (!empty($_FILES['values'])) {
    // TODO: Upload blob as regular file and store its name to database.
}

if ($base->exec($rules)) {
    $_SESSION['alert'] = 'Inserted 1 row to table <code>' . $_POST['table'] . '</code>.';
    header('location: ' . $p . '?table=' . $_POST['table'] . '&task=update');
    exit;
}

$_SESSION['alert'] = 'Could not insert row into table <code>' . $_POST['table'] . '</code>.';
$_SESSION['alert'] .= '<br><b>DEBUG:</b> ' . $base->lastErrorMsg();
header('location: ' . $p . '?table=' . $_POST['table'] . '&task=update');
exit;