<?php session_start();

error_reporting(E_ALL | E_STRICT);

ini_set('display_errors', true);
ini_set('display_startup_errors', true);
ini_set('error_log', __DIR__ . '/errors.log');
ini_set('html_errors', 1);

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
    // Trim white-space and normalize line-break
    $v = trim(strtr($v, ["\r\n" => "\n", "\r" => "\n"]));
    if (is_numeric($v)) {
        $v = false !== strpos($v, '.') ? (float) $v : (int) $v;
    } else {
        $v = $values[$v] ?? $v;
    }
});

// Naming best practice(s):
// - Treat table name as PHP class (pascal case)
// - Treat table column name as class property (camel case)

$FILE = __DIR__ . '/table.db';
$ID = 'ID'; // Primary column
$PATTERN_TABLE = "^[A-Z][a-z\\d]*(?:_?[A-Z\\d][a-z\\d]*)*$";
$PATTERN_TABLE_COLUMN = "^[A-Za-z][A-Za-z\\d]*(?:_?[A-Za-z\\d][a-z\\d]*)*$";
$SESSION = 'STATUS';
$TRUNCATE = 50;

$PATH = trim(strtr(strtr(__DIR__ . '/', "\\", '/'), [strtr($_SERVER['DOCUMENT_ROOT'], "\\", '/') => '/']), '/');
$PATH = "" !== $PATH ? '/' . $PATH . '/index.php' : '/index.php';

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

$stringify = static function(string $value) {
    return '"' . strip_tags(strtr($value, ['"' => '""'])) . '"';
};

if (!is_file($FILE)) {
    $_SESSION[$SESSION] = 'Table does not exist. Automatically create a table for you.';
}

try {
    new Pixie\Connection('sqlite', [
        'database' => $FILE,
        'driver' => 'sqlite'
    ], 'Base');
} catch (Exception $e) {
    echo ($_SESSION[$SESSION] = strtr($e->getMessage(), ["\n" => '<br>']));
    exit;
}

