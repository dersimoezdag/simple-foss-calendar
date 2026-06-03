(function () {
  'use strict';

  const { registerBlockType } = wp.blocks;
  const { InspectorControls, useBlockProps } = wp.blockEditor;
  const { PanelBody, RangeControl, SelectControl, TextControl, ToggleControl } = wp.components;
  const { createElement: el } = wp.element;
  const { __ } = wp.i18n;
  const ServerSideRender = wp.serverSideRender;

  registerBlockType('simple-foss-calendar/upcoming-events', {
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
              title: __('Event list settings', 'simple-foss-calendar'),
              initialOpen: true,
            },
            el(TextControl, {
              label: __('Event category slug', 'simple-foss-calendar'),
              help: __('Leave empty to show all event topics.', 'simple-foss-calendar'),
              value: attributes.category || '',
              onChange: (category) => setAttributes({ category }),
            }),
            el(RangeControl, {
              label: __('Maximum events', 'simple-foss-calendar'),
              value: attributes.maxEvents || 6,
              min: 1,
              max: 20,
              onChange: (maxEvents) => setAttributes({ maxEvents }),
            }),
            el(ToggleControl, {
              label: __('Show place', 'simple-foss-calendar'),
              checked: attributes.showPlace !== false,
              onChange: (showPlace) => setAttributes({ showPlace }),
            }),
            el(ToggleControl, {
              label: __('Show time', 'simple-foss-calendar'),
              checked: attributes.showTime !== false,
              onChange: (showTime) => setAttributes({ showTime }),
            }),
            el(SelectControl, {
              label: __('Style', 'simple-foss-calendar'),
              value: attributes.displayStyle || 'list',
              options: [
                { label: __('List', 'simple-foss-calendar'), value: 'list' },
                { label: __('Minimal list', 'simple-foss-calendar'), value: 'minimal-list' },
                { label: __('Calendar', 'simple-foss-calendar'), value: 'calendar' },
              ],
              onChange: (displayStyle) => setAttributes({ displayStyle }),
            })
          )
        ),
        el(ServerSideRender, {
          block: 'simple-foss-calendar/upcoming-events',
          attributes,
        })
      );
    },
  });
})();
