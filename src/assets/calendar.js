(function () {
  'use strict';

  const settings = window.openagendaCalendarSettings || {};
  const labels = settings.labels || {};
  const locale = settings.locale || document.documentElement.lang || 'en-US';
  const configuredFirstWeekday = Number(settings.firstWeekday);
  const firstWeekday = Number.isInteger(configuredFirstWeekday) ? configuredFirstWeekday : 1;

  function startOfMonth(date) {
    return new Date(date.getFullYear(), date.getMonth(), 1);
  }

  function endOfMonth(date) {
    return new Date(date.getFullYear(), date.getMonth() + 1, 0);
  }

  function toDateKey(date) {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
  }

  function parseLocalDate(value) {
    if (!value) {
      return null;
    }

    const datePart = value.split('T')[0];
    const parts = datePart.split('-').map(Number);
    if (parts.length !== 3 || parts.some(Number.isNaN)) {
      return null;
    }

    return new Date(parts[0], parts[1] - 1, parts[2]);
  }

  function datesBetween(start, end) {
    const days = [];
    const cursor = new Date(start.getFullYear(), start.getMonth(), start.getDate());
    const last = new Date(end.getFullYear(), end.getMonth(), end.getDate());

    while (cursor <= last) {
      days.push(toDateKey(cursor));
      cursor.setDate(cursor.getDate() + 1);
    }

    return days;
  }

  function monthGridDates(monthDate) {
    const first = startOfMonth(monthDate);
    const last = endOfMonth(monthDate);
    const offset = (first.getDay() - firstWeekday + 7) % 7;
    const cursor = new Date(first);
    cursor.setDate(first.getDate() - offset);

    const dates = [];
    while (dates.length < 42) {
      dates.push(new Date(cursor));
      cursor.setDate(cursor.getDate() + 1);
    }

    const lastVisible = dates[dates.length - 1];
    if (lastVisible < last) {
      while (dates.length < 49) {
        dates.push(new Date(cursor));
        cursor.setDate(cursor.getDate() + 1);
      }
    }

    return dates;
  }

  function buildUrl(calendar, monthDate) {
    const first = startOfMonth(monthDate);
    const last = endOfMonth(monthDate);
    const url = new URL(settings.restUrl, window.location.href);

    url.searchParams.set('from', toDateKey(first));
    url.searchParams.set('to', toDateKey(last));

    if (calendar.dataset.topic) {
      url.searchParams.set('topic', calendar.dataset.topic);
    }

    return url;
  }

  function groupEventsByDay(events) {
    return events.reduce((grouped, event) => {
      const start = parseLocalDate(event.start);
      const end = parseLocalDate(event.end) || start;

      if (!start) {
        return grouped;
      }

      datesBetween(start, end).forEach((key) => {
        grouped[key] = grouped[key] || [];
        grouped[key].push(event);
      });

      return grouped;
    }, {});
  }

  function renderWeekdays(calendar) {
    const weekdays = calendar.querySelector('[data-openagenda-weekdays]');
    weekdays.innerHTML = '';

    for (let index = 0; index < 7; index += 1) {
      const day = (firstWeekday + index) % 7;
      const sample = new Date(2024, 0, 7 + day);
      const label = new Intl.DateTimeFormat(locale, { weekday: 'short' }).format(sample);
      const item = document.createElement('div');
      item.className = 'openagenda-calendar__weekday';
      item.textContent = label;
      weekdays.appendChild(item);
    }
  }

  function renderLegend(calendar, events) {
    const legend = calendar.querySelector('[data-openagenda-legend]');
    const shouldShow = calendar.dataset.showLegend === 'true';
    const topics = new Map();

    events.forEach((event) => {
      (event.topics || []).forEach((topic) => {
        if (!topics.has(topic)) {
          topics.set(topic, event.color || '#ffffff');
        }
      });
    });

    legend.innerHTML = '';
    legend.hidden = !shouldShow || topics.size === 0;

    topics.forEach((color, topic) => {
      const item = document.createElement('span');
      item.className = 'openagenda-calendar__legend-item';
      item.style.setProperty('--openagenda-event-color', color);
      item.textContent = topic;
      legend.appendChild(item);
    });
  }

  function eventTimeLabel(event) {
    if (event.allDay) {
      return '';
    }

    if (event.compactTimeLabel) {
      return event.compactTimeLabel;
    }

    if (!event.start || !event.start.includes('T')) {
      return '';
    }

    return event.start.split('T')[1].slice(0, 5);
  }

  function renderEvents(day, events) {
    const list = document.createElement('ul');
    list.className = 'openagenda-calendar__events';

    events.slice(0, 4).forEach((event) => {
      const item = document.createElement('li');
      item.className = 'openagenda-calendar__event';
      item.style.setProperty('--openagenda-event-color', event.color || '#ffffff');

      const link = document.createElement('a');
      link.className = 'openagenda-calendar__event-link';
      link.href = event.url || event.permalink || '#';

      const time = eventTimeLabel(event);
      if (time) {
        const timeNode = document.createElement('span');
        timeNode.className = 'openagenda-calendar__event-time';
        timeNode.textContent = time;
        link.appendChild(timeNode);
      }

      const title = document.createElement('span');
      title.className = 'openagenda-calendar__event-title';
      title.textContent = event.title;
      link.appendChild(title);

      item.appendChild(link);
      list.appendChild(item);
    });

    if (events.length > 4) {
      const more = document.createElement('li');
      more.className = 'openagenda-calendar__more';
      more.textContent = `+${events.length - 4}`;
      list.appendChild(more);
    }

    day.appendChild(list);
  }

  function renderCalendar(calendar, monthDate, events) {
    const title = calendar.querySelector('[data-openagenda-title]');
    const grid = calendar.querySelector('[data-openagenda-grid]');
    const status = calendar.querySelector('[data-openagenda-status]');
    const todayKey = toDateKey(new Date());
    const activeMonth = monthDate.getMonth();
    const grouped = groupEventsByDay(events);

    title.textContent = new Intl.DateTimeFormat(locale, {
      month: 'long',
      year: 'numeric',
    }).format(monthDate);

    grid.innerHTML = '';
    status.textContent = events.length ? '' : labels.noEvents || 'No events this month.';
    status.hidden = events.length > 0;

    monthGridDates(monthDate).forEach((date) => {
      const key = toDateKey(date);
      const dayEvents = grouped[key] || [];
      const day = document.createElement('div');
      day.className = 'openagenda-calendar__day';

      if (date.getMonth() !== activeMonth) {
        day.classList.add('openagenda-calendar__day--muted');
      }

      if (!dayEvents.length) {
        day.classList.add('openagenda-calendar__day--empty');
      }

      if (key === todayKey) {
        day.classList.add('openagenda-calendar__day--today');
      }

      const heading = document.createElement('div');
      heading.className = 'openagenda-calendar__day-heading';

      const number = document.createElement('time');
      number.dateTime = key;
      number.textContent = String(date.getDate());
      heading.appendChild(number);

      day.appendChild(heading);

      if (dayEvents.length) {
        renderEvents(day, dayEvents);
      }

      grid.appendChild(day);
    });

    renderLegend(calendar, events);
  }

  async function loadEvents(calendar, monthDate) {
    const status = calendar.querySelector('[data-openagenda-status]');
    status.hidden = false;
    status.textContent = labels.loading || 'Loading events...';
    calendar.classList.add('openagenda-calendar--loading');

    try {
      const response = await fetch(buildUrl(calendar, monthDate), {
        credentials: 'same-origin',
        headers: {
          Accept: 'application/json',
        },
      });

      if (!response.ok) {
        throw new Error(`Calendar request failed: ${response.status}`);
      }

      const events = await response.json();
      renderCalendar(calendar, monthDate, events);
    } catch (error) {
      status.hidden = false;
      status.textContent = error.message;
    } finally {
      calendar.classList.remove('openagenda-calendar--loading');
    }
  }

  function initCalendar(calendar) {
    let currentMonth = startOfMonth(new Date());

    renderWeekdays(calendar);
    loadEvents(calendar, currentMonth);

    calendar.addEventListener('click', (event) => {
      const button = event.target.closest('[data-openagenda-action]');
      if (!button) {
        return;
      }

      const action = button.dataset.sfcAction;
      if (action === 'previous') {
        currentMonth = new Date(currentMonth.getFullYear(), currentMonth.getMonth() - 1, 1);
      }

      if (action === 'next') {
        currentMonth = new Date(currentMonth.getFullYear(), currentMonth.getMonth() + 1, 1);
      }

      if (action === 'today') {
        currentMonth = startOfMonth(new Date());
      }

      loadEvents(calendar, currentMonth);
    });
  }

  function init() {
    document.querySelectorAll('.openagenda-calendar').forEach(initCalendar);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
