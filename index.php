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
      box-sizing: border-box;
    }

    :root {
      background: #fff;
      color: #000;
      font-family: 'times new roman', serif;
    }

    button {
      cursor: pointer;
    }

    select {
      cursor: pointer;
    }

    th, td {
      text-align: left;
      vertical-align: top;
    }

    body > p:first-child {
      background: #ff0;
      padding: .35em .5em;
    }

    </style>
  </head>
  <body>
    <?php if (isset($_SESSION['alert'])): ?>
      <p>
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
          Create
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
              <input autofocus name="table" pattern="^[A-Z_][a-zA-Z\d_]*(?:_[A-Z\d][a-zA-Z\d]*)*$" placeholder="FooBarBaz" required type="text">
            </p>
          </p>
          <p>
            <label>
              <b>Table Columns</b>
            </label>
            <table border="1">
              <thead>
                <th>
                  Name
                </th>
                <th>
                  Type
                </th>
                <th>
                  Status
                </th>
                <th></th>
              </thead>
              <tbody>
                <tr>
                  <td colspan="4">
                    <button onclick="addColumn.call(this);" title="Add Column" type="button">
                      Add
                    </button>
                  </td>
                </tr>
              </tbody>
            </table>
          </p>
          <p>
            <button type="submit">
              Create
            </button>
          </p>
          <template id="table-column">
            <tr>
              <td>
                <input name="keys[name][]" pattern="^[a-zA-Z_][a-zA-Z\d_]*(?:_[a-zA-Z\d]*)*$" placeholder="fooBarBaz" required type="text">
              </td>
              <td>
                <select name="keys[type][]" onchange="changeStatus.call(this);">
                  <option selected value="TEXT">Text</option>
                  <option value="BLOB">Blob</option>
                  <option value="INTEGER">Number</option>
                  <option value="INTEGER">Toggle</option>
                  <option value="NULL">Null</option>
                  <option value="REAL">Decimal</option>
                </select>
              </td>
              <td>
                <label>
                  <input disabled name="keys[auto-increment][]" type="checkbox" value="1">
                  Auto Increment
                </label>
                <br>
                <label>
                  <input name="keys[not-null][]" type="checkbox" value="1">
                  Not Null
                </label>
                <br>
                <label>
                  <input name="keys[primary-key][]" type="checkbox" value="1">
                  Primary Key
                </label>
                <br>
                <label>
                  <input name="keys[unique][]" type="checkbox" value="1">
                  Unique
                </label>
              </td>
              <td>
                <button onclick="removeColumn.call(this);" title="Remove This Column" type="button">
                  Remove
                </button>
              </td>
            </tr>
          </template>
          <script>

          const column = document.querySelector('#table-column');

          function addColumn() {
              let parent = this.parentNode.parentNode,
                  clone = column.content.cloneNode(true);
              parent.parentNode.insertBefore(clone, parent);
              parent.previousElementSibling.querySelector('input').focus();
          }

          function changeStatus() {
              let value = this.value,
                  parent = this.parentNode,
                  next = parent.nextElementSibling,
                  autoIncrement = next.querySelector('[name="keys[auto-increment][]"]');
              if ('INTEGER' === value) {
                  autoIncrement.disabled = false;
              } else {
                  autoIncrement.disabled = true;
              }
          }

          function removeColumn() {
              this.closest('tr').remove();
          }

          </script>
        <?php elseif ('update' === $task): ?>
          <?php if ($table = Base::table($_GET['table'])): ?>
            <table border="1" style="width: 100%;">
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
                  <td></td>
                </tr>
              </tfoot>
            </table>
            <input name="table" type="hidden" value="<?= $_GET['table']; ?>">
            <p>
              <button type="submit">
                Insert
              </button>
            </p>
          <?php else: ?>
            <p>
              Table <code><?= $_GET['table']; ?></code> does not exist.
            </p>
          <?php endif; ?>
        <?php endif; ?>
      <?php else: ?>
        <?php if ($tables = (array) Base::tables()): ?>
          <table border="1">
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
                    <a href="<?= $p; ?>?table=<?= $k; ?>&amp;task=update">
                      <?= $k; ?>
                    </a> (<?= $v . ' Row' . (1 === $v ? "" : 's'); ?>)
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