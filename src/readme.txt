=== Simple FOSS Calendar ===
Contributors: simple-foss-calendar-contributors
Tags: calendar, events, event calendar, shortcode
Requires at least: 6.0
Tested up to: 6.5
Requires PHP: 7.4
Stable tag: 0.1.22
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Adds an accessible event calendar and upcoming-events list to WordPress.

== Description ==

Simple FOSS Calendar provides an Events post type, event topics, date/time metadata, a responsive month calendar, an upcoming-events list, and a small read-only REST endpoint for frontend integrations.

Repository: https://github.com/dersimoezdag/simple-foss-calendar

The plugin is intentionally dependency-free on the frontend. It uses vanilla JavaScript and CSS, so it can be dropped into a broad range of WordPress themes.

Included translations: English and German.

Events can optionally repeat daily, weekly, monthly, or yearly, with a custom interval and optional end date.

Event date, time, location, URL, color, and recurrence can be edited in the Event Details box and in the block editor sidebar. Recurrence is used as an organization tool and for generated occurrences, but is not shown as a public detail on single event pages.

== Usage ==

1. Activate the plugin.
2. Create events under Events in the WordPress admin.
3. Add one of these shortcodes to a page or post:

`[simple_foss_calendar]`

`[simple_foss_events limit="6"]`

Filter by an event topic slug:

`[simple_foss_calendar topic="meetup"]`

`[simple_foss_events topic="meetup" limit="4"]`

The upcoming-events list also supports display options:

`[simple_foss_events category="meetup" max-events="5" show-place="true" show-time="true" style="list"]`

Available upcoming-events options:

`category` or `topic`: Event Topic slug.

`limit`, `max-events`, or `max_events`: Maximum number of events.

`show-place`: Show the event location.

`show-time`: Show the event time.

`style`: `list`, `minimal-list`, or `calendar`.

You can also insert the "Upcoming Events" block in the block editor. The block exposes controls for event category, maximum events, place, time, and style.

The calendar data is also available at:

`/wp-json/simple-foss-calendar/v1/events`

Optional REST query parameters:

`from=YYYY-MM-DD`

`to=YYYY-MM-DD`

`topic=topic-slug`

== Frequently Asked Questions ==

= Does this copy code from Sunflower? =

No. The plugin is an original implementation that takes broad product inspiration from a WordPress event calendar pattern: event content in WordPress, calendar output on the frontend, and normalized event data for JavaScript.

= Does it depend on a commercial service? =

No. Events are stored as WordPress content and rendered with local plugin assets.

== Changelog ==

= 0.1.22 =

Prevent recursive content filtering when rendering related events on single event pages.

= 0.1.21 =

Fix timezone-shifted event time display by formatting stored local event times directly.

= 0.1.20 =

Replace generic single-event post navigation with a styled "More Events" section.

= 0.1.19 =

Hide recurrence details from public single event pages while keeping recurrence for event organization.

= 0.1.18 =

Align compact minimal-list rows as date, bold title, and title-aligned time/place meta.

= 0.1.17 =

Refine minimal-list typography, alignment, and wrapping on theme-colored backgrounds.

= 0.1.16 =

Add event date, time, place, and all-day fields to Quick Edit.

= 0.1.15 =

Show the minimal-list divider only between events and make it subtler.

= 0.1.14 =

Make minimal-list time and location text inherit the theme color and render lighter.

= 0.1.13 =

Show minimal upcoming-event rows as title plus one compact date, time, and location line.

= 0.1.12 =

Set the default event color to white and remove old style aliases.

= 0.1.11 =

Refine upcoming event list markup and split date/time on single event pages.

= 0.1.10 =

Make event date and time saving more reliable in the block editor.

= 0.1.9 =

Add a shortcode generator below the admin event list.

= 0.1.8 =

Show event date, time, location, recurrence, and external URL on single event pages.

= 0.1.7 =

Fix saving event date, time, and location from the block editor.

= 0.1.6 =

Add a block editor sidebar for event date, time, location, URL, color, and recurrence fields.

= 0.1.5 =

Add event duplication in the admin event list.

= 0.1.4 =

Add recurring events.

= 0.1.3 =

Add English and German translation catalogs.

= 0.1.0 =

Initial release.
