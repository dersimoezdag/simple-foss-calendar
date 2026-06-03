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
    { label: __('Does not repeat', 'simple-foss-calendar'), value: 'none' },
    { label: __('Daily', 'simple-foss-calendar'), value: 'daily' },
    { label: __('Weekly', 'simple-foss-calendar'), value: 'weekly' },
    { label: __('Monthly', 'simple-foss-calendar'), value: 'monthly' },
    { label: __('Yearly', 'simple-foss-calendar'), value: 'yearly' },
  ];

  function EventDetailsPanel({ meta, setMeta }) {
    if (select('core/editor').getCurrentPostType() !== 'sfc_event') {
      return null;
    }

    latestMeta = meta || {};
    const updateMeta = (key, value) => setMeta({ ...meta, [key]: value });

    return el(
      PluginDocumentSettingPanel,
      {
        name: 'sfc-event-details',
        title: __('Event date, time and place', 'simple-foss-calendar'),
        className: 'sfc-event-details-panel',
      },
      el(TextControl, {
        label: __('Start date', 'simple-foss-calendar'),
        type: 'date',
        value: meta._sfc_start_date || '',
        onChange: (value) => updateMeta('_sfc_start_date', value),
      }),
      el(TextControl, {
        label: __('Start time', 'simple-foss-calendar'),
        type: 'time',
        value: meta._sfc_start_time || '',
        onChange: (value) => updateMeta('_sfc_start_time', value),
      }),
      el(TextControl, {
        label: __('End date', 'simple-foss-calendar'),
        type: 'date',
        value: meta._sfc_end_date || '',
        onChange: (value) => updateMeta('_sfc_end_date', value),
      }),
      el(TextControl, {
        label: __('End time', 'simple-foss-calendar'),
        type: 'time',
        value: meta._sfc_end_time || '',
        onChange: (value) => updateMeta('_sfc_end_time', value),
      }),
      el(ToggleControl, {
        label: __('All-day event', 'simple-foss-calendar'),
        checked: meta._sfc_all_day === '1',
        onChange: (checked) => updateMeta('_sfc_all_day', checked ? '1' : '0'),
      }),
      el(TextControl, {
        label: __('Location', 'simple-foss-calendar'),
        value: meta._sfc_location || '',
        onChange: (value) => updateMeta('_sfc_location', value),
      }),
      el(TextControl, {
        label: __('External URL', 'simple-foss-calendar'),
        type: 'url',
        value: meta._sfc_external_url || '',
        onChange: (value) => updateMeta('_sfc_external_url', value),
      }),
      el(TextControl, {
        label: __('Calendar color', 'simple-foss-calendar'),
        type: 'color',
        value: meta._sfc_color || '#ffffff',
        onChange: (value) => updateMeta('_sfc_color', value),
      }),
      el(
        PanelBody,
        {
          title: __('Repeat', 'simple-foss-calendar'),
          initialOpen: false,
        },
        el(SelectControl, {
          label: __('Repeat', 'simple-foss-calendar'),
          value: meta._sfc_recurrence || 'none',
          options: recurrenceOptions,
          onChange: (value) => updateMeta('_sfc_recurrence', value),
        }),
        el(TextControl, {
          label: __('Repeat every', 'simple-foss-calendar'),
          type: 'number',
          min: 1,
          max: 99,
          value: meta._sfc_recurrence_interval || 1,
          onChange: (value) => updateMeta('_sfc_recurrence_interval', Number(value) || 1),
        }),
        el(TextControl, {
          label: __('Repeat until', 'simple-foss-calendar'),
          type: 'date',
          value: meta._sfc_recurrence_until || '',
          onChange: (value) => updateMeta('_sfc_recurrence_until', value),
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

  registerPlugin('sfc-event-details', {
    render: withEventMeta(EventDetailsPanel),
  });

  subscribe(() => {
    const editor = select('core/editor');
    if (!editor || editor.getCurrentPostType() !== 'sfc_event') {
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
          path: `/simple-foss-calendar/v1/event-meta/${postId}`,
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
