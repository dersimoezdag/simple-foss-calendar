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

    const data = row.querySelector('.sfc-quick-edit-data');

    if (!data) {
      return;
    }

    setInput(editRow, 'sfc_start_date', data.dataset.startDate);
    setInput(editRow, 'sfc_start_time', data.dataset.startTime);
    setInput(editRow, 'sfc_end_date', data.dataset.endDate);
    setInput(editRow, 'sfc_end_time', data.dataset.endTime);
    setInput(editRow, 'sfc_location', data.dataset.location);
    setCheckbox(editRow, 'sfc_all_day', data.dataset.allDay === '1');
  };
})(window, document);
