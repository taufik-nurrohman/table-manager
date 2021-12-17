<p>
  <label>
    <b>Table Name</b>
    <br>
    <input autofocus name="table" pattern="^[a-z][a-z\d]*(?:_[a-z\d]*)*$" placeholder="table_1" required type="text">
  </label>
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
        <td>
          <input disabled type="text" value="id">
        </td>
        <td>
          <select disabled>
            <option selected>Integer</option>
          </select>
        </td>
        <td>
          <label>
            <input checked disabled type="checkbox">
            Primary Key
          </label>
          <br>
          <label>
            <input checked disabled type="checkbox">
            Auto Increment
          </label>
          <br>
          <label>
            <input checked disabled type="checkbox">
            Not Null
          </label>
          <input name="column[name][]" type="hidden" value="id">
          <input name="column[type][]" type="hidden" value="INTEGER">
          <input name="column[primary][]" type="hidden" value="1">
          <input name="column[increment][]" type="hidden" value="1">
          <input name="column[vital][]" type="hidden" value="1">
        </td>
        <td>
          <button disabled title="Remove This Column" type="button">
            Remove
          </button>
        </td>
      </tr>
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
      <input name="column[name][]" pattern="^[a-z][a-z\d]*(?:_[a-z\d]*)*$" placeholder="column_1" required type="text">
    </td>
    <td>
      <select name="column[type][]" onchange="changeStatus.call(this);">
        <option value="BLOB">Blob</option>
        <option value="INTEGER">Integer</option>
        <option value="NULL">Null</option>
        <option value="REAL">Real</option>
        <option selected value="TEXT">Text</option>
      </select>
    </td>
    <td>
      <label>
        <input name="column[primary][]" type="checkbox" value="1">
        Primary Key
      </label>
      <br>
      <label>
        <input disabled name="column[increment][]" type="checkbox" value="1">
        Auto Increment
      </label>
      <br>
      <label>
        <input name="column[vital][]" type="checkbox" value="1">
        Not Null
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
}

function changeStatus() {
    let value = this.value,
        parent = this.parentNode,
        next = parent.nextElementSibling,
        increment = next.querySelector('[name="column[increment][]"]');
    if ('INTEGER' === value) {
        increment.disabled = false;
    } else {
        increment.disabled = true;
    }
}

function removeColumn() {
    this.closest('tr').remove();
}

</script>