<p>
  <label>
    <b>Table Name</b>
  </label>
  <p>
    <input autofocus name="table" pattern="^[a-z][a-z\d]*(?:_[a-z\d]*)*$" placeholder="foo_bar_baz" required type="text">
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
          <input name="keys[name][]" type="hidden" value="id">
          <input name="keys[type][]" type="hidden" value="INTEGER">
          <input name="keys[primary-key][]" type="hidden" value="1">
          <input name="keys[auto-increment][]" type="hidden" value="1">
          <input name="keys[not-null][]" type="hidden" value="1">
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
      <input name="keys[name][]" pattern="^[a-z][a-z\d]*(?:_[a-z\d]*)*$" placeholder="foo_bar_baz" required type="text">
    </td>
    <td>
      <select name="keys[type][]" onchange="changeStatus.call(this);">
        <option selected value="TEXT">String</option>
        <option value="BLOB">Blob</option>
        <option value="INTEGER">Integer</option>
        <option value="NULL">Null</option>
        <option value="REAL">Float</option>
      </select>
    </td>
    <td>
      <label>
        <input name="keys[primary-key][]" type="checkbox" value="1">
        Primary Key
      </label>
      <br>
      <label>
        <input disabled name="keys[auto-increment][]" type="checkbox" value="1">
        Auto Increment
      </label>
      <br>
      <label>
        <input name="keys[not-null][]" type="checkbox" value="1">
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