(function () {
  'use strict';

  const { registerBlockType } = wp.blocks;
  const { InspectorControls, useBlockProps } = wp.blockEditor;
  const { PanelBody, RangeControl, SelectControl, TextControl, ToggleControl } = wp.components;
  const { createElement: el } = wp.element;
  const { __ } = wp.i18n;
  const ServerSideRender = wp.serverSideRender;

  registerBlockType('openagenda-events-calendar/upcoming-events', {
    edit({ attributes, setAttributes }) {
      const blockProps = useBlockProps();

      return el(
        'div',
        blockProps,
        el(
          InspectorControls,
          null,
          el(
            PanelBody,
            {
              title: __('Event list settings', 'openagenda-events-calendar'),
              initialOpen: true,
            },
            el(TextControl, {
              label: __('Event category slug', 'openagenda-events-calendar'),
              help: __('Leave empty to show all event topics.', 'openagenda-events-calendar'),
              value: attributes.category || '',
              onChange: (category) => setAttributes({ category }),
            }),
            el(RangeControl, {
              label: __('Maximum events', 'openagenda-events-calendar'),
              value: attributes.maxEvents || 6,
              min: 1,
              max: 20,
              onChange: (maxEvents) => setAttributes({ maxEvents }),
            }),
            el(ToggleControl, {
              label: __('Show place', 'openagenda-events-calendar'),
              checked: attributes.showPlace !== false,
              onChange: (showPlace) => setAttributes({ showPlace }),
            }),
            el(ToggleControl, {
              label: __('Show time', 'openagenda-events-calendar'),
              checked: attributes.showTime !== false,
              onChange: (showTime) => setAttributes({ showTime }),
            }),
            el(SelectControl, {
              label: __('Style', 'openagenda-events-calendar'),
              value: attributes.displayStyle || 'list',
              options: [
                { label: __('List', 'openagenda-events-calendar'), value: 'list' },
                { label: __('Minimal list', 'openagenda-events-calendar'), value: 'minimal-list' },
                { label: __('Calendar', 'openagenda-events-calendar'), value: 'calendar' },
              ],
              onChange: (displayStyle) => setAttributes({ displayStyle }),
            })
          )
        ),
        el(ServerSideRender, {
          block: 'openagenda-events-calendar/upcoming-events',
          attributes,
        })
      );
    },
  });
})();
