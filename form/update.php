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