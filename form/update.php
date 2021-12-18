<?php if ($base->querySingle("SELECT name FROM sqlite_master WHERE type='table' AND name='" . $_GET['table'] . "'")): ?>
  <table border="1">
    <thead>
      <tr>
        <?php $values = $base->query("PRAGMA table_info(" . $_GET['table'] . ")"); ?>
        <?php while ($value = $values->fetchArray(SQLITE3_NUM)): ?>
          <th>
            <?= $value[1]; ?>
          </th>
        <?php endwhile; ?>
        <th></th>
      </tr>
    </thead>
    <?php if ($base->querySingle("SELECT count(*) FROM " . $_GET['table'])): ?>
      <tbody>
        <?php $values = $base->query("SELECT * FROM " . $_GET['table']); ?>
        <?php while ($value = $values->fetchArray(SQLITE3_NUM)): ?>
          <tr>
            <?php foreach ($value as $v): ?>
              <td>
                <?= htmlspecialchars($v); ?>
              </td>
            <?php endforeach; ?>
            <td>
              <button name="delete" type="submit" value="<?= $value[0]; ?>">
                Delete
              </button>
            </td>
          </tr>
        <?php endwhile; ?>
      </tbody>
    <?php endif; ?>
  </table>
  <?php $values = $base->query("PRAGMA table_info(" . $_GET['table'] . ")"); ?>
  <?php $data = []; while ($value = $values->fetchArray(SQLITE3_NUM)): ?>
    <?php if ('id' === $value[1]) continue; ?>
    <?php $data[$value[1]] = $value[2]; ?>
  <?php endwhile; ?>
  <?php ksort($data); ?>
  <?php foreach ($data as $k => $v): ?>
    <p>
      <label>
        <b>
          <?= $k; ?>
        </b>
      </label>
      <br>
      <?php if ("" === $v): ?>
        <em>NULL</em>
      <?php elseif ('BLOB' === $v): ?>
        <input name="values[<?= $k; ?>]" type="file">
      <?php elseif ('INTEGER' === $v): ?>
        <input max="9223372036854775807" min="-9223372036854775808" name="values[<?= $k; ?>]" step="1" type="number">
      <?php elseif ('REAL' === $v): ?>
        <input name="values[<?= $k; ?>]" step="0.001" type="number">
      <?php elseif ('TEXT' === $v): ?>
        <textarea name="values[<?= $k; ?>]"></textarea>
      <?php endif; ?>
    </p>
  <?php endforeach; ?>
  <p>
    <button title="Insert Row" type="submit">
      Insert
    </button>
    <input name="table" type="hidden" value="<?= $_GET['table']; ?>">
  </p>
<?php else: ?>
  <p>
    Table <code><?= $_GET['table']; ?></code> does not exist.
  </p>
<?php endif; ?>