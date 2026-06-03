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
    const output = document.querySelector('[data-sfc-shortcode-output="events"]');
    if (!output) {
      return;
    }

    const category = document.querySelector('[data-sfc-shortcode-field="category"]')?.value || '';
    const maxEvents = document.querySelector('[data-sfc-shortcode-field="max-events"]')?.value || '6';
    const style = document.querySelector('[data-sfc-shortcode-field="style"]')?.value || 'list';
    const showPlace = checkboxValue('[data-sfc-shortcode-field="show-place"]');
    const showTime = checkboxValue('[data-sfc-shortcode-field="show-time"]');

    output.value = `[simple_foss_events${attr('category', category)}${attr('max-events', maxEvents)}${attr('show-place', showPlace)}${attr('show-time', showTime)}${attr('style', style)}]`;
  }

  function updateCalendarShortcode() {
    const output = document.querySelector('[data-sfc-shortcode-output="calendar"]');
    if (!output) {
      return;
    }

    const topic = document.querySelector('[data-sfc-calendar-field="topic"]')?.value || '';
    const showLegend = checkboxValue('[data-sfc-calendar-field="show_legend"]');

    output.value = `[simple_foss_calendar${attr('topic', topic)}${attr('show_legend', showLegend)}]`;
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
    document.querySelectorAll('[data-sfc-shortcode-field]').forEach((field) => {
      field.addEventListener('input', updateEventsShortcode);
      field.addEventListener('change', updateEventsShortcode);
    });

    document.querySelectorAll('[data-sfc-calendar-field]').forEach((field) => {
      field.addEventListener('input', updateCalendarShortcode);
      field.addEventListener('change', updateCalendarShortcode);
    });

    document.querySelectorAll('[data-sfc-copy-shortcode]').forEach((button) => {
      button.addEventListener('click', () => copyShortcode(button.dataset.sfcCopyShortcode));
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
