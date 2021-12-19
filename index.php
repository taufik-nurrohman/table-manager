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
        $values = self::query('SELECT ' . ($keys ? implode(', ', $keys) : '*') . ' FROM "' . strtr($table, ['"' => '""']) . '"');
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
                $out[$value[0]] = true; // TODO: Set value as total column(s) and row(s)
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
    require __DIR__ . '/task/' . $task . '.php';
} else {
    if (isset($_GET['task']) && 'list' === $_GET['task']) {
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
          <?php require __DIR__ . '/form/create.php'; ?>
        <?php elseif ('update' === $task): ?>
          <?php require __DIR__ . '/form/update.php'; ?>
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
                    </a>
                  </td>
                  <td>
                    <button name="drop" type="submit" value="<?= $k; ?>">
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