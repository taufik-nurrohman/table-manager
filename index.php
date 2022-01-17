<?php session_start();

error_reporting(E_ALL | E_STRICT);

ini_set('display_errors', true);
ini_set('display_startup_errors', true);
ini_set('error_log', __DIR__ . '/errors.log');
ini_set('html_errors', 1);

// Change the value to your time zone
date_default_timezone_set('Asia/Jakarta');

// TODO: Use native `SQLite3` class and remove composer
require __DIR__ . '/vendor/autoload.php';

$any = [&$_GET, &$_POST, &$_REQUEST];
$values = [
    'FALSE' => false,
    'NULL' => null,
    'TRUE' => true,
    'false' => false,
    'null' => null,
    'true' => true
];

array_walk_recursive($any, static function(&$v) use($values) {
    $v = trim(strtr($v, ["\r\n" => "\n", "\r" => "\n"]));
    if (is_numeric($v)) {
        $v = false !== strpos($v, '.') ? (float) $v : (int) $v;
    } else {
        $v = $values[$v] ?? $v;
    }
});

$DEBUG = true;
$FILE = __DIR__ . '/table.db';

$CHUNK = 20;
$EXCERPT = 50;
$PATTERN_TABLE = "^[A-Z][a-z\\d]*(?:_?[A-Z\\d][a-z\\d]*)*$";
$PATTERN_TABLE_COLUMN = "^[A-Za-z][A-Za-z\\d]*(?:_?[A-Za-z\\d][a-z\\d]*)*$";
$SESSION = 'STATUS';
$THUMB = 320;

$PATH = trim(strtr(strtr(__DIR__ . '/', "\\", '/'), [strtr($_SERVER['DOCUMENT_ROOT'], "\\", '/') => '/']), '/');
$PATH = "" !== $PATH ? '/' . $PATH . '/index.php' : '/index.php';

$safe = static function($value, $force = false) {
    $value = strip_tags(strtr($value ?? "", ['"' => '""']));
    if (!$force) {
        return is_numeric($value) ? $value : ("" !== $value ? '"' . $value . '"' : 'NULL');
    }
    return "" !== $value ? '"' . $value . '"' : 'NULL';
};

// <https://salman-w.blogspot.com/2014/04/stackoverflow-like-pagination.html>
$pager = static function($current, $count, $chunk, $peek, $fn, $first, $previous, $next, $last) {
    $begin = 1;
    $end = (int) ceil($count / $chunk);
    $out = "";
    if ($end <= 1) {
        return $out;
    }
    if ($current <= $peek + $peek) {
        $min = $begin;
        $max = min($begin + $peek + $peek, $end);
    } else if ($current > $end - $peek - $peek) {
        $min = $end - $peek - $peek;
        $max = $end;
    } else {
        $min = $current - $peek;
        $max = $current + $peek;
    }
    if ($previous) {
        $out = '<span>';
        if ($current === $begin) {
            $out .= '<b title="' . $previous . '">' . $previous . '</b>';
        } else {
            $out .= '<a href="' . call_user_func($fn, $current - 1) . '" title="' . $previous . '" rel="prev">' . $previous . '</a>';
        }
        $out .= '</span> ';
    }
    if ($first && $last) {
        $out .= '<span>';
        if ($min > $begin) {
            $out .= '<a href="' . call_user_func($fn, $begin) . '" title="' . $first . '" rel="prev">' . $begin . '</a>';
            if ($min > $begin + 1) {
                $out .= ' <span>&hellip;</span>';
            }
        }
        for ($i = $min; $i <= $max; ++$i) {
            if ($current === $i) {
                $out .= ' <b title="' . $i . '">' . $i . '</b>';
            } else {
                $out .= ' <a href="' . call_user_func($fn, $i) . '" title="' . $i . '" rel="' . ($current >= $i ? 'prev' : 'next') . '">' . $i . '</a>';
            }
        }
        if ($max < $end) {
            if ($max < $end - 1) {
                $out .= ' <span>&hellip;</span>';
            }
            $out .= ' <a href="' . call_user_func($fn, $end) . '" title="' . $last . '" rel="next">' . $end . '</a>';
        }
        $out .= '</span>';
    }
    if ($next) {
        $out .= ' <span>';
        if ($current === $end) {
            $out .= '<b title="' . $next . '">' . $next . '</b>';
        } else {
            $out .= '<a href="' . call_user_func($fn, $current + 1) . '" title="' . $next . '" rel="next">' . $next . '</a>';
        }
        $out .= '</span>';
    }
    return $out;
};

$path = static function() use($PATH) {
    return $PATH;
};

$query = static function(array $alter = []) {
    $q = http_build_query(array_replace_recursive($_GET, $alter));
    return "" !== $q ? '?' . $q : "";
};

if (!is_file($FILE)) {
    $_SESSION[$SESSION][] = 'Table does not exist. Automatically create a table for you.';
}

try {
    new Pixie\Connection('sqlite', [
        'database' => $FILE,
        'driver' => 'sqlite'
    ], 'Base');
} catch (Exception $e) {
    echo ($_SESSION[$SESSION][] = strtr($e->getMessage(), ["\n" => '<br>']));
    exit;
}

