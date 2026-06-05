(function () {
  'use strict';

  function attr(name, value) {
    if (!value) {
      return '';
    }

    return ` ${name}="${String(value).replace(/"/g, '&quot;')}"`;
  }

  function checkboxValue(selector) {
    const field = document.querySelector(selector);
    return field && field.checked ? 'true' : 'false';
  }

  function updateEventsShortcode() {
    const output = document.querySelector('[data-openagenda-shortcode-output="events"]');
    if (!output) {
      return;
    }

    const category = document.querySelector('[data-openagenda-shortcode-field="category"]')?.value || '';
    const maxEvents = document.querySelector('[data-openagenda-shortcode-field="max-events"]')?.value || '6';
    const style = document.querySelector('[data-openagenda-shortcode-field="style"]')?.value || 'list';
    const showPlace = checkboxValue('[data-openagenda-shortcode-field="show-place"]');
    const showTime = checkboxValue('[data-openagenda-shortcode-field="show-time"]');

    output.value = `[openagenda_events${attr('category', category)}${attr('max-events', maxEvents)}${attr('show-place', showPlace)}${attr('show-time', showTime)}${attr('style', style)}]`;
  }

  function updateCalendarShortcode() {
    const output = document.querySelector('[data-openagenda-shortcode-output="calendar"]');
    if (!output) {
      return;
    }

    const topic = document.querySelector('[data-openagenda-calendar-field="topic"]')?.value || '';
    const showLegend = checkboxValue('[data-openagenda-calendar-field="show_legend"]');

    output.value = `[openagenda_events_calendar${attr('topic', topic)}${attr('show_legend', showLegend)}]`;
  }

  async function copyShortcode(id) {
    const field = document.getElementById(id);
    if (!field) {
      return;
    }

    field.select();

    try {
      await navigator.clipboard.writeText(field.value);
    } catch (error) {
      document.execCommand('copy');
    }
  }

  function init() {
    document.querySelectorAll('[data-openagenda-shortcode-field]').forEach((field) => {
      field.addEventListener('input', updateEventsShortcode);
      field.addEventListener('change', updateEventsShortcode);
    });

    document.querySelectorAll('[data-openagenda-calendar-field]').forEach((field) => {
      field.addEventListener('input', updateCalendarShortcode);
      field.addEventListener('change', updateCalendarShortcode);
    });

    document.querySelectorAll('[data-openagenda-copy-shortcode]').forEach((button) => {
      button.addEventListener('click', () => copyShortcode(button.dataset.openagendaCopyShortcode));
    });

    updateEventsShortcode();
    updateCalendarShortcode();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