if ('POST' === $_SERVER['REQUEST_METHOD']) {
    if (isset($_POST['drop'])) {
        Base::query('DROP TABLE ' . $stringify($table = $_POST['drop']))->get();
        if ($errors = Base::pdo()->errorInfo()) {
            if ('00000' === $errors[0]) {
                $_SESSION[$SESSION] = 'Dropped table <code>' . $table . '</code>.';
                header('location: ' . $path());
                exit;
            }
            $_SESSION[$SESSION] = 'Could not drop table <code>' . $table . '</code>.';
            foreach ($errors as $k => $v) {
                $_SESSION['status'] .= '<br><b>DEBUG(' . $k . '):</b> ' . $v;
            }
        }
        header('location: ' . $path());
        exit;
    }
    if ('create' === $_POST['task']) {
        $columns = [];
        foreach ($_POST['columns'] ?? [] as $k => $v) {
            $rules = "";
            $key = $v['key'];
            $type = sprintf($v['type'], $v['rule']['DEFAULT'] ?? 'NULL', $stringify($key));
            foreach ($v['rule'] ?? [] as $kk => $vv) {
                if (false !== strpos($type, ' ' . $kk)) {
                    continue;
                }
                if ('DEFAULT' === $kk) {
                    if ("" === $vv || 'NULL' === $vv) {
                        continue;
                    }
                    $rules .= ' DEFAULT ' . ('CURRENT_DATE' === $vv || 'CURRENT_TIME' === $vv || 'CURRENT_TIMESTAMP' === $vv || is_numeric($vv) ? $vv : $stringify($vv));
                } else {
                    $rules .= ' ' . $kk;
                }
            }
            $columns[$key] = trim($stringify($key) . ' ' . $type . $rules);
        }
        $stmt = 'CREATE TABLE ' . $stringify($table = $_POST['table']);
        $columns[$ID] = '"' . $ID . '" INTEGER PRIMARY KEY'; // `ID` column is hard-coded
        ksort($columns);
        $stmt .= ' (' . implode(', ', $columns) . ')';
        Base::query($stmt)->get();
        if ($errors = Base::pdo()->errorInfo()) {
            if ('00000' === $errors[0]) {
                $_SESSION[$SESSION] = 'Created table <code>' . $table . '</code>.';
                header('location: ' . $path());
                exit;
            }
            $_SESSION['status'] = 'Could not create table <code>' . $table . '</code>.';
            foreach ($errors as $k => $v) {
                $_SESSION['status'] .= '<br><b>DEBUG(' . $k . '):</b> ' . $v;
            }
        }
        header('location: ' . $path());
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
  font: normal normal 18px/1.4 sans-serif;
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
button {
  background: #def;
  border: 2px solid #000;
  color: inherit;
  cursor: pointer;
  display: inline-block;
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
code {
  color: #909;
  font-family: monospace;
}
i {
  font-style: italic;
}
input[type='number'],
input[type='search'],
input[type='text'],
select,
textarea {
  background: #fff;
  border: 2px solid #000;
  display: inline-block;
  font-weight: normal;
  padding: .25em .5em;
  vertical-align: middle;
  width: 15em;
}
input[type='checkbox'],
input[type='radio'] {
  appearance: none;
  border: 2px solid;
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
  outline: 0;
}
input[type='checkbox']:checked,
input[type='radio']:checked {
  background: #00f;
  box-shadow: inset 0 0 0 3px #fff;
}
input[type='radio'] {
  border-radius: 100%;
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
label > input[type='checkbox']:focus + span,
label > input[type='radio']:focus + span {
  color: #00f;
}
label > input[type='text'] {
  border: 0;
  padding: 0;
}
label + label {
  margin-left: .5em;
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
  margin-left: 1em;
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
td,
th {
  border: 1px solid;
  padding: .25em .5em;
  text-align: left;
  vertical-align: top;
}
th {
  background: #fed;
}
[role='alert'] {
  background: #fe9;
  padding: .5em .75em;
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
#table-rows-container li,
#table-rows-container p,
#table-rows-container ul {
  list-style: none;
  margin: 0;
  padding: 0;
}
#table-rows-container ul ul {
  margin-left: 1.5em;
}
#table-rows-container ul label {
  align-items: start;
  cursor: pointer;
  display: flex;
  gap: .5em;
  user-select: none;
}
:disabled {
  cursor: not-allowed;
  opacity: .5;
}
:focus:invalid {
  border-color: #f00;
  color: #f00;
  outline-color: #f00;
}
[hidden] {
  display: none !important;
}
tr[data-type="BLOB"] .column-rule\:default,
tr[data-type="BLOB"] .column-rule\:default + br {
  display: none;
}
tr[data-type="BOOLEAN"] .column-rule\:not-null,
tr[data-type="BOOLEAN"] .column-rule\:not-null + br,
tr[data-type="BOOLEAN"] .column-rule\:unique,
tr[data-type="BOOLEAN"] .column-rule\:unique + br {
  display: none;
}
tr[data-type="NULL"] .column-rule\:default,
tr[data-type="NULL"] .column-rule\:default + br,
tr[data-type="NULL"] .column-rule\:not-null,
tr[data-type="NULL"] .column-rule\:not-null + br,
tr[data-type="NULL"] .column-rule\:unique,
tr[data-type="NULL"] .column-rule\:unique + br {
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
    $out .= '<p role="alert">';
    $out .= $_SESSION[$SESSION];
    $out .= '</p>';
}

$out .= '<form action="' . $path() . '" enctype="multipart/form-data" method="post">';
$out .= '<main>';

if (!empty($_GET['table'])) {
    $name = $stringify($_GET['table']);
    if ('insert' === ($_GET['task'] ?? 0)) {
        $out .= '<b>TODO:</b> Add insert form.';
    } else if (array_key_exists('row', $_GET)) {
        if ($row = Base::table(trim($name, '"'))->find($_GET['row'] ?? 0, $ID)) {
            $sql = Base::table('sqlite_master')->select('sql')->where('tbl_name', '=', trim($name, '"'))->first()->sql ?? "";
            $sql_columns = substr($sql, strpos($sql, '(') + 1, -1);
            $out .= '<h3>';
            $out .= 'Edit Row';
            $out .= '</h3>';
            foreach ($row as $k => $v) {
                if ($ID === $k) {
                    continue;
                }
                $type = 'TEXT';
                $default = null;
                $out .= '<p>';
                $out .= '<small>';
                $out .= $k;
                $out .= '</small>';
                $out .= '<br>';
                if ($sql_columns && preg_match('/"' . preg_quote($k) . '"\s+(\w+)(?:\s+DEFAULT\s+(".*?"|[^\s,]+))?/', $sql_columns, $m)) {
                    // var_dump($m);
                    $type = $m[1] ?? 'TEXT';
                }
                $vv = htmlspecialchars($v);
                if ('BLOB' === $type) {
                    $out .= '<input name="x" type="file">';
                } else if ('BOOLEAN' === $type) {
                    $out .= '<label>';
                    $out .= '<input' . (0 === $v ? ' checked' : "") . ' name="x" type="radio" value="0">';
                    $out .= ' ';
                    $out .= '<span>';
                    $out .= 'No';
                    $out .= '</span>';
                    $out .= '</label>';
                    $out .= '<label>';
                    $out .= '<input' . (1 === $v ? ' checked' : "") . ' name="x" type="radio" value="1">';
                    $out .= ' ';
                    $out .= '<span>';
                    $out .= 'Yes';
                    $out .= '</span>';
                    $out .= '</label>';
                } else if ('INTEGER' === $type) {
                    $out .= '<input name="x" type="number" placeholder="' . $vv . '" value="' . $vv . '">';
                } else {
                    $out .= '<textarea name="x" placeholder="' . explode("\n", $vv)[0] . '">';
                    $out .= $vv;
                    $out .= '</textarea>';
                }
                $out .= '</p>';
            }
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
        $out .= '<table>';
        $out .= '<thead>';
        $out .= '<tr>';

        foreach ($table as $v) {
            if ($ID !== ($n = $v->name)) {
                $fields[] = 'SUBSTR(' . $stringify($n) . ', 1, ' . $TRUNCATE . ') AS ' . $stringify($n);
            } else {
                $fields[] = $stringify($n);
            }
            $out .= '<th>';
            $out .= '<a' . ($n === ($_GET['sort'][1] ?? $ID) ? ' aria-current="true"' : "") . ' href="' . $path() . $query([
                'sort' => [1 === ($_GET['sort'][0] ?? -1) ? -1 : 1, $n]
            ]) . '">';
            $out .= $n;
            if (!empty($v->pk)) {
                $out .= '<small aria-label="Primary key" role="status">';
                $out .= '*';
                $out .= '</small>';
            }
            $out .= '</a>';
            $out .= '</th>';
        }

        $out .= '</tr>';
        $out .= '</thead>';
        $out .= '<tbody>';

        if (0 === $rows) {
            $out .= '<tr>';
            $out .= '<td colspan="' . $columns . '" style="text-align: center;">';
            $out .= '<i aria-label="No rows yet." role="status">';
            $out .= 'EMPTY';
            $out .= '</i>';
            $out .= '</td>';
            $out .= '</tr>';
        } else {
            sort($fields);
            $rows = Base::query('SELECT ' . implode(', ', $fields) . ' FROM ' . $name . ' ORDER BY ' . $stringify($_GET['sort'][1] ?? $ID) . ' ' . (1 === ($_GET['sort'][0] ?? -1) ? 'ASC' : 'DESC') . ' LIMIT ' . ($chunk = $_GET['chunk'] ?? 20) . ' OFFSET ' . ($chunk * (($_GET['part'] ?? 1) - 1)))->get();
            foreach ($rows as $row) {
                $out .= '<tr>';
                foreach ($row as $k => $v) {
                    $out .= '<td>';
                    if ($ID === $k) {
                        $out .= '<a href="' . $path() . $query([
                            'chunk' => null,
                            'part' => null,
                            'row' => $v,
                            'sort' => null,
                            'table' => $_GET['table'],
                            'task' => null
                        ]) . '">';
                    }
                    if (null === $v) {
                        $out .= '<i aria-label="Null value" role="status">';
                        $out .= 'NULL';
                        $out .= '</i>';
                    } else if ("" === $v) {
                        $out .= '<i aria-label="Empty string value" role="status">';
                        $out .= 'EMPTY';
                        $out .= '</i>';
                    }
                    $out .= $TRUNCATE === strlen($v) ? htmlspecialchars($v) . '&hellip;' : htmlspecialchars($v);
                    if ($ID === $k) {
                        $out .= '</a>';
                    }
                    $out .= '</td>';
                }
                $out .= '</tr>';
            }
        }

        $out .= '</tbody>';
        $out .= '</table>';

        $the_pager = $pager($_GET['part'] ?? 1, Base::table(trim($name, '"'))->count(), $_GET['chunk'] ?? 20, 2, static function($part) use($ID, $path, $query) {
            return $path() . strtr($query([
                'chunk' => $_GET['chunk'] ?? 20,
                'part' => $part,
                'sort' => $_GET['sort'] ?? [-1, $ID]
            ]), ['&' => '&amp;']);
        }, 'First', 'Previous', 'Next', 'Last');

        if ($the_pager) {
            $out .= '<p>';
            $out .= $the_pager;
            $out .= '</p>';
        }

        $out .= '<p>';
        $out .= '<a href="' . $path() . $query([
            'chunk' => null,
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
        $out .= '<script>';
        $out .= <<<JS
const drop = document.querySelector('button[name=drop]');
const tableColumns = document.querySelector('#table-columns').textContent.trim();
const tableRows = document.querySelector('#table-rows').textContent.trim();
drop.disabled = false;
drop.addEventListener('click', dropTable, false);
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
    $task = $_GET['task'] ?? null;
    if ('create' === $task) {
        $out .= '<h3>';
        $out .= 'Create Table';
        $out .= '</h3>';
        $out .= '<p>';
        $out .= '<label for="' . ($id = 'f:' . substr(uniqid(), 6)) . '">';
        $out .= 'Name';
        $out .= '</label>';
        $out .= '<input autofocus class="table" id="' . $id . '" name="table" placeholder="FooBarBaz" pattern="' . $PATTERN_TABLE . '" required type="text">';
        $out .= ' ';
        $out .= '<button class="add" disabled title="Add Column" type="button">';
        $out .= '&plus;';
        $out .= '</button>';
        $out .= '<table>';
        $out .= '<thead>';
        $out .= '<tr>';
        $out .= '<th scope="col">';
        $out .= 'Name';
        $out .= '</th>';
        $out .= '<th scope="col">';
        $out .= 'Type';
        $out .= '</th>';
        $out .= '</tr>';
        $out .= '</thead>';
        $out .= '<tbody id="columns">';
        $out .= '<tr data-type="INTEGER">';
        $out .= '<th scope="row">';
        $out .= 'ID';
        $out .= '<small aria-label="Primary key" role="status">';
        $out .= '*';
        $out .= '</small>';
        $out .= '</th>';
        $out .= '<td>';
        $out .= '<label class="column-type:integer">';
        $out .= '<input checked disabled type="radio">';
        $out .= ' ';
        $out .= '<span>';
        $out .= 'Integer';
        $out .= '</span>';
        $out .= '</label>';
        $out .= '<br>';
        $out .= '<label class="column-rule:primary-key">';
        $out .= '<input checked disabled type="checkbox">';
        $out .= ' ';
        $out .= '<span>';
        $out .= 'Primary Key';
        $out .= '</span>';
        $out .= '</label>';
        $out .= '</td>';
        $out .= '</tr>';
        $out .= '</tbody>';
        $out .= '</table>';
        $out .= '<p>';
        $out .= '<button class="create" disabled name="task" type="submit" value="create">';
        $out .= 'Create';
        $out .= '</button>';
        $out .= '</p>';
        $out .= '<template id="column">';
        $out .= '<tr data-type="TEXT">';
        $out .= '<th scope="row">';
        $out .= '<button class="remove" title="Remove Column" type="button">';
        $out .= '&minus;';
        $out .= '</button>';
        $out .= ' ';
        $out .= '<input name="columns[][key]" placeholder="fooBarBaz" pattern="' . $PATTERN_TABLE_COLUMN . '" required type="text">';
        $out .= '</th>';
        $out .= '<td>';
        $types = [
            'BLOB' => 'Binary',
            'NULL' => 'Null',
            'BOOLEAN DEFAULT 0 CHECK(%2$s IN (0, 1))' => 'Boolean',
            'NUMBER' => 'Integer',
            'REAL' => 'Float',
            'TEXT' => 'String'
        ];
        asort($types);
        foreach ($types as $k => $v) {
            $out .= '<label class="column-type:' . strtolower(explode(' ', $k)[0]) . '">';
            $out .= '<input' . ('TEXT' === $k ? ' checked' : "") . ' name="columns[][type]" type="radio" value="' . $k . '">';
            $out .= ' ';
            $out .= '<span>';
            $out .= $v;
            $out .= '</span>';
            $out .= '</label>';
        }
        $out .= '<br>';
        $out .= '<label class="column-rule:not-null">';
        $out .= '<input name="columns[][rule][NOT NULL]" type="checkbox">';
        $out .= ' ';
        $out .= '<span>';
        $out .= 'Not Null';
        $out .= '</span>';
        $out .= '</label>';
        $out .= '<br>';
        $out .= '<label class="column-rule:unique">';
        $out .= '<input name="columns[][rule][UNIQUE]" type="checkbox">';
        $out .= ' ';
        $out .= '<span>';
        $out .= 'Unique';
        $out .= '</span>';
        $out .= '</label>';
        $out .= '<br>';
        $out .= '<label class="column-rule:default">';
        $out .= '<input name="columns[][rule][DEFAULT]" placeholder="Default" type="text">';
        $out .= '</label>';
        $out .= '</td>';
        $out .= '</tr>';
        $out .= '</template>';
        $out .= '<script>';
        $out .= <<<JS
const add = document.querySelector('.add');
const column = document.querySelector('#column');
const columns = document.querySelector('#columns');
const create = document.querySelector('.create');
const table = document.querySelector('.table');
add.addEventListener('click', addColumn, false);
table.addEventListener('input', onChangeValue, false);
table.addEventListener('keydown', onChangeValue, false);
let index = 0;
function addColumn() {
   let node = column.content.cloneNode(true),
       remove = node.querySelector('.remove');
    remove.addEventListener('click', removeColumn, false);
    node.querySelectorAll('[name*="[]"]').forEach(v => v.name = v.name.replace(/\[\]/g, '[' + index + ']'));
    columns.appendChild(node);
    node = columns.querySelector('tr:last-child th input[type="text"]');
    node.addEventListener('input', onChangeValue, false);
    node.addEventListener('keydown', onChangeValue, false);
    node.focus();
    columns.querySelectorAll('tr:last-child td input[type="radio"]').forEach(v => v.addEventListener('change', onChangeType, false));
    create.disabled = true;
    ++index;
}
function onChangeType() {
    let type = this.value.split(/\s+/)[0];
    this.closest('tr').dataset.type = type;
}
function onChangeValue() {
    add.disabled = create.disabled = !this.validity.valid;
}
function removeColumn() {
    this.parentNode.parentNode.remove();
}
JS;
        $out .= '</script>';
    } else {
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

if ($tables = Base::query("SELECT name FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%'")->get()) {
    $out .= '<ul>';
    foreach ($tables as $table) {
        $out .= '<li>';
        $out .= '<a' . ($table->name === ($_GET['table'] ?? "") ? ' aria-current="true"' : "") . ' href="' . $path() . $query([
            'chunk' => null,
            'part' => null,
            'row' => null,
            'sort' => null,
            'table' => $table->name,
            'task' => null
        ]) . '">';
        $out .= $table->name;
        $out .= '</a>';
        $out .= '</li>';
    }
    $out .= '</ul>';
}

if (!isset($_GET['task'])) {
    $out .= '<p>';
    $out .= '<a href="' . $path() . $query([
        'chunk' => null,
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

if ('POST' === $_SERVER['REQUEST_METHOD']) {
    $table = $_POST['table'] ?? null;
    $task = $_POST['task'] ?? null;
    if ('create' === $task) {
    }
    if ('insert' === $task) {
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
        if (Base::table($table)->insert($values)) {
            $_SESSION['status'] = 'Inserted 1 row to table <code>' . $table . '</code>.';
            header('location: ' . $path . query([
                'table' => $table,
                'task' => 'select'
            ]));
            exit;
        }
        $_SESSION['status'] = 'Could not insert row into table <code>' . $table . '</code>.';
        if ($errors = Base::pdo()->errorInfo()) {
            foreach ($errors as $k => $v) {
                $_SESSION['status'] .= '<br><b>DEBUG(' . $k . '):</b> ' . $v;
            }
        }
        header('location: ' . $path . $query);
        exit;
    }
    if ('delete' === $task) {
        $query = query([
            'table' => $table,
            'task' => 'select'
        ]);
        if (isset($_POST['row'])) {
            if (Base::table($table)->where('ID', '=', $id = (int) $_POST['row'])->delete()) {
                $_SESSION['status'] = 'Deleted 1 row with ID <code>' . $id . '</code> in table <code>' . $table . '</code>.';
                header('location: ' . $path . $query);
                exit;
            }
            $_SESSION['status'] = 'Could not delete row with ID <code>' . $id . '</code> in table <code>' . $table . '</code>.';
            if ($errors = Base::pdo()->errorInfo()) {
                foreach ($errors as $k => $v) {
                    $_SESSION['status'] .= '<br><b>DEBUG(' . $k . '):</b> ' . $v;
                }
            }
            header('location: ' . $path . $query);
            exit;
        }
    }
    if (isset($_POST['drop'])) {
    }
    if (isset($_POST['select'])) {
        header('location: ' . $path . query([
            'table' => $_POST['select'],
            'task' => 'select'
        ]));
        exit;
    }
} else {
    if (isset($_GET['task']) && 'list' === $_GET['task']) {
        // Redirect to home page
        header('location: ' . $path);
        exit;
    }
}

?>
<!DOCTYPE html>
<html dir="ltr">
  <head>
    <meta content="width=device-width">
    <meta charset="utf-8">
    <title>
      Table Management System
    </title>
    <style></style>
  </head>
  <body>
    <?php $task_default = ($task = $_GET['task'] ?? null) ?? 'create'; ?>
    <?php if (isset($_SESSION['status'])): ?>
      <p role="alert">
        <?= $_SESSION['status']; ?>
      </p>
    <?php endif; ?>
    <form action="<?= $path; ?>" enctype="multipart/form-data" method="post">
      <?php if ($task): ?>
        <?php if ('create' === $task): ?>
          <p>
            <label>
              <b>Table Name</b>
            </label>
            <p>
              <input autofocus id="table-name" name="table" pattern="^[A-Z_][a-zA-Z\d_]*(?:_[A-Z\d][a-zA-Z\d]*)*$" placeholder="FooBarBaz" required type="text">
            </p>
          </p>
          <p>
            <label>
              <b>Table Columns</b>
            </label>
          </p>
          <table hidden>
            <thead>
              <th>
                Name
              </th>
              <th>
                Type
              </th>
              <th>
                &hellip;
              </th>
            </thead>
            <tbody id="table-rows-container"></tbody>
          </table>
          <p>
            <button class="add-table-column" disabled type="button">
              Add Column
            </button>
            <button class="create-table" disabled name="task" type="submit" value="create">
              Create Table
            </button>
          </p>
          <template id="table-column-template">
            <tr>
              <td>
                <input name="keys[key][]" pattern="^[a-zA-Z_][a-zA-Z\d_]*(?:_[a-zA-Z\d]*)*$" placeholder="fooBarBaz" required type="text">
              </td>
              <td>
                <ul>
                  <?php

                  $types = [
                      'BLOB' => 'Binary',
                      'BOOLEAN DEFAULT 0 CHECK (%s IN (0, 1))' => 'Boolean',
                      'INTEGER' => 'Integer',
                      'NULL' => 'Null',
                      'REAL' => 'Float',
                      'TEXT' => 'String'
                  ];

                  asort($types);

                  ?>
                  <?php foreach ($types as $k => $v): ?>
                    <li>
                      <p>
                        <label>
                          <input class="the-table-column"<?= 'TEXT' === $k ? ' checked' : ""; ?> name="keys[type][]" type="radio" value="<?= htmlspecialchars($k); ?>">
                          <span>
                            <?= $v; ?>
                          </span>
                        </label>
                      </p>
                      <p<?= 'TEXT' === $k ? "" : ' hidden'; ?>>
                        <b>Rules</b>
                      </p>
                      <ul<?= 'TEXT' === $k ? "" : ' hidden'; ?>>
                        <?php if ('Boolean' === $v): ?>
                          <li>
                            <label>
                              <span>Default:</span>
                              <input max="1" min="0" name="keys[value][]" step="1" type="number" value="0">
                            </label>
                          </li>
                        <?php elseif ('Null' === $v): ?>
                          <li>
                            <i>None.</i>
                          </li>
                        <?php else: ?>
                          <?php if ('NUMBER' === $k || 'REAL' === $k): ?>
                            <li>
                              <label>
                                <input name="keys[rules][][AUTOINCREMENT]" type="checkbox">
                                <span>
                                  Automatically increment the value on this field if it was not set explicitly by the user. The automatic value is made based on the last number that has been inserted in the previous action.
                                </span>
                              </label>
                            </li>
                          <?php endif; ?>
                          <li>
                            <label>
                              <input name="keys[rules][][NOT NULL]" type="checkbox">
                              <span>
                                Force user to provide specific data on this field, or else the insertion process will be rejected.
                              </span>
                            </label>
                          </li>
                          <li>
                            <label>
                              <input name="keys[rules][][UNIQUE]" type="checkbox">
                              <span>
                                Make sure this field rejects the given value if it already exists in other records.
                              </span>
                            </label>
                          </li>
                        <?php endif; ?>
                      </ul>
                    </li>
                  <?php endforeach; ?>
                </ul>
              </td>
              <td>
                <button class="remove-table-column" type="button">
                  Remove
                </button>
              </td>
            </tr>
          </template>
          <script>

          const tableColumnAdd = document.querySelector('.add-table-column');
          const tableColumnTemplate = document.querySelector('#table-column-template');
          const tableCreate = document.querySelector('.create-table');
          const tableName = document.querySelector('#table-name');
          const tableRowsContainer = document.querySelector('#table-rows-container');

          let index = 0;

          function addColumn() {
              tableRowsContainer.appendChild(tableColumnTemplate.content.cloneNode(true));
              setTimeout(() => {
                  document.querySelectorAll('[name]').forEach(input => {
                      input.name = input.name.replace(/\[\]/g, '[' + index + ']');
                  });
                  let focus = [...tableRowsContainer.querySelectorAll('input[type="text"]')].pop();
                  focus && focus.focus();
                  document.querySelectorAll('.remove-table-column:not(.has-event)').forEach(remove => {
                      remove.addEventListener('click', removeColumn, false);
                      remove.classList.add('has-event');
                  });
                  document.querySelectorAll('.the-table-column:not(.has-event)').forEach(the => {
                      the.addEventListener('change', changeType, false);
                      the.classList.add('has-event');
                  });
                  ++index;
              }, 1);
              checkTable();
          }

          function changeType() {
              document.querySelectorAll('.the-table-column').forEach(the => {
                  the.parentNode.parentNode.nextElementSibling.hidden = true;
                  the.parentNode.parentNode.nextElementSibling.nextElementSibling.hidden = true;
              });
              this.parentNode.parentNode.nextElementSibling.hidden = false;
              this.parentNode.parentNode.nextElementSibling.nextElementSibling.hidden = false;
          }

          function checkTable() {
              tableRowsContainer.closest('table').hidden = 0 === tableRowsContainer.children.length;
          }

          function checkTableName() {
              let valid = tableName.validity && tableName.validity.valid;
              tableColumnAdd.disabled = !valid;
              tableCreate.disabled = !valid;
          }

          function removeColumn() {
              this.closest('tr').remove();
              checkTable();
              --index;
          }

          tableColumnAdd.addEventListener('click', addColumn, false);

          tableName.addEventListener('input', checkTableName, false);
          tableName.addEventListener('keyup', checkTableName, false);

          </script>
        <?php elseif ('insert' === $task): ?>
          <?php if ($table = Base::query('PRAGMA table_info(' . strtr($_GET['table'], ['"' => '""']) . ')')->get()): ?>
            <input name="table" type="hidden" value="<?= $_GET['table']; ?>">
            <table>
              <thead>
                <tr>
                  <?php foreach ($table as $k => $v): ?>
                    <th>
                      <?= $v->name; ?><?= '1' === $v->pk ? '<small aria-label="Primary Key" role="status">*</small>' : ""; ?>
                    </th>
                  <?php endforeach; ?>
                  <th>
                    &hellip;
                  </th>
                </tr>
              </thead>
              <tfoot>
                <tr>
                  <?php foreach ($table as $k => $v): ?>
                    <?php if ('ID' === $v->name): ?>
                      <td></td>
                    <?php else: ?>
                      <td>
                        <?php if ('BLOB' === $v->type): ?>
                          <input name="values[<?= $v->name; ?>]" style="display: block; width: 100%;" type="file">
                        <?php elseif ('INTEGER' === $v->type): ?>
                          <input name="values[<?= $v->name; ?>]" placeholder="<?= $v->dflt_value ?? ""; ?>" style="display: block; width: 100%;" type="number">
                        <?php elseif ('NULL' === $v->type): ?>
                          <em>NULL</em>
                        <?php elseif ('REAL' === $v->type): ?>
                          <input name="values[<?= $v->name; ?>]" placeholder="<?= $v->dflt_value ?? ""; ?>" style="display: block; width: 100%;" type="number">
                        <?php else: ?>
                          <textarea name="values[<?= $v->name; ?>]" placeholder="<?= $v->dflt_value ?? ""; ?>" style="display: block; width: 100%;"></textarea>
                        <?php endif; ?>
                      </td>
                    <?php endif; ?>
                  <?php endforeach; ?>
                  <td>
                    <button name="task" type="submit" value="insert">
                      Insert
                    </button>
                  </td>
                </tr>
              </tfoot>
            </table>
          <?php else: ?>
            <p>
              Table <code>
                <?= $_GET['table']; ?>
              </code> does not exist.
            </p>
          <?php endif; ?>
        <?php elseif ('select' === $task): ?>
          <?php if ($table = Base::query('PRAGMA table_info(' . strtr($_GET['table'], ['"' => '""']) . ')')->get()): ?>
            <input name="table" type="hidden" value="<?= $_GET['table']; ?>">
            <input name="task" type="hidden" value="delete">
            <table>
              <?php $fields = []; ?>
              <thead>
                <tr>
                  <?php $sort = $_GET['sort'][0] ?? '-1'; ?>
                  <?php foreach ($table as $k => $v): ?>
                    <th>
                      <a<?= $v->name === ($_GET['sort'][1] ?? 'ID') ? ' aria-current="true"' : ""; ?> href="<?= $path . strtr(query([
                          'sort' => ['1' === $sort ? '-1' : '1', $v->name]
                      ]), ['&' => '&amp;']); ?>">
                        <?= $v->name; ?><?= '1' === $v->pk ? '<small aria-label="Primary Key" role="status">*</small>' : ""; ?>
                      </a>
                    </th>
                    <?php $fields[] = 'SUBSTR(' . $v->name . ', 1, 50)'; ?>
                  <?php endforeach; ?>
                  <th>
                    &hellip;
                  </th>
                </tr>
              </thead>
              <?php if ($rows = Base::query('SELECT ID, ' . implode(', ', $fields) . ' FROM "' . strtr($_GET['table'], ['"' => '""']) . '" ORDER BY "' . strtr($_GET['sort'][1] ?? 'ID', ['"' => '""']) . '" ' . ('1' === ($_GET['sort'][0] ?? '-1') ? 'ASC' : 'DESC') . ' LIMIT ' . ($chunk = (int) ($_GET['chunk'] ?? 20)) . ' OFFSET ' . ($chunk * (((int) ($_GET['part'] ?? 1)) - 1)))->get()): ?>
                <tbody>
                  <?php foreach ($rows as $k => $v): ?>
                    <tr>
                      <?php foreach ($v as $kk => $vv): ?>
                        <?php if ('ID' === $kk) continue; ?>
                        <td>
                          <?= 50 === strlen($vv) ? htmlspecialchars($vv) . '&hellip;' : htmlspecialchars($vv); ?>
                        </td>
                      <?php endforeach; ?>
                      <td>
                        <button name="row" type="submit" value="<?= $v->ID; ?>">
                          Delete
                        </button>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              <?php endif; ?>
            </table>
            <?php $pager = pager((int) ($_GET['part'] ?? 1), Base::table($_GET['table'])->count(), (int) ($_GET['chunk'] ?? 20), 2, static function($part) use($path) {
                return $path . strtr(query([
                    'chunk' => $_GET['chunk'] ?? 20,
                    'part' => $part,
                    'sort' => $_GET['sort'] ?? ['-1', 'ID'],
                    'task' => 'select'
                ]), ['&' => '&amp;']);
            }, 'First', 'Previous', 'Next', 'Last'); ?>
            <?php if ($pager): ?>
              <p>
                <?= $pager; ?>
              </p>
            <?php endif; ?>
          <?php else: ?>
            <p>
              Table <code>
                <?= $_GET['table']; ?>
              </code> does not exist.
            </p>
          <?php endif; ?>
        <?php elseif ('update' === $task): ?>
        <?php endif; ?>
      <?php else: ?>
        <?php if ($tables = Base::query("SELECT name FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%'")->get()): ?>
          <table>
            <thead>
              <tr>
                <th>
                  Tables
                </th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($tables as $k => $v): ?>
                <tr>
                  <td>
                    <span>
                      <?= $v->name; ?>
                    </span>
                    <br>
                    <?php $columns = count((array) Base::query('PRAGMA table_info("' . strtr($v->name, ['"' => '""']) . '")')->get()); ?>
                    <?php $rows = Base::table($v->name)->count(); ?>
                    <small>
                      <?= $columns . ' Column' . (1 === $columns ? "" : 's'); ?>, <?= $rows . ' Row' . (1 === $rows ? "" : 's'); ?>
                    </small>
                  </td>
                  <td>
                    <button name="select" type="submit" value="<?= $v->name; ?>">
                      Select
                    </button>
                    <button name="drop" onclick="return confirm('Are you sure you want to delete this table with its rows?')" type="submit" value="<?= $v->name; ?>">
                      Drop
                    </button>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        <?php else: ?>
          <p>
            No tables yet.
          </p>
        <?php endif; ?>
      <?php endif; ?>
    </form>
    <hr>
    <form action="<?= $path; ?>" method="get">
      <?php if ('create' === $task): ?>
        <button name="task" type="submit" value="list">
          Back
        </button>
      <?php elseif ('insert' === $task): ?>
        <button name="task" type="submit" value="select">
          Back
        </button>
        <input name="table" type="hidden" value="<?= $_GET['table']; ?>">
      <?php elseif ('select' === $task): ?>
        <button name="task" type="submit" value="list">
          Back
        </button>
        <button name="task" type="submit" value="insert">
          New Row
        </button>
        <input name="table" type="hidden" value="<?= $_GET['table']; ?>">
      <?php else: ?>
        <button name="task" type="submit" value="create">
          Create Table
        </button>
      <?php endif; ?>
    </form>
  </body>
</html>
<?php unset($_SESSION['status']); ?>