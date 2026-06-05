(function (window, document) {
  if (!window.inlineEditPost) {
    return;
  }

  const originalEdit = window.inlineEditPost.edit;

  function setInput(editRow, name, value) {
    const input = editRow.querySelector(`[name="${name}"]`);

    if (input) {
      input.value = value || '';
    }
  }

  function setCheckbox(editRow, name, checked) {
    const input = editRow.querySelector(`[name="${name}"]`);

    if (input) {
      input.checked = checked;
    }
  }

  window.inlineEditPost.edit = function (id) {
    originalEdit.apply(this, arguments);

    const postId = typeof id === 'object' ? this.getId(id) : id;
    const row = document.getElementById(`post-${postId}`);
    const editRow = document.getElementById(`edit-${postId}`);

    if (!row || !editRow) {
      return;
    }

    const data = row.querySelector('.openagenda-quick-edit-data');

    if (!data) {
      return;
    }

    setInput(editRow, 'openagenda_start_date', data.dataset.startDate);
    setInput(editRow, 'openagenda_start_time', data.dataset.startTime);
    setInput(editRow, 'openagenda_end_date', data.dataset.endDate);
    setInput(editRow, 'openagenda_end_time', data.dataset.endTime);
    setInput(editRow, 'openagenda_location', data.dataset.location);
    setCheckbox(editRow, 'openagenda_all_day', data.dataset.allDay === '1');
  };
})(window, document);
