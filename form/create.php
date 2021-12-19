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