if ('POST' === $_SERVER['REQUEST_METHOD']) {
    if (isset($_POST['drop'])) {
        try {
            Base::query($stmt = 'DROP TABLE ' . $safe($table = $_POST['drop'], 1))->get();
            $_SESSION[$SESSION][] = 'Dropped table <code>' . $table . '</code>.';
            if ($DEBUG) {
                $_SESSION[$SESSION][] = '<b>DEBUG:</b> <code>' . htmlspecialchars($stmt) . '</code>';
            }
        } catch (Exception $e) {
            $_SESSION[$SESSION][] = 'Could not drop table <code>' . $table . '</code>.';
            if ($DEBUG) {
                $_SESSION[$SESSION][] = '<b>DEBUG:</b> ' . $e->getMessage() . '.';
            }
        }
        header('location: ' . $path());
        exit;
    }
    $task = $_POST['task'] ?? 0;
    if (empty($task)) {
        $_SESSION[$SESSION][] = 'Unknown task.';
        header('location: ' . $path());
        exit;
    }
    if ('alter' === $task) {
        $table = $_POST['table'] ?? 0;
        if (empty($table)) {
            $_SESSION[$SESSION][] = 'Unknown table.';
            header('location: ' . $path());
            exit;
        }
        if (isset($_POST['column']['to'])) {
            $from = $_POST['column']['from'] ?? 0;
            $to = $_POST['column']['to'] ?? 0;
            if (isset($_POST['alter']) && 0 === $_POST['alter']) {
                try {
                    Base::query($stmt = 'ALTER TABLE ' . $safe($table, 1) . ' DROP COLUMN ' . $safe($from, 1))->get();
                    $_SESSION[$SESSION][] = 'Dropped column <code>' . $from . '</code> from table <code>' . $table . '</code>.';
                    if ($DEBUG) {
                        $_SESSION[$SESSION][] = '<b>DEBUG:</b> <code>' . htmlspecialchars($stmt) . '</code>';
                    }
                } catch (Exception $e) {
                    $_SESSION[$SESSION][] = 'Could not drop column <code>' . $from . '</code> from table <code>' . $table . '</code>.';
                    if ($DEBUG) {
                        $_SESSION[$SESSION][] = '<b>DEBUG:</b> ' . $e->getMessage() . '.';
                    }
                }
                header('location: ' . $path() . $query([
                    'chunk' => null,
                    'column' => null,
                    'part' => null,
                    'row' => null,
                    'sort' => null,
                    'table' => $table,
                    'task' => null
                ]));
                exit;
            }
            if ($to === $from) {
                // Skip!
                $_SESSION[$SESSION][] = 'Nothing updated.';
            } else {
                try {
                    Base::query($stmt = 'ALTER TABLE ' . $safe($table, 1) . ' RENAME COLUMN ' . $safe($from, 1) . ' TO ' . $safe($to, 1))->get();
                    $_SESSION[$SESSION][] = 'Renamed column in table <code>' . $table . '</code> from <code>' . $from . '</code> to <code>' . $to . '</code>.';
                    if ($DEBUG) {
                        $_SESSION[$SESSION][] = '<b>DEBUG:</b> <code>' . htmlspecialchars($stmt) . '</code>';
                    }
                } catch (Exception $e) {
                    $_SESSION[$SESSION][] = 'Could not rename column in table <code>' . $table . '</code> from <code>' . $from . '</code> to <code>' . $to . '</code>.';
                    if ($DEBUG) {
                        $_SESSION[$SESSION][] = '<b>DEBUG:</b> ' . $e->getMessage() . '.';
                    }
                }
            }
            header('location: ' . $path() . $query([
                'chunk' => null,
                'column' => $to,
                'part' => null,
                'row' => null,
                'sort' => null,
                'table' => $table,
                'task' => null
            ]));
            exit;
        }
        $from = $_POST['table']['from'] ?? 0;
        $to = $_POST['table']['to'] ?? 0;
        if ($to === $from) {
            // Skip!
            $_SESSION[$SESSION][] = 'Nothing updated.';
        } else {
            try {
                Base::query($stmt = 'ALTER TABLE ' . $safe($from, 1) . ' RENAME TO ' . $safe($to, 1))->get();
                $_SESSION[$SESSION][] = 'Renamed table from <code>' . $from . '</code> to <code>' . $to . '</code>.';
                if ($DEBUG) {
                    $_SESSION[$SESSION][] = '<b>DEBUG:</b> <code>' . htmlspecialchars($stmt) . '</code>';
                }
            } catch (Exception $e) {
                $_SESSION[$SESSION][] = 'Could not rename table from <code>' . $from . '</code> to <code>' . $to . '</code>.';
                if ($DEBUG) {
                    $_SESSION[$SESSION][] = '<b>DEBUG:</b> ' . $e->getMessage() . '.';
                }
            }
        }
        header('location: ' . $path() . $query([
            'chunk' => null,
            'column' => null,
            'part' => null,
            'row' => null,
            'sort' => null,
            'table' => $to,
            'task' => null
        ]));
        exit;
    }
    if ('create' === $task) {
        $columns = [];
        $options = ['CURRENT_DATE', 'CURRENT_TIME', 'CURRENT_TIMESTAMP', false, null, true];
        foreach ($_POST['columns'] ?? [] as $k => $v) {
            $rules = "";
            $key = $v['key'];
            $type = $v['type'];
            $value = $v['value'];
            foreach ($v['rule'] ?? [] as $kk => $vv) {
                if ('CHECK' === $kk && is_array($vv)) {
                    $kk = 'CHECK(' . sprintf(implode(' AND ', array_keys($vv)), $safe($key, 1)) . ')';
                }
                $rules .= ' ' . $kk;
            }
            $value = in_array($value, $options, true) ? $value : $safe($value);
            if (false === $value) {
                $value = 'FALSE';
            } else if (null === $value) {
                $value = 'NULL';
            } else if (true === $value) {
                $value = 'TRUE';
            }
            $columns[$key] = trim($safe($key, 1) . ' ' . $type . ('NULL' !== $value ? ' DEFAULT ' . $value : "") . $rules);
        }
        $stmt = 'CREATE TABLE ' . $safe($table = $_POST['table'], 1);
        if ($columns) {
            ksort($columns);
            $keys = [];
            foreach ($_POST['primary'] ?? [] as $k => $v) {
                $keys[] = $safe($k, 1);
            }
            sort($keys);
            $primary = $keys ? ', PRIMARY KEY(' . implode(', ', $keys) . ')' : "";
            $stmt .= ' (' . implode(', ', $columns) . $primary . ')';
        }
        try {
            Base::query($stmt)->get();
            $_SESSION[$SESSION][] = 'Created table <code>' . $table . '</code>.';
            if ($DEBUG) {
                $_SESSION[$SESSION][] = '<b>DEBUG:</b> <code>' . htmlspecialchars($stmt) . '</code>';
            }
        } catch (Exception $e) {
            $_SESSION[$SESSION][] = 'Could not create table <code>' . $table . '</code>.';
            if ($DEBUG) {
                $_SESSION[$SESSION][] = '<b>DEBUG:</b> ' . $e->getMessage() . '.';
            }
        }
        header('location: ' . $path() . $query([
            'chunk' => null,
            'column' => null,
            'part' => null,
            'row' => null,
            'sort' => null,
            'table' => $table,
            'task' => null
        ]));
        exit;
    }
    if ('delete' === $task) {
        try {
            Base::query($stmt = 'DELETE FROM ' . $safe($table = $_POST['table'], 1) . ' WHERE ' . $safe($column = $_POST['column'] ?? 'rowid', 1) . ' = ' . $safe($row = $_POST['row']))->get();
            $_SESSION[$SESSION][] = 'Deleted 1 row with ID <code>' . $row . '</code> from table <code>' . $table . '</code>.';
            if ($DEBUG) {
                $_SESSION[$SESSION][] = '<b>DEBUG:</b> <code>' . htmlspecialchars($stmt) . '</code>';
            }
        } catch (Exception $e) {
            $_SESSION[$SESSION][] = 'Could not delete row with ID <code>' . $row . '</code> from table <code>' . $table . '</code>.';
            if ($DEBUG) {
                $_SESSION[$SESSION][] = '<b>DEBUG:</b> ' . $e->getMessage() . '.';
            }
        }
        header('location: ' . $path() . $query([
            'chunk' => null,
            'column' => null,
            'part' => null,
            'row' => null,
            'sort' => null,
            'table' => $table,
            'task' => null
        ]));
        exit;
    }
    if ('insert' === $task) {
        try {
            $keys = $values = [];
            foreach ($_POST['values'] ?? [] as $k => $v) {
                if (isset($_FILES['values']['name'][$k])) {
                    continue;
                }
                $keys[] = $safe($k, 1);
                if (is_array($v)) {
                    if (array_key_exists('date', $v)) {
                        $v['date'] = $v['date'] ?: date('Y-m-d');
                    }
                    if (array_key_exists('time', $v)) {
                        $v['time'] = $v['time'] ?: date('H:i:s');
                    }
                    $v = trim(implode(' ', $v));
                }
                $values[] = $safe($v);
            }
            $errors = [
                0 => 'There is no error, the file uploaded with success.',
                1 => 'The uploaded file exceeds the <code>upload_max_filesize</code> directive in <code>php.ini</code>.',
                2 => 'The uploaded file exceeds the <code>MAX_FILE_SIZE</code> directive that was specified in the HTML form.',
                3 => 'The uploaded file was only partially uploaded.',
                4 => 'No file was uploaded.',
                6 => 'Missing a temporary folder.',
                7 => 'Failed to write file to disk.',
                8 => 'A PHP extension stopped the file upload.',
            ];
            foreach ($_FILES['values']['name'] ?? [] as $k => $v) {
                $error = $_FILES['values']['error'][$k] ?? -1;
                if (!empty($error)) {
                    $_SESSION[$SESSION][] = $errors[$error] ?? 'Unknown error.';
                    continue;
                }
                $keys[] = $safe($k, 1);
                $values[] = $safe('data:' . $_FILES['values']['type'][$k] . ';base64,' . base64_encode(file_get_contents($_FILES['values']['tmp_name'][$k])) . '#' . urlencode($v));
            }
            $stmt = 'INSERT INTO ' . $safe($table = $_POST['table'], 1) . ' (' . implode(', ', $keys) . ') VALUES (' . implode(', ', $values) . ')';
            Base::query($stmt)->get();
            $_SESSION[$SESSION][] = 'Inserted 1 row to table <code>' . $table . '</code>.';
            if ($DEBUG) {
                $_SESSION[$SESSION][] = '<b>DEBUG:</b> <code>' . htmlspecialchars($stmt) . '</code>';
            }
        } catch (Exception $e) {
            $_SESSION[$SESSION][] = 'Could not insert row to table <code>' . $table . '</code>.';
            if ($DEBUG) {
                $_SESSION[$SESSION][] = '<b>DEBUG:</b> ' . $e->getMessage() . '.';
            }
        }
        header('location: ' . $path() . $query([
            'chunk' => null,
            'column' => null,
            'part' => null,
            'row' => null,
            'sort' => null,
            'table' => $table,
            'task' => null
        ]));
        exit;
    }
    if ('update' === $task) {
        try {
            $values = [];
            foreach ($_POST['values'] ?? [] as $k => $v) {
                if (isset($_FILES['values']['name'][$k])) {
                    continue;
                }
                if (is_array($v)) {
                    if (array_key_exists('date', $v)) {
                        $v['date'] = $v['date'] ?: date('Y-m-d');
                    }
                    if (array_key_exists('time', $v)) {
                        $v['time'] = $v['time'] ?: date('H:i:s');
                    }
                    $v = trim(implode(' ', $v));
                }
                $values[] = $safe($k, 1) . ' = ' . $safe($v);
            }
            $errors = [
                0 => 'There is no error, the file uploaded with success.',
                1 => 'The uploaded file exceeds the <code>upload_max_filesize</code> directive in <code>php.ini</code>.',
                2 => 'The uploaded file exceeds the <code>MAX_FILE_SIZE</code> directive that was specified in the HTML form.',
                3 => 'The uploaded file was only partially uploaded.',
                4 => 'No file was uploaded.',
                6 => 'Missing a temporary folder.',
                7 => 'Failed to write file to disk.',
                8 => 'A PHP extension stopped the file upload.',
            ];
            foreach ($_FILES['values']['name'] ?? [] as $k => $v) {
                $error = $_FILES['values']['error'][$k] ?? -1;
                if (!empty($error)) {
                    $_SESSION[$SESSION][] = $errors[$error] ?? 'Unknown error.';
                    continue;
                }
                $values[] = $safe($k, 1) . ' = ' . $safe('data:' . $_FILES['values']['type'][$k] . ';base64,' . base64_encode(file_get_contents($_FILES['values']['tmp_name'][$k])) . '#' . urlencode($v));
            }
            $stmt = 'UPDATE ' . $safe($table = $_POST['table'], 1) . ' SET ' . implode(', ', $values) . ' WHERE ' . $safe($column = $_POST['column'] ?? 'rowid', 1) . ' = ' . $safe($row = $_POST['row']);
            Base::query($stmt)->get();
            $_SESSION[$SESSION][] = 'Updated 1 row with ID <code>' . $row . '</code> in table <code>' . $table . '</code>.';
            if ($DEBUG) {
                $_SESSION[$SESSION][] = '<b>DEBUG:</b> <code>' . htmlspecialchars($stmt) . '</code>';
            }
        } catch (Exception $e) {
            $_SESSION[$SESSION][] = 'Could not update row with ID <code>' . $row . '</code> in table <code>' . $table . '</code>.';
            if ($DEBUG) {
                $_SESSION[$SESSION][] = '<b>DEBUG:</b> ' . $e->getMessage() . '.';
            }
        }
        header('location: ' . $path() . $query([
            'chunk' => null,
            'column' => null,
            'part' => null,
            'row' => null,
            'sort' => null,
            'table' => $table,
            'task' => null
        ]));
        exit;
    }
    header('location: ' . $path());
    exit;
}

$style = <<<CSS
* {
  background: none;
  box-sizing: border-box;
  color: inherit;
  font: inherit;
  margin: 0;
  padding: 0;
}
:focus {
  outline: 0;
}
:root {
  background: #fff;
  color: #000;
  font: normal normal 14px/1.4 sans-serif;
  padding: 1em;
}
a {
  color: #00f;
  text-decoration: none;
}
a[aria-current='true'] {
  color: inherit;
}
a:focus,
a:hover {
  text-decoration: underline;
}
b,
h3,
th {
  font-weight: bold;
}
a[role='button'],
button,
input,
select,
textarea {
  position: relative;
  z-index: 1;
}
a[role='button']:focus,
button:focus,
input:focus,
select:focus,
textarea:focus {
  z-index: 10;
}
a[role='button'],
button {
  background: #def;
  border: 2px solid #000;
  color: inherit;
  cursor: pointer;
  display: inline-block;
  font: inherit;
  font-weight: normal;
  padding: .25em .5em;
  text-decoration: none;
  vertical-align: middle;
}
a[role='button']:focus,
button:focus,
select:focus {
  border-color: #00f;
  outline-offset: -4px;
  outline: 1px solid #00f;
}
::file-selector-button {
  background: #def;
  border: 2px solid #000;
  color: inherit;
  cursor: pointer;
  display: inline-block;
  font: inherit;
  font-weight: normal;
  margin-inline-end: .5em;
  padding: .25em .5em;
  text-decoration: none;
  vertical-align: middle;
}
:focus::file-selector-button,
::file-selector-button:focus {
  border-color: #00f;
  outline-offset: -4px;
  outline: 1px solid #00f;
}
code {
  color: #909;
  font-family: monospace;
}
i {
  font-style: italic;
}
input[type='color'],
input[type='date'],
input[type='number'],
input[type='search'],
input[type='text'],
input[type='time'],
select,
textarea {
  background: #fff;
  border: 2px solid #000;
  display: inline-block;
  font: inherit;
  font-family: monospace;
  font-weight: normal;
  padding: .25em .5em;
  vertical-align: middle;
  width: 12em;
}
textarea {
  height: 12em;
  resize: vertical;
  width: 100%;
}
input[maxlength='255'] {
  width: 100%;
}
input[type='color'] {
  cursor: pointer;
  height: 2em;
  padding: 0;
  width: 2em;
}
::-moz-color-swatch {
  border: 0;
}
::-webkit-color-swatch {
  border: 0;
}
::-webkit-color-swatch-wrapper {
  padding: 0;
}
input[type='checkbox'],
input[type='radio'] {
  appearance: none;
  border: 2px solid;
  box-shadow: inset 0 0 0 2px #fff;
  cursor: pointer;
  display: inline-block;
  font-weight: normal;
  height: 1em;
  min-height: 1em;
  min-width: 1em;
  vertical-align: middle;
  width: 1em;
}
input[type='checkbox']:focus,
input[type='radio']:focus {
  border-color: #00f;
}
input[type='checkbox']:checked,
input[type='radio']:checked {
  background: #000;
}
input[type='checkbox']:checked:focus,
input[type='radio']:checked:focus {
  background: #00f;
}
input[type='radio'] {
  border-radius: 100%;
}
button + input[type='text'],
input[type='text'] + button {
  margin-left: -2px;
}
label {
  cursor: pointer;
  display: inline-block;
  user-select: none;
  vertical-align: middle;
}
label + input {
  margin-left: .5em;
}
label > input + span {
  display: inline-block;
  vertical-align: middle;
}
form,
h3,
hr,
ol,
p,
table,
ul {
  margin: 0 0 1em;
}
hr {
  border: 0;
  border-top: 1px dashed #000;
}
ol,
ul {
  margin-left: 1.5em;
}
select {
  cursor: pointer;
}
small {
  font-size: small;
}
table {
  border-collapse: collapse;
  table-layout: auto;
  width: 100%;
}
tbody:empty + tfoot {
  display: none;
}
td,
th {
  border: 1px solid;
  padding: .5em;
  text-align: left;
  vertical-align: top;
  white-space: nowrap;
}
th {
  background: #fed;
}
[role='alert'] {
  background: #fe9;
  padding: .5em .75em;
  word-wrap: break-word;
}
[role='group'] {
  display: flex;
  flex-wrap: wrap;
  gap: .5em;
  margin: 0;
}
[role='status'] {
  color: #f00;
}
form {
  display: flex;
  gap: 2em;
}
aside {
  min-width: 10em;
  order: 1;
}
main {
  flex: 1;
  order: 2;
  overflow: auto;
}
:disabled,
[role='button']:not([href]) {
  cursor: not-allowed;
  opacity: .5;
}
:focus:invalid {
  color: #f00;
  outline-color: #f00;
}
tr[data-type='NULL'] .column-rule\:check-length-max-255,
tr[data-type='NULL'] .column-rule\:not-null,
tr[data-type='NULL'] .column-rule\:unique {
  display: none;
}
CSS;

$title = 'SQLite Table Manager';

http_response_code(200);

$out  = '<!DOCTYPE html>';
$out .= '<html dir="ltr">';
$out .= '<head>';
$out .= '<meta charset="utf-8">';
$out .= '<meta content="width=device-width" name="viewport">';
$out .= '<title>';
$out .= $title;
$out .= '</title>';
$out .= '<link href="favicon.ico" rel="icon">';
$out .= '<style>';
$out .= $style;
$out .= '</style>';
$out .= '</head>';
$out .= '<body>';

if (!empty($_SESSION[$SESSION])) {
    foreach ((array) $_SESSION[$SESSION] as $v) {
        $out .= '<p role="alert">';
        $out .= $v;
        $out .= '</p>';
    }
}

$out .= '<form action="' . $path() . '" enctype="multipart/form-data" method="post">';
$out .= '<main>';

$task = $_GET['task'] ?? null;

if (!empty($_GET['table'])) {
    $name = $safe($_GET['table'], 1);
    $columns = Base::query('PRAGMA table_info(' . $name . ')')->get();
    $sql = Base::query('SELECT "sql" FROM "sqlite_master" WHERE "tbl_name" = ' . $name)->first()->sql ?? "";
    $sql = substr($sql, strpos($sql, '('));
    if ('alter' === $task) {
        if (!empty($_GET['column'])) {
            $column = $safe($_GET['column'], 1);
            $found = false;
            foreach ($columns as $v) {
                if ('"' . $v->name . '"' === $column) {
                    $found = true;
                    break;
                }
            }
            if ($found) {
                $out .= '<h3>';
                $out .= 'Column';
                $out .= '</h3>';
                $out .= '<p>';
                $out .= '<input autofocus name="column[to]" placeholder=' . $column . ' pattern="' . $PATTERN_TABLE_COLUMN . '" required type="text" value=' . $column . '>';
                $out .= '</p>';
                $out .= '<p>';
                $out .= '<button name="alter" type="submit" value="1">';
                $out .= 'Update';
                $out .= '</button>';
                $out .= ' ';
                $out .= '<button name="alter" type="submit" value="0">';
                $out .= 'Delete';
                $out .= '</button>';
                $out .= '</p>';
                $out .= '<input name="task" type="hidden" value="alter">';
            } else {
                $out .= '<p>';
                $out .= 'Column <code>' . trim($column, '"') . '</code> does not exist.';
                $out .= '</p>';
            }
            $out .= '<input name="column[from]" type="hidden" value=' . $column . '>';
            $out .= '<input name="table" type="hidden" value=' . $name . '>';
        } else {
            $out .= '<h3>';
            $out .= 'Table';
            $out .= '</h3>';
            $out .= '<p>';
            $out .= '<input autofocus name="table[to]" placeholder=' . $name . ' pattern="' . $PATTERN_TABLE . '" required type="text" value=' . $name . '>';
            $out .= '</p>';
            $out .= '<p>';
            $out .= '<button name="alter" type="submit" value="1">';
            $out .= 'Update';
            $out .= '</button>';
            $out .= '</p>';
            $out .= '<h3>';
            $out .= 'Columns';
            $out .= '</h3>';
            $out .= '<ul>';
            foreach ($columns as $v) {
                $column = $v->name;
                $out .= '<li>';
                $out .= '<a href="' . $path() . $query([
                    'chunk' => null,
                    'column' => $column,
                    'part' => null,
                    'row' => null,
                    'sort' => null,
                    'table' => trim($name, '"'),
                    'task' => 'alter'
                ]) . '">';
                $out .= $column;
                if (!empty($v->pk)) {
                    $out .= '<small aria-label="Primary Key" role="status">';
                    $out .= '*';
                    $out .= '</small>';
                }
                $out .= '</a>';
                $out .= '</li>';
            }
            $out .= '</ul>';
            $out .= '<p>';
            // TODO
            $out .= '<a role="button">';
            $out .= 'Add';
            $out .= '</a>';
            $out .= '</p>';
            $out .= '<input name="table[from]" type="hidden" value=' . $name . '>';
            $out .= '<input name="task" type="hidden" value="alter">';
        }
    } else if ('insert' === $task) {
        $out .= '<table>';
        $out .= '<tbody>';
        $first = true;
        foreach ($columns as $k => $v) {
            if (!empty($v->pk) && 'INTEGER' === $v->type) {
                continue;
            }
            $d = $v->dflt_value ?? "";
            $n = $v->name;
            $r = !empty($v->notnull);
            $t = $v->type;
            if ($d) {
                if (0 === strpos($d, '"')) {
                    $d = substr(strtr($d, ['""' => '"']), 1, -1);
                } else if (0 === strpos($d, "'")) {
                    $d = substr(strtr($d, ["''" => "'"]), 1, -1);
                }
            }
            $out .= '<tr>';
            $out .= '<th scope="row">';
            $out .= $n;
            $out .= '</th>';
            $out .= '<td>';
            if ('BLOB' === $t) {
                $out .= '<input' . ($first ? ' autofocus' : "") . ' name="values[' . $n . ']"' . ($r ? ' required' : "") . ' type="file">';
            } else if ('INTEGER' === $t) {
                if ('FALSE' === $d || 'TRUE' === $d) {
                    $out .= '<span role="group">';
                    $out .= '<label>';
                    $out .= '<input' . ('TRUE' === $d ? ($first ? ' autofocus' : "") . ' checked' : "") . ' name="values[' . $n . ']" type="radio" value="1">';
                    $out .= ' ';
                    $out .= '<span>';
                    $out .= 'Yes';
                    $out .= '</span>';
                    $out .= '</label>';
                    $out .= ' ';
                    $out .= '<label>';
                    $out .= '<input' . ('FALSE' === $d ? ($first ? ' autofocus' : "") . ' checked' : "") . ' name="values[' . $n . ']" type="radio" value="0">';
                    $out .= ' ';
                    $out .= '<span>';
                    $out .= 'No';
                    $out .= '</span>';
                    $out .= '</label>';
                    $out .= '</span>';
                } else {
                    $out .= '<input' . ($first ? ' autofocus' : "") . ' max="9223372036854775807" min="-9223372036854775808" name="values[' . $n . ']" placeholder="' . htmlspecialchars($d) . '"' . ($r ? ' required' : "") . ' step="1" type="number">';
                }
            } else if ("" === $t || 'NULL' === $t) {
                $out .= '<code>';
                $out .= 'NULL';
                $out .= '</code>';
            } else if ('REAL' === $t) {
                $out .= '<input' . ($first ? ' autofocus' : "") . ' max="9223372036854775807" min="-9223372036854775808" name="values[' . $n . ']" placeholder="' . htmlspecialchars($d) . '"' . ($r ? ' required' : "") . ' step="0.01" type="number">';
            } else {
                if ('CURRENT_DATE' === $d || preg_match('/^[1-9]\d{3,}-(0\d|1[0-2])-(0\d|[1-2]\d|3[0-1])$/', $d)) {
                    $out .= '<input' . ($first ? ' autofocus' : "") . ' name="values[' . $n . '][date]"' . ($r ? ' required' : "") . ' type="date" value="' . ('CURRENT_DATE' !== $d ? $d : date('Y-m-d')) . '">';
                } else if ('CURRENT_TIME' === $d || preg_match('/^([0-1]\d|2[0-4]):([0-5]\d|60):([0-5]\d|60)$/', $d)) {
                    $out .= '<input' . ($first ? ' autofocus' : "") . ' name="values[' . $n . '][time]"' . ($r ? ' required' : "") . ' type="time" value="' . ('CURRENT_TIME' !== $d ? $d : date('H:m:s')) . '">';
                } else if ('CURRENT_TIMESTAMP' === $d || preg_match('/^[1-9]\d{3,}-(0\d|1[0-2])-(0\d|[1-2]\d|3[0-1])\s+([0-1]\d|2[0-4]):([0-5]\d|60):([0-5]\d|60)$/', $d)) {
                    [$date, $time] = explode(' ', 'CURRENT_TIMESTAMP' !== $d ? $d : date('Y-m-d H:i:s'), 2);
                    $out .= '<input' . ($first ? ' autofocus' : "") . ' name="values[' . $n . '][date]"' . ($r ? ' required' : "") . ' type="date" value="' . $date . '">';
                    $out .= ' ';
                    $out .= '<input' . ($first ? ' autofocus' : "") . ' name="values[' . $n . '][time]"' . ($r ? ' required' : "") . ' type="time" value="' . $time . '">';
                } else if (0 === strpos($d, '#') && 7 === strlen($d) && ctype_xdigit(substr($d, 1))) {
                    $out .= '<input' . ($first ? ' autofocus' : "") . ' name="values[' . $n . ']"' . ($r ? ' required' : "") . ' type="color" value="' . htmlspecialchars($d) . '">';
                } else {
                    if ($sql && false !== strpos($sql, 'CHECK(LENGTH("' . $n . '") <= 255)')) {
                        $out .= '<input' . ($first ? ' autofocus' : "") . ' maxlength="255" name="values[' . $n . ']" placeholder="' . htmlspecialchars($d) . '"' . ($r ? ' required' : "") . ' type="text">';
                    } else {
                        $out .= '<textarea' . ($first ? ' autofocus' : "") . ' name="values[' . $n . ']" placeholder="' . htmlspecialchars($d) . '"' . ($r ? ' required' : "") . '>';
                        $out .= '</textarea>';
                    }
                }
            }
            $out .= '</td>';
            $out .= '</tr>';
            $first = false;
        }
        $out .= '</tbody>';
        $out .= '</table>';
        $out .= '<p>';
        $out .= '<button name="task" type="submit" value="insert">';
        $out .= 'Insert';
        $out .= '</button>';
        $out .= '</p>';
        $out .= '<input name="table" type="hidden" value=' . $name . '>';
    } else if (array_key_exists('row', $_GET)) {
        if ($row = Base::table(trim($name, '"'))->find($_GET['row'] ?? 0, $_GET['column'] ?? 'rowid')) {
            $out .= '<table>';
            $out .= '<tbody>';
            $first = true;
            foreach ($columns as $k => $v) {
                if (!empty($v->pk) && 'INTEGER' === $v->type) {
                    continue;
                }
                $d = $v->dflt_value ?? "";
                $n = $v->name;
                $r = !empty($v->notnull);
                $t = $v->type;
                if ($d) {
                    if (0 === strpos($d, '"')) {
                        $d = substr(strtr($d, ['""' => '"']), 1, -1);
                    } else if (0 === strpos($d, "'")) {
                        $d = substr(strtr($d, ["''" => "'"]), 1, -1);
                    }
                }
                $out .= '<tr>';
                $out .= '<th scope="row">';
                $out .= $n;
                $out .= '</th>';
                $out .= '<td>';
                if ('BLOB' === $t) {
                    if (preg_match('/^data:([^\/]+)\/([^;]+);base64,([^#]+)(?:#(.*))?$/', $row->{$n}, $m)) {
                        $out .= '<p>';
                        if ('image' === $m[1]) {
                            if (extension_loaded('gd')) {
                                $gd = imagecreatefromstring(base64_decode($m[3]));
                                $x = imagesx($gd);
                                $y = imagesy($gd);
                                if ($y > $x) { // Portrait
                                    if ($y > $THUMB) {
                                        $gd = imagescale($gd, -1, $THUMB, IMG_NEAREST_NEIGHBOUR);
                                    }
                                } else {
                                    if ($x > $THUMB) {
                                        $gd = imagescale($gd, $THUMB, -1, IMG_NEAREST_NEIGHBOUR);
                                    }
                                }
                                ob_start();
                                if ('gif' === $m[2]) {
                                    imagegif($gd);
                                } else if ('jpeg' === $m[2] || 'jpg' === $m[2]) {
                                    imagejpeg($gd, null, 100);
                                } else if ('png' === $m[2]) {
                                    imagepng($gd, null, 9);
                                } else if ('webp' === $m[2]) {
                                    imagewebp($gd, null, 100);
                                }
                                $buffer = ob_get_clean();
                                $out .= '<img alt="" src="data:' . $m[1] . '/' . $m[2] . ';base64,' . base64_encode($buffer) . '">';
                                if (isset($m[4])) {
                                    $out .= '<br>';
                                    $out .= '<small>';
                                    $out .= $m[4];
                                    $out .= '</small>';
                                }
                            } else {
                                $out .= '<small role="status">';
                                $out .= 'Missing <a href="https://www.php.net/manual/en/book.image.php" rel="nofollow" target="_blank">GD</a> extension.';
                                $out .= '</small>';
                            }
                        } else {
                            $out .= '&#x1f4c4;';
                            $out .= ' ';
                            $out .= $m[4];
                        }
                        $out .= '</p>';
                    }
                    $out .= '<input' . ($first ? ' autofocus' : "") . ' name="values[' . $n . ']"' . ($r ? ' required' : "") . ' type="file">';
                } else if ('INTEGER' === $t) {
                    if ('FALSE' === $d || 'TRUE' === $d) {
                        $out .= '<span role="group">';
                        $out .= '<label>';
                        $out .= '<input' . ('1' === $row->{$n} ? ($first ? ' autofocus' : "") . ' checked' : "") . ' name="values[' . $n . ']" type="radio" value="1">';
                        $out .= ' ';
                        $out .= '<span>';
                        $out .= 'Yes';
                        $out .= '</span>';
                        $out .= '</label>';
                        $out .= ' ';
                        $out .= '<label>';
                        $out .= '<input' . ('0' === $row->{$n} ? ($first ? ' autofocus' : "") . ' checked' : "") . ' name="values[' . $n . ']" type="radio" value="0">';
                        $out .= ' ';
                        $out .= '<span>';
                        $out .= 'No';
                        $out .= '</span>';
                        $out .= '</label>';
                        $out .= '</span>';
                    } else {
                        $out .= '<input' . ($first ? ' autofocus' : "") . ' max="9223372036854775807" min="-9223372036854775808" name="values[' . $n . ']" placeholder="' . htmlspecialchars($d) . '"' . ($r ? ' required' : "") . ' step="1" type="number" value="' . htmlspecialchars($row->{$n} ?? "") . '">';
                    }
                } else if ("" === $t || 'NULL' === $t) {
                    $out .= '<code>';
                    $out .= 'NULL';
                    $out .= '</code>';
                } else if ('REAL' === $t) {
                    $out .= '<input' . ($first ? ' autofocus' : "") . ' max="9223372036854775807" min="-9223372036854775808" name="values[' . $n . ']" placeholder="' . htmlspecialchars($d) . '"' . ($r ? ' required' : "") . ' step="0.01" type="number" value="' . htmlspecialchars($row->{$n} ?? "") . '">';
                } else {
                    if ('CURRENT_DATE' === $d || preg_match('/^[1-9]\d{3,}-(0\d|1[0-2])-(0\d|[1-2]\d|3[0-1])$/', $d)) {
                        $out .= '<input' . ($first ? ' autofocus' : "") . ' name="values[' . $n . '][date]"' . ($r ? ' required' : "") . ' type="date" value="' . htmlspecialchars($row->{$n}) . '">';
                    } else if ('CURRENT_TIME' === $d || preg_match('/^([0-1]\d|2[0-4]):([0-5]\d|60):([0-5]\d|60)$/', $d)) {
                        $out .= '<input' . ($first ? ' autofocus' : "") . ' name="values[' . $n . '][time]"' . ($r ? ' required' : "") . ' type="time" value="' . htmlspecialchars($row->{$n}) . '">';
                    } else if ('CURRENT_TIMESTAMP' === $d || preg_match('/^[1-9]\d{3,}-(0\d|1[0-2])-(0\d|[1-2]\d|3[0-1])\s+([0-1]\d|2[0-4]):([0-5]\d|60):([0-5]\d|60)$/', $d)) {
                        [$date, $time] = explode(' ', $row->{$n}, 2);
                        $out .= '<input' . ($first ? ' autofocus' : "") . ' name="values[' . $n . '][date]"' . ($r ? ' required' : "") . ' type="date" value="' . htmlspecialchars($date) . '">';
                        $out .= ' ';
                        $out .= '<input' . ($first ? ' autofocus' : "") . ' name="values[' . $n . '][time]"' . ($r ? ' required' : "") . ' type="time" value="' . htmlspecialchars($time) . '">';
                    } else if (0 === strpos($d, '#') && 7 === strlen($d) && ctype_xdigit(substr($d, 1))) {
                        $out .= '<input' . ($first ? ' autofocus' : "") . ' name="values[' . $n . ']"' . ($r ? ' required' : "") . ' type="color" value="' . htmlspecialchars($row->{$n}) . '">';
                    } else {
                        if ($sql && false !== strpos($sql, 'CHECK(LENGTH("' . $n . '") <= 255)')) {
                            $out .= '<input' . ($first ? ' autofocus' : "") . ' maxlength="255" name="values[' . $n . ']" placeholder="' . htmlspecialchars($d) . '"' . ($r ? ' required' : "") . ' type="text" value="' . htmlspecialchars(substr($row->{$n}, 0, 255)) . '">';
                        } else {
                            $out .= '<textarea' . ($first ? ' autofocus' : "") . ' name="values[' . $n . ']" placeholder="' . htmlspecialchars($d) . '"' . ($r ? ' required' : "") . '>';
                            $out .= htmlspecialchars($row->{$n} ?? "");
                            $out .= '</textarea>';
                        }
                    }
                }
                $out .= '</td>';
                $out .= '</tr>';
                $first = false;
            }
            $out .= '</tbody>';
            $out .= '</table>';
            $out .= '<p>';
            $out .= '<button name="task" type="submit" value="update">';
            $out .= 'Update';
            $out .= '</button>';
            $out .= ' ';
            $out .= '<button name="task" type="submit" value="delete">';
            $out .= 'Delete';
            $out .= '</button>';
            $out .= '</p>';
            $out .= '<input name="column" type="hidden" value=' . $safe($_GET['column'] ?? 'rowid', 1) . '>';
            $out .= '<input name="row" type="hidden" value=' . $safe($_GET['row'], 1) . '>';
            $out .= '<input name="table" type="hidden" value=' . $name . '>';
        } else {
            $out .= '<p>';
            $out .= 'Could not find a row with ID <code>' . $_GET['row'] . '</code> in table <code>' . trim($name, '"') . '</code>.';
            $out .= '</p>';
        }
    } else if ($table = Base::query('PRAGMA table_info(' . $name . ')')->get()) {
        $fields = [];
        $columns = count((array) $table);
        $rows = Base::table(trim($name, '"'))->count();

        $out .= '<p role="status">';
        $out .= '<span id="table-columns">' . $columns . '</span> Column' . (1 === $columns ? "" : 's');
        $out .= ', ';
        $out .= '<span id="table-rows">' . $rows . '</span> Row' . (1 === $rows ? "" : 's');
        $out .= '</p>';

        $out .= '<p>';
        $out .= '<a href="' . $path() . $query([
            'chunk' => null,
            'column' => null,
            'part' => null,
            'row' => null,
            'sort' => null,
            'table' => trim($name, '"'),
            'task' => 'alter'
        ]) . '" role="button">';
        $out .= 'Alter';
        $out .= '</a>';
        $out .= ' ';
        $out .= '<a href="' . $path() . $query([
            'chunk' => null,
            'column' => null,
            'part' => null,
            'row' => null,
            'sort' => null,
            'table' => trim($name, '"'),
            'task' => 'insert'
        ]) . '" role="button">';
        $out .= 'Insert';
        $out .= '</a>';
        $out .= ' ';
        $out .= '<button disabled name="drop" type="submit" value=' . $name . '>';
        $out .= 'Drop';
        $out .= '</button>';
        $out .= '</p>';

        $out .= '<table>';
        $out .= '<thead>';
        $out .= '<tr>';

        $keys = [];

        $key = $table[0]->name ?? 'rowid';
        foreach ($table as $v) {
            $n = $v->name;
            if ($p = (int) $v->pk) {
                $keys[$n] = $p;
            }
            $fields[] = 'CASE WHEN INSTR(' . $safe($n, 1) . ', "data:") = 1 AND INSTR(' . $safe($n, 1) . ', ";base64,") THEN "&#x1f4c4; " || SUBSTR(' . $safe($n, 1) . ', INSTR(' . $safe($n, 1) . ', "#") + 1) WHEN LENGTH(' . $safe($n, 1) . ') > ' . $EXCERPT . ' THEN SUBSTR(' . $safe($n, 1) . ', 1, ' . $EXCERPT . ') || "..." ELSE ' . $safe($n, 1) . ' END AS ' . $safe($n, 1);
            $out .= '<th>';
            $out .= '<a' . ($n === ($_GET['sort'][1] ?? $key) ? ' aria-current="true"' : "") . ' href="' . $path() . $query([
                'sort' => [1 === ($_GET['sort'][0] ?? -1) ? -1 : 1, $n]
            ]) . '">';
            $out .= $n;
            if (!empty($p)) {
                $out .= '<small aria-label="Primary Key" role="status">';
                $out .= '*';
                $out .= '</small>';
            }
            $out .= '</a>';
            $out .= '</th>';
        }

        // Has 0 or more than 1 primary key(s)!
        if ($has_primary_key_alias = 0 === count($keys) || count($keys) > 1) {
            $out .= '<th>';
            $out .= '<span aria-label="Primary Key" role="status">';
            $out .= '#';
            $out .= '</span>';
            $out .= '</th>';
        }

        $out .= '</tr>';
        $out .= '</thead>';
        $out .= '<tbody>';

        if (0 === $rows) {
            $out .= '<tr>';
            $out .= '<td colspan="' . ($columns + 1) . '" style="text-align: center;">';
            $out .= '<i aria-label="Empty Table" role="status">';
            $out .= 'EMPTY';
            $out .= '</i>';
            $out .= '</td>';
            $out .= '</tr>';
        } else {
            sort($fields);
            if ($has_primary_key_alias) {
                $fields[] = 'rowid';
                $ref = $keys;
                $keys = ['rowid' => 1];
            }
            $rows = Base::query('SELECT ' . implode(', ', $fields) . ' FROM ' . $name . ' ORDER BY ' . $safe($_GET['sort'][1] ?? 'rowid', 1) . ' ' . (1 === ($_GET['sort'][0] ?? -1) ? 'ASC' : 'DESC') . ' LIMIT ' . ($chunk = $_GET['chunk'] ?? $CHUNK) . ' OFFSET ' . ($chunk * (($_GET['part'] ?? 1) - 1)))->get();
            foreach ($rows as $row) {
                $out .= '<tr>';
                foreach ($row as $k => $v) {
                    $out .= '<td>';
                    if (0 === strpos($v, '#') && 7 === strlen($v) && ctype_xdigit(substr($v, 1))) {
                        $as_color = true;
                        $out .= '<span style="background: ' . $v . '; border: 1px solid #808080; display: inline-block; height: 1.25em; vertical-align: middle; width: 1.25em;"></span>';
                        $out .= ' ';
                        $out .= '<span style="display: inline-block; vertical-align: middle;">';
                    }
                    if (!empty($keys[$k])) {
                        $out .= '<a href="' . $path() . $query([
                            'chunk' => null,
                            'column' => 'rowid' === $k ? null : $k,
                            'part' => null,
                            'row' => $v,
                            'sort' => null,
                            'table' => trim($name, '"'),
                            'task' => null
                        ]) . '">';
                    }
                    if (null === $v) {
                        if ($has_primary_key_alias && !empty($ref[$k])) {
                            $out .= $row->rowid ?? '?';
                        } else {
                            $out .= '<i aria-label="Null Value" role="status">';
                            $out .= 'NULL';
                            $out .= '</i>';
                        }
                    } else if ("" === $v) {
                        $out .= '<i aria-label="Empty String Value" role="status">';
                        $out .= 'EMPTY';
                        $out .= '</i>';
                    }
                    $v = htmlspecialchars($v);
                    if (0 === strpos($v, '&amp;#x')) {
                        $v = strtr($v, ['&amp;#x' => '&#x']);
                    }
                    $v = strtr($v, ['...' => '&hellip;']);
                    $out .= $v;
                    if (!empty($keys[$k])) {
                        $out .= '</a>';
                    }
                    if (!empty($as_color)) {
                        $out .= '</span>';
                        unset($as_color);
                    }
                    $out .= '</td>';
                }
                $out .= '</tr>';
            }
        }

        $out .= '</tbody>';
        $out .= '</table>';

        $the_pager = $pager($_GET['part'] ?? 1, Base::table(trim($name, '"'))->count(), $_GET['chunk'] ?? $CHUNK, 2, static function($part) use($CHUNK, $path, $query) {
            return $path() . strtr($query([
                'chunk' => $_GET['chunk'] ?? $CHUNK,
                'part' => $part,
                'sort' => $_GET['sort'] ?? [-1]
            ]), ['&' => '&amp;']);
        }, 'First', 'Previous', 'Next', 'Last');

        if ($the_pager) {
            $out .= '<p>';
            $out .= $the_pager;
            $out .= '</p>';
        }

        $out .= '<script>';
        $out .= <<<JS
const drop = document.querySelector('button[name=drop]');
const tableColumns = document.querySelector('#table-columns').textContent.trim();
const tableRows = document.querySelector('#table-rows').textContent.trim();
drop.disabled = false;
drop.addEventListener('click', dropTable, false);
drop.previousElementSibling.focus(); // Focus to the `insert` “button”
function dropTable(e) {
    if (window.confirm('Dropping a table is a dangerous action. We need to confirm that you consciously want to do so.')) {
        let table = window.prompt('Please write down the table name you want to drop:');
        if (table && table === this.value) {
            let rows = window.prompt('Please write down the number of rows in table “' + table + '”:');
            rows = rows + "";
            if ("" !== rows && rows === tableRows) {
                let columns = window.prompt('Please write down the number of columns in table “' + table + '”:');
                columns = columns + "";
                if ("" !== columns && columns === tableColumns) {
                    // Pass!
                } else {
                    window.alert('Wrong answer.');
                    e.preventDefault();
                }
            } else {
                window.alert('Wrong answer.');
                e.preventDefault();
            }
        } else {
            window.alert('Wrong answer.');
            e.preventDefault();
        }
    } else {
        e.preventDefault();
    }
}
JS;
        $out .= '</script>';

    } else {
        http_response_code(404);
        $out .= '<p>';
        $out .= 'Table <code>' . trim($name, '"') . '</code> does not exist.';
        $out .= '</p>';
    }
} else {
    if ('create' === $task) {
        $out .= '<p>';
        $out .= '<label for="' . ($id = 'f:' . substr(uniqid(), 6)) . '">';
        $out .= 'Table';
        $out .= '</label>';
        $out .= '<input autofocus id="' . $id . '" name="table" placeholder="FooBarBaz" pattern="' . $PATTERN_TABLE . '" required type="text">';
        $out .= '<button class="add" title="Add Column" type="button">';
        $out .= '&plus;';
        $out .= '</button>';
        $out .= '</p>';
        $out .= '<table>';
        $out .= '<thead>';
        $out .= '<tr>';
        $out .= '<th scope="col">';
        $out .= 'Key';
        $out .= '</th>';
        $out .= '<th scope="col">';
        $out .= 'Value';
        $out .= '</th>';
        $out .= '<th scope="col">';
        $out .= 'Type';
        $out .= '</th>';
        $out .= '<th scope="col">';
        $out .= 'Rules';
        $out .= '</th>';
        $out .= '</tr>';
        $out .= '</thead>';
        $out .= '<tbody id="columns">';
        $out .= '</tbody>';
        $out .= '<tfoot>';
        $out .= '<tr>';
        $out .= '<th scope="row">';
        $out .= 'Primary';
        $out .= '</th>';
        $out .= '<td colspan="3">';
        $out .= '<span id="keys" role="group">';
        $out .= '</span>';
        $out .= '</td>';
        $out .= '</tr>';
        $out .= '</tfoot>';
        $out .= '</table>';
        $out .= '<p>';
        $out .= '<button class="create" name="task" type="submit" value="create">';
        $out .= 'Create';
        $out .= '</button>';
        $out .= '</p>';
        $out .= '<hr>';
        $out .= '<h3>';
        $out .= 'Tips';
        $out .= '</h3>';
        $out .= '<ol>';
        $out .= '<li>';
        $out .= 'Add a column with type of <code>INTEGER</code> and default value of <code>FALSE</code> or <code>TRUE</code> to generate a toggle field. SQLite does not have native <code>BOOLEAN</code> type <a href="https://www.sqlite.org/datatype3.html#boolean_datatype" rel="nofollow" target="_blank">by design</a>, so the data you provide later will be stored as <code>0</code> for <code>false</code> and <code>1</code> for <code>true</code>.';
        $out .= '</li>';
        $out .= '<li>';
        $out .= 'Add a column with type of <code>TEXT</code> and default value of <code>CURRENT_DATE</code> or <code>CURRENT_TIME</code> or <code>CURRENT_TIMESTAMP</code> to generate a date/time field. SQLite also does not have types to handle date and time data natively, but it does have <a href="https://sqlite.org/syntax/literal-value.html" rel="nofollow" target="_blank">those literals</a> to store the current date and time as <code>INTEGER</code>, <code>REAL</code> or <code>TEXT</code>, depending on the type of column you provide.';
        $out .= '</li>';
        $out .= '<li>';
        $out .= 'Add a column with type of <code>TEXT</code> and default value of RGB color code in HEX format (e.g. <code>#000000</code>) to generate a color field.';
        $out .= '</li>';
        $out .= '<li>';
        $out .= 'Add a column with type of <code>TEXT</code> and select the 255 maximum characters length constraint to generate a text field.';
        $out .= '</li>';
        $out .= '</ol>';
        $out .= '<template id="column">';
        $out .= '<tr data-type="TEXT">';
        $out .= '<th scope="row" style="width: 1px;">';
        $out .= '<button class="remove" title="Remove Column" type="button">';
        $out .= '&minus;';
        $out .= '</button>';
        $out .= '<input name="columns[][key]" placeholder="fooBarBaz" pattern="' . $PATTERN_TABLE_COLUMN . '" required type="text">';
        $out .= '</th>';
        $out .= '<td style="width: 1px;">';
        $out .= '<button class="reset" title="Clear Value" type="button">';
        $out .= '&times;';
        $out .= '</button>';
        $out .= '<input name="columns[][value]" placeholder="NULL" type="text">';
        $out .= '</td>';
        $out .= '<td>';
        $types = ['BLOB', 'INTEGER', 'NULL', 'REAL', 'TEXT'];
        $out .= '<span role="group">';
        foreach ($types as $type) {
            $out .= '<label class="column-type:' . strtolower($type) . '">';
            $out .= '<input' . ('TEXT' === $type ? ' checked' : "") . ' name="columns[][type]" type="radio" value="' . $type . '">';
            $out .= ' ';
            $out .= '<span>';
            $out .= '<code>';
            $out .= $type;
            $out .= '</code>';
            $out .= '</span>';
            $out .= '</label>';
            $out .= ' ';
        }
        $out = rtrim($out);
        $out .= '</span>';
        $out .= '</td>';
        $out .= '<td>';
        $out .= '<span role="group">';
        $out .= '<label class="column-rule:not-null">';
        $out .= '<input name="columns[][rule][NOT NULL]" type="checkbox">';
        $out .= ' ';
        $out .= '<span>';
        $out .= '<code>';
        $out .= 'NOT NULL';
        $out .= '</code>';
        $out .= '</span>';
        $out .= '</label>';
        $out .= ' ';
        $out .= '<label class="column-rule:unique">';
        $out .= '<input name="columns[][rule][UNIQUE]" type="checkbox">';
        $out .= ' ';
        $out .= '<span>';
        $out .= '<code>';
        $out .= 'UNIQUE';
        $out .= '</code>';
        $out .= '</span>';
        $out .= '</label>';
        $out .= ' ';
        $out .= '<label class="column-rule:check-length-max-255">';
        $out .= '<input name="columns[][rule][CHECK][LENGTH(%s) &lt;= 255]" type="checkbox">';
        $out .= ' ';
        $out .= '<span>';
        $out .= '<code>';
        $out .= 'LENGTH(?) &lt;= 255';
        $out .= '</code>';
        $out .= '</span>';
        $out .= '</label>';
        $out .= '</span>';
        $out .= '</td>';
        $out .= '</tr>';
        $out .= '</template>';
        $out .= '<script>';
        $out .= <<<JS
const add = document.querySelector('.add');
const column = document.querySelector('#column');
const columns = document.querySelector('#columns');
const create = document.querySelector('.create');
const keys = document.querySelector('#keys');
add.addEventListener('click', addColumn, false);
let index = 0;
function addColumn() {
    let key = document.createElement('label'),
        node = column.content.cloneNode(true),
        remove = node.querySelector('.remove'),
        reset = node.querySelector('.reset');
    remove.addEventListener('click', removeColumn, false);
    reset.addEventListener('click', resetValue, false);
    node.querySelectorAll('[name*="[]"]').forEach(v => v.name = v.name.replace(/\[\]/g, '[' + index + ']'));
    columns.appendChild(node);
    if (node = columns.querySelector('tr:last-child th input[type="text"]')) {
        node.__key = key;
        node.addEventListener('focus', onUpdateKey, false);
        node.addEventListener('input', onUpdateKey, false);
        node.addEventListener('keydown', onUpdateKey, false);
        node.focus();
        keys.appendChild(key);
    }
    columns.querySelectorAll('tr:last-child td input[type="radio"]').forEach(v => v.addEventListener('change', onChangeType, false));
    ++index;
}
function onChangeType() {
    let type = this.value.split(/\s+/)[0];
    this.closest('tr').dataset.type = type;
}
function onUpdateKey() {
    let key = this.__key,
        value = this.value;
    key.innerHTML = "" !== value ? '<input name="primary[' + value + ']" type="checkbox"> <span>' + value + '</span>' : "";
}
function removeColumn() {
    this.parentNode.parentNode.remove();
}
function resetValue() {
    let next = this.nextElementSibling;
    next && (next.value = ""), next.focus();
}
JS;
        $out .= '</script>';
    } else {
        $out .= '<p>';
        $out .= 'I created this project to fulfill my curiosity about databases. If you find this project useful, please <a href="https://github.com/taufik-nurrohman/table-manager" target="_blank">rate it</a> or share it with your friends. But most importantly, please support me to develop <a href="https://github.com/mecha-cms" target="_blank">Mecha</a> further! Thank you 💕';
        $out .= '</p>';
        $out .= '<p>';
        $out .= 'Please select a table, or create a new one!';
        $out .= '</p>';
        $out .= '<hr>';
        $out .= '<p>';
        $out .= '<small>';
        $out .= '&copy; 2022 &middot; <a href="https://github.com/taufik-nurrohman" target="_blank">SQLite Table Manager</a>';
        $out .= '</small>';
        $out .= '</p>';
    }
}

$out .= '</main>';
$out .= '<aside>';

$out .= '<h3>';
$out .= 'Tables';
$out .= '</h3>';

if ($tables = Base::query('SELECT "name" FROM "sqlite_master" WHERE "type" = "table" AND "name" NOT LIKE "sqlite_%" ORDER BY "name" ASC')->get()) {
    $out .= '<ul>';
    foreach ($tables as $table) {
        $n = $table->name;
        $out .= '<li>';
        $out .= '<a' . ($n === ($_GET['table'] ?? "") ? ' aria-current="true"' : "") . ' href="' . $path() . $query([
            'chunk' => null,
            'column' => null,
            'part' => null,
            'row' => null,
            'sort' => null,
            'table' => $n,
            'task' => null
        ]) . '">';
        $out .= $n;
        $out .= '</a>';
        $out .= '</li>';
    }
    $out .= '</ul>';
} else {
    $out .= '<p>';
    $out .= '<i aria-label="Empty Data" role="status">';
    $out .= 'EMPTY';
    $out .= '</i>';
    $out .= '</p>';
}

if (!isset($_GET['task'])) {
    $out .= '<p>';
    $out .= '<a href="' . $path() . $query([
        'chunk' => null,
        'column' => null,
        'part' => null,
        'row' => null,
        'sort' => null,
        'table' => null,
        'task' => 'create'
    ]) . '" role="button">';
    $out .= 'Create';
    $out .= '</a>';
    $out .= '</p>';
}

$out .= '</aside>';
$out .= '</form>';
$out .= '</body>';
$out .= '</html>';

echo $out;

unset($_SESSION[$SESSION]);

exit;