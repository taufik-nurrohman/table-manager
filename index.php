<?php session_start();

error_reporting(E_ALL | E_STRICT);
ini_set('display_errors', true);
ini_set('display_startup_errors', true);
ini_set('error_log', __DIR__ . '/errors.log');
ini_set('html_errors', 1);

$p = trim(strtr(strtr(__DIR__ . '/', "\\", '/'), [strtr($_SERVER['DOCUMENT_ROOT'], "\\", '/') => '/']), '/');
$p = "" !== $p ? '/' . $p . '/index.php' : '/index.php';

// Naming best practice(s):
// - Treat table name as PHP class (pascal case)
// - Treat table column name as class property (camel case)

final class Base {

    public static $base;
    public static $error;
    public static $path;

    public static function start(string $path = null) {
        self::$base = $base = new SQLite3(self::$path = $path);
        self::$error = false;
    }

    public static function alter(string $table) {
        return self::query('ALTER TABLE "' . strtr($table, ['"' => '""']) . '"');
    }

    public static function create(string $table, array $keys = []) {
        $query = 'CREATE TABLE "' . strtr($table, ['"' => '""']) . '"';
        unset($keys['ID']); // `ID` column is hard-coded
        if ($keys) {
            $keys['ID'] = 'INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL';
            $data = [];
            foreach ($keys as $k => $v) {
                $data[$k] = trim('"' . strtr($k, ['"' => '""']) . '" ' . $v);
            }
            ksort($data);
            $query .= ' (' . implode(', ', $data) . ')';
        }
        return self::query($query);
    }

    public static function delete(string $table, int $row) {
        return self::query('DELETE FROM "' . strtr($table, ['"' => '""']) . '" WHERE "ID" = ?', [$row]);
    }

    public static function drop(string $table) {
        return self::query('DROP TABLE "' . strtr($table, ['"' => '""']) . '"');
    }

    public static function insert(string $table, array $values = []) {
        $query = 'INSERT INTO "' . strtr($table, ['"' => '""']) . '"';
        if ($values) {
            ksort($values);
            foreach ($values as &$v) {
                if (is_string($v)) {
                    $v = "'" . strtr($v, ["'" => "''"]) . "'";
                } else if (false === $v) {
                    $v = 0; // Replace `false` with `0` because SQLite does not have support for native boolean type
                } else if (true === $v) {
                    $v = 1; // Replace `true` with `1` because SQLite does not have support for native boolean type
                }
            }
            return self::query($query . ' (' . implode(', ', array_keys($values)) . ') VALUES (' . implode(', ', array_values($values)) . ')');
        }
        return false;
    }

    public static function query(string $query, array $lot = []) {
        if (self::$error) {
            return false;
        }
        if (!$lot) {
            if (!$out = self::$base->query($query)) {
                self::$error = self::$base->lastErrorMsg();
                return false;
            }
            self::$error = null;
            return (object) $out;
        }
        $stmt = self::$base->prepare($query);
        foreach ($lot as $k => $v) {
            $type = SQLITE3_TEXT;
            if (is_float($v)) {
                $type = SQLITE3_FLOAT;
            } else if (is_bool($v) || is_int($v)) {
                $type = SQLITE3_INTEGER;
            } else if (is_null($v)) {
                $type = SQLITE3_NULL;
            } else if (is_string($v)) {
                $type = SQLITE3_TEXT;
            } else {
                $type = SQLITE3_BLOB;
            }
            // `[1, 2, 3]`
            if (array_keys($lot) === range(0, count($lot) - 1)) {
                $stmt->bindValue($k + 1, $v, $type);
            // `{foo: 1, bar: 2, baz: 3}`
            } else {
                $stmt->bindValue(':' . $k, $v, $type);
            }
        }
        if (!$out = $stmt->execute()) {
            self::$error = self::$base->lastErrorMsg();
            return false;
        }
        self::$error = null;
        return (object) $out;
    }

    public static function row(string $table, int $id, array $keys = []) {
        $out = [];
        ksort($keys);
        $values = self::query('SELECT ' . ($keys ? implode(', ', $keys) : '*') . ' FROM "' . strtr($table, ['"' => '""']) . '" WHERE "ID" = ?', [$id]);
        while ($value = $values->fetchArray(SQLITE3_ASSOC)) {
            ksort($value);
            $out[] = (object) $value;
        }
        return $out;
    }

    public static function rows(string $table, array $keys = []) {
        $out = [];
        ksort($keys);
        $values = self::query('SELECT ' . ($keys ? implode(', ', $keys) : '*') . ' FROM "' . strtr($table, ['"' => '""']) . '" ORDER BY "ID" DESC');
        while ($value = $values->fetchArray(SQLITE3_ASSOC)) {
            ksort($value);
            $out[] = (object) $value;
        }
        return $out;
    }

    public static function table(string $name) {
        $values = self::query('PRAGMA table_info("' . strtr($name, ['"' => '""']) . '")');
        $out = [];
        while ($value = $values->fetchArray(SQLITE3_NUM)) {
            $out[$value[1]] = $value[2];
        }
        ksort($out);
        return (object) $out;
    }

    public static function tables() {
        $values = self::query("SELECT name FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%'");
        $out = [];
        if ($values) {
            while ($value = $values->fetchArray(SQLITE3_NUM)) {
                $v = self::$base->querySingle('SELECT count("ID") FROM "' . strtr($value[0], ['"' => '""']) . '"');
                $out[$value[0]] = $v; // Total row(s) in table
            }
            ksort($out);
        }
        return (object) $out;
    }

    public static function update(string $table, int $row, array $values = []) {
        if ($values) {
            ksort($values);
            foreach ($values as &$v) {
                if (is_string($v)) {
                    $v = "'" . strtr($v, ["'" => "''"]) . "'";
                } else if (false === $v) {
                    $v = 0;
                } else if (true === $v) {
                    $v = 1;
                }
            }
            // TODO
            $query = 'UPDATE "' . strtr($table, ['"' => '""']) . '" SET ';
            return self::query("");
        }
        return false;
    }

}

if (!is_file($path = __DIR__ . '/table.db')) {
    $_SESSION['alert'] = 'Table does not exist. Automatically create table!';
}

Base::start($path);

if ($error = Base::$error) {
    $_SESSION['alert'] = $error;
}

if ('POST' === $_SERVER['REQUEST_METHOD']) {
    $task = $_POST['task'];
    if ('create' === $task) {
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
        foreach ($_POST['keys']['key'] as $k => $v) {
            $rules = "";
            foreach ($_POST['keys']['rules'][$k] ?? [] as $kk => $vv) {
                $rules .= ' ' . $kk;
            }
            $chops = explode(' ', strtr($_POST['keys']['type'][$k] ?? 'TEXT', [
                ':default' => $_POST['keys']['value'] ?? 'NULL',
                ':key' => $v
            ]), 2);
            $keys[$v] = trim($chops[0] . $rules . ' ' . ($chops[1] ?? ""));
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
    }
    if ('update' === $task) {
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
    }
} else {
    if (isset($_GET['task']) && 'list' === $_GET['task']) {
        // Redirect to home page
        header('location: ' . $p);
        exit;
    }
}

?>
<!DOCTYPE html>
<html dir="ltr">
  <head>
    <meta content="width=device-width">
    <meta charset="utf-8">
    <title>Table Management System</title>
    <style>

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

    a:focus,
    a:hover {
      text-decoration: underline;
    }

    b, th {
      font-weight: bold;
    }

    button {
      background: #def;
      border: 2px solid #000;
      cursor: pointer;
      padding: .25em .5em;
    }

    button:focus,
    select:focus {
      border-color: #00f;
      outline-offset: -4px;
      outline: 1px solid #00f;
    }

    i {
      font-style: italic;
    }

    input[type='number'],
    input[type='text'],
    select,
    textarea {
      background: #fff;
      border: 2px solid #000;
      padding: .25em .5em;
    }

    input[type='checkbox'],
    input[type='radio'] {
      appearance: none;
      border: 2px solid;
      height: 1em;
      margin-top: .125em;
      min-height: 1em;
      min-width: 1em;
      width: 1em;
    }

    input[type='checkbox']:focus,
    input[type='radio']:focus {
      border-color: #00f;
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

    hr,
    p,
    table {
      margin: 1em 0;
    }

    hr {
      border: 0;
      border-top: 1px dashed #000;
    }

    select {
      cursor: pointer;
    }

    small {
      font-size: smaller;
    }

    table {
      border-collapse: collapse;
      width: 100%;
    }

    td,
    th {
      border: 1px solid;
      padding: .25em;
      text-align: left;
      vertical-align: top;
    }

    tfoot td,
    tfoot th {
      background: #eee;
    }

    [role='alert'] {
      background: #ff0;
      padding: .35em .5em;
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

    </style>
  </head>
  <body>
    <?php if (isset($_SESSION['alert'])): ?>
      <p role="alert">
        <?= $_SESSION['alert']; ?>
      </p>
    <?php endif; ?>
    <form action="<?= $p; ?>" method="get">
      <?php if (isset($_GET['task'])): ?>
        <button name="task" type="submit" value="list">
          List
        </button>
      <?php else: ?>
        <button name="task" type="submit" value="create">
          New Table
        </button>
      <?php endif; ?>
    </form>
    <hr>
    <form action="<?= $p; ?>?task=<?= $task_default = ($task = $_GET['task'] ?? null) ?? 'create'; ?>" enctype="multipart/form-data" method="post">
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
            <table hidden>
              <thead>
                <th>
                  Name
                </th>
                <th>
                  Type
                </th>
                <th>
                  Actions
                </th>
              </thead>
              <tbody id="table-rows-container"></tbody>
            </table>
          </p>
          <p>
            <button class="add-table-column" disabled type="button">
              Add Column
            </button>
            <button class="create-table" disabled type="submit">
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
                      'BLOB' => 'File',
                      'INTEGER DEFAULT 0 CHECK (:key IN (0, 1))' => 'Boolean',
                      'INTEGER' => 'Number',
                      'NULL' => 'Null',
                      'REAL' => 'Decimal',
                      'TEXT' => 'Any'
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
                        <li>
                          <label>
                            <input name="keys[rules][][AUTOINCREMENT]" type="checkbox">
                            <span>
                              Automatically increment the value on this field if it was not set explicitly by the user. The automatic value is made based on the last number that has been inserted in the previous action.
                            </span>
                          </label>
                        </li>
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
                            <input name="keys[rules][][PRIMARY KEY]" type="checkbox">
                            <span>
                              Make this field as the primary key.
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
        <?php elseif ('update' === $task): ?>
          <?php if ($table = Base::table($_GET['table'])): ?>
            <table>
              <thead>
                <tr>
                  <?php foreach ($table as $k => $v): ?>
                    <th>
                      <?= $k; ?>
                    </th>
                  <?php endforeach; ?>
                  <th></th>
                </tr>
              </thead>
              <tfoot>
                <tr>
                  <?php foreach ($table as $k => $v): ?>
                    <?php if ('ID' === $k): ?>
                      <td></td>
                    <?php else: ?>
                      <td>
                        <?php if ('BLOB' === $v): ?>
                          <input name="values[<?= $k; ?>]" style="display: block; width: 100%;" type="file">
                        <?php elseif ('INTEGER' === $v): ?>
                          <input name="values[<?= $k; ?>]" style="display: block; width: 100%;" type="number">
                        <?php elseif ('NULL' === $v): ?>
                          <em>NULL</em>
                        <?php elseif ('REAL' === $v): ?>
                          <input name="values[<?= $k; ?>]" style="display: block; width: 100%;" type="number">
                        <?php else: ?>
                          <textarea name="values[<?= $k; ?>]" style="display: block; width: 100%;"></textarea>
                        <?php endif; ?>
                      </td>
                    <?php endif; ?>
                  <?php endforeach; ?>
                  <td>
                    <button type="submit">
                      Insert
                    </button>
                  </td>
                </tr>
              </tfoot>
            </table>
            <table>
              <thead>
                <tr>
                  <?php foreach ($table as $k => $v): ?>
                    <th>
                      <?= $k; ?>
                    </th>
                  <?php endforeach; ?>
                  <th></th>
                </tr>
              </thead>
              <?php if ($rows = Base::rows($_GET['table'])): ?>
                <tbody>
                  <?php foreach ($rows as $k => $v): ?>
                    <tr>
                      <?php foreach ($v as $kk => $vv): ?>
                        <td>
                          <?php $vv = htmlspecialchars($vv); ?>
                          <?php $vvv = trim(substr($vv, 0, 120)); ?>
                          <?= $vvv . (strlen($vv) > strlen($vvv) ? '&hellip;' : ""); ?>
                        </td>
                      <?php endforeach; ?>
                      <td>
                        <button name="delete" type="submit" value="<?= $v->ID; ?>">
                          Delete
                        </button>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              <?php endif; ?>
            </table>
            <input name="table" type="hidden" value="<?= $_GET['table']; ?>">
          <?php else: ?>
            <p>
              Table <code><?= $_GET['table']; ?></code> does not exist.
            </p>
          <?php endif; ?>
        <?php endif; ?>
      <?php else: ?>
        <?php if ($tables = (array) Base::tables()): ?>
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
                    <a href="<?= $p; ?>?table=<?= $k; ?>&amp;task=update"><?= $k; ?></a>
                    <br>
                    <small><?= $v . ' Row' . (1 === $v ? "" : 's'); ?></small>
                  </td>
                  <td>
                    <button name="drop" onclick="return confirm('Are you sure you want to delete this table with its rows?')" type="submit" value="<?= $k; ?>">
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
      <input name="task" type="hidden" value="<?= $task_default; ?>">
    </form>
  </body>
</html>
<?php unset($_SESSION['alert']); ?>