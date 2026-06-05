(function () {
  'use strict';

  const { PanelBody, SelectControl, TextControl, ToggleControl } = wp.components;
  const { createHigherOrderComponent } = wp.compose;
  const { select, subscribe, withDispatch, withSelect } = wp.data;
  const { createElement: el } = wp.element;
  const { __ } = wp.i18n;
  const { PluginDocumentSettingPanel } = wp.editPost;
  const { registerPlugin } = wp.plugins;
  const apiFetch = wp.apiFetch;

  let latestMeta = {};
  let wasSaving = false;
  let lastSavedPayload = '';

  const recurrenceOptions = [
    { label: __('Does not repeat', 'openagenda-events-calendar'), value: 'none' },
    { label: __('Daily', 'openagenda-events-calendar'), value: 'daily' },
    { label: __('Weekly', 'openagenda-events-calendar'), value: 'weekly' },
    { label: __('Monthly', 'openagenda-events-calendar'), value: 'monthly' },
    { label: __('Yearly', 'openagenda-events-calendar'), value: 'yearly' },
  ];

  function EventDetailsPanel({ meta, setMeta }) {
    if (select('core/editor').getCurrentPostType() !== 'openagenda_event') {
      return null;
    }

    latestMeta = meta || {};
    const updateMeta = (key, value) => setMeta({ ...meta, [key]: value });

    return el(
      PluginDocumentSettingPanel,
      {
        name: 'openagenda-event-details',
        title: __('Event date, time and place', 'openagenda-events-calendar'),
        className: 'openagenda-event-details-panel',
      },
      el(TextControl, {
        label: __('Start date', 'openagenda-events-calendar'),
        type: 'date',
        value: meta._openagenda_start_date || '',
        onChange: (value) => updateMeta('_openagenda_start_date', value),
      }),
      el(TextControl, {
        label: __('Start time', 'openagenda-events-calendar'),
        type: 'time',
        value: meta._openagenda_start_time || '',
        onChange: (value) => updateMeta('_openagenda_start_time', value),
      }),
      el(TextControl, {
        label: __('End date', 'openagenda-events-calendar'),
        type: 'date',
        value: meta._openagenda_end_date || '',
        onChange: (value) => updateMeta('_openagenda_end_date', value),
      }),
      el(TextControl, {
        label: __('End time', 'openagenda-events-calendar'),
        type: 'time',
        value: meta._openagenda_end_time || '',
        onChange: (value) => updateMeta('_openagenda_end_time', value),
      }),
      el(ToggleControl, {
        label: __('All-day event', 'openagenda-events-calendar'),
        checked: meta._openagenda_all_day === '1',
        onChange: (checked) => updateMeta('_openagenda_all_day', checked ? '1' : '0'),
      }),
      el(TextControl, {
        label: __('Location', 'openagenda-events-calendar'),
        value: meta._openagenda_location || '',
        onChange: (value) => updateMeta('_openagenda_location', value),
      }),
      el(TextControl, {
        label: __('External URL', 'openagenda-events-calendar'),
        type: 'url',
        value: meta._openagenda_external_url || '',
        onChange: (value) => updateMeta('_openagenda_external_url', value),
      }),
      el(TextControl, {
        label: __('Calendar color', 'openagenda-events-calendar'),
        type: 'color',
        value: meta._openagenda_color || '#ffffff',
        onChange: (value) => updateMeta('_openagenda_color', value),
      }),
      el(
        PanelBody,
        {
          title: __('Repeat', 'openagenda-events-calendar'),
          initialOpen: false,
        },
        el(SelectControl, {
          label: __('Repeat', 'openagenda-events-calendar'),
          value: meta._openagenda_recurrence || 'none',
          options: recurrenceOptions,
          onChange: (value) => updateMeta('_openagenda_recurrence', value),
        }),
        el(TextControl, {
          label: __('Repeat every', 'openagenda-events-calendar'),
          type: 'number',
          min: 1,
          max: 99,
          value: meta._openagenda_recurrence_interval || 1,
          onChange: (value) => updateMeta('_openagenda_recurrence_interval', Number(value) || 1),
        }),
        el(TextControl, {
          label: __('Repeat until', 'openagenda-events-calendar'),
          type: 'date',
          value: meta._openagenda_recurrence_until || '',
          onChange: (value) => updateMeta('_openagenda_recurrence_until', value),
        })
      )
    );
  }

  const withEventMeta = createHigherOrderComponent(
    (Component) =>
      withSelect((selectFn) => ({
        meta: selectFn('core/editor').getEditedPostAttribute('meta') || {},
      }))(
        withDispatch((dispatch) => ({
          setMeta: (meta) => dispatch('core/editor').editPost({ meta }),
        }))(Component)
      ),
    'withEventMeta'
  );

  registerPlugin('openagenda-event-details', {
    render: withEventMeta(EventDetailsPanel),
  });

  subscribe(() => {
    const editor = select('core/editor');
    if (!editor || editor.getCurrentPostType() !== 'openagenda_event') {
      return;
    }

    const isSaving = editor.isSavingPost();
    const isAutosaving = editor.isAutosavingPost();

    if (wasSaving && !isSaving && !isAutosaving) {
      const postId = editor.getCurrentPostId();
      const payload = JSON.stringify(latestMeta || {});

      if (postId && payload !== lastSavedPayload) {
        lastSavedPayload = payload;
        apiFetch({
          path: `/openagenda-events-calendar/v1/event-meta/${postId}`,
          method: 'POST',
          data: {
            meta: latestMeta || {},
          },
        }).catch(() => {});
      }
    }

    wasSaving = isSaving;
  });
})();
