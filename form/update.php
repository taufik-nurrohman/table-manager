<?php if ($base->querySingle("SELECT name FROM sqlite_master WHERE type='table' AND name='" . $_GET['table'] . "'")): ?>
  <table border="1">
    <thead>
      <tr>
        <?php $rows = $base->query("PRAGMA table_info(" . $_GET['table'] . ")"); ?>
        <?php while ($row = $rows->fetchArray(SQLITE3_NUM)): ?>
          <th>
            <?= $row[1]; ?>
          </th>
        <?php endwhile; ?>
      </tr>
    </thead>
    <tbody>
      <?php $rows = $base->query("SELECT * FROM " . $_GET['table']); ?>
      <?php while ($row = $rows->fetchArray(SQLITE3_NUM)): ?>
        <tr>
        </tr>
      <?php endwhile; ?>
    </tbody>
  </table>
  <?php $rows = $base->query("PRAGMA table_info(" . $_GET['table'] . ")"); ?>
  <?php while ($row = $rows->fetchArray(SQLITE3_NUM)): ?>
    <p>
    <label>
      <b>
        <?= $row[1]; ?>
      </b>
    </label>
    <br>
    <?php if ('INTEGER' === $row[2]): ?>
      <input type="number" value="">
    <?php elseif ('TEXT' === $row[2]): ?>
      <textarea></textarea>
    <?php endif; ?>
    </p>
  <?php endwhile; ?>
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