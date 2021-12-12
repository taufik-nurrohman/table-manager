<table border="1">
  <tr>
    <th style="width: 10em;">
      Table
    </th>
    <td>
      <input autofocus name="table" pattern="^[a-z][a-z\d]*(?:_[a-z\d]*)*$" placeholder="table_1" required type="text">
    </td>
  </tr>
  <tr>
    <th>
      Columns
    </th>
    <td>
      <div>
        <button onclick="addColumn.call(this);" title="Add Column" type="button">
          Add
        </button>
      </div>
    </td>
  </tr>
</table>
<p>
  <button type="submit">
    Create
  </button>
</p>
<template id="table-column">
  <div>
    <table border="1">
      <tr>
        <th style="width: 10em;">
          Name
        </th>
        <td>
          <input name="column[name][]" pattern="^[a-z][a-z\d]*(?:_[a-z\d]*)*$" placeholder="column_1" required type="text">
        </td>
      </tr>
      <tr>
        <th>
          Type
        </th>
        <td>
          <select name="column[type][]">
            <option value="BLOB">Blob</option>
            <option value="INTEGER">Integer</option>
            <option value="NULL">Null</option>
            <option value="REAL">Real</option>
            <option selected value="TEXT">Text</option>
          </select>
        </td>
      </tr>
      <tr>
        <th>
          Primary
        </th>
        <td>
          <input name="column[primary][]" type="checkbox" value="1">
        </td>
      </tr>
      <tr>
        <td colspan="2">
          <button onclick="removeColumn.call(this);" title="Remove This Column" type="button">
            Remove
          </button>
          <button onclick="cloneColumn.call(this);" title="Clone This Column" type="button">
            Clone
          </button>
        </td>
      </tr>
    </table>
  </div>
</template>
<script>

const column = document.querySelector('#table-column');

function addColumn() {
    let parent = this.parentNode,
        clone = column.content.cloneNode(true);
    parent.parentNode.insertBefore(clone, parent);
}

function cloneColumn() {}

function removeColumn() {
    this.closest('div').remove();
}

</script>