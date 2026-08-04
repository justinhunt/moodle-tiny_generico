# Tiny Generico for Moodle

**Insert Generico filter widgets from a button on Moodle's TinyMCE editor toolbar.**

The Generico filter lets an administrator define reusable HTML/JS/CSS templates ("widgets") that
teachers drop into content using filter strings such as `{GENERICO:type="youtube",id="..."}`.
Typing those strings by hand is error-prone, so Tiny Generico adds a **Generico Widgets** button
(and menu item) to the TinyMCE editor. It opens a picker of the widgets available on the site,
shows a simple settings form for the chosen widget's variables, and inserts a correctly formed
filter string into the text.

Since version 1.0.6, Tiny Generico supports templates from **both** generations of the filter:

- **[Generico](https://moodle.org/plugins/filter_generico)** (`filter_generico`) — the classic,
  config-based filter, using `{GENERICO:...}` strings.
- **[Generico Two](https://github.com/justinhunt/moodle-filter_genericotwo)** (`filter_genericotwo`)
  — the new database-backed filter with Mustache templates, using `{G2:...}` strings.

Either filter (or both) can be installed; the widget picker lists whichever templates are
available, grouped and labelled when both filters contribute.

- **Plugin:** `tiny_generico` (TinyMCE editor plugin)
- **Maintainer:** Justin Hunt — poodllsupport@gmail.com
- **Documentation:** https://support.poodll.com
- **License:** GNU GPL v3 or later

---

## Requirements

| | |
|---|---|
| Moodle | 4.3 or later (`$plugin->requires = 2023100900`), TinyMCE editor |
| Filter | [Generico](https://moodle.org/plugins/view.php?plugin=filter_generico) and/or [Generico Two](https://github.com/justinhunt/moodle-filter_genericotwo) installed and **active** (Generico Two itself requires Moodle 4.5+) |

Tiny Generico is free and needs no external services or API credentials. Without at least one of
the Generico filters installed and enabled, the widget picker has nothing to offer.

## Installation

1. Copy the plugin folder to `lib/editor/tiny/plugins/generico` in your Moodle code root (on
   Moodle 5.1+ this is `public/lib/editor/tiny/plugins/generico`), or install from the Moodle
   plugins directory, or via git:

   ```
   git clone https://github.com/justinhunt/moodle-tiny_generico.git lib/editor/tiny/plugins/generico
   ```

2. Visit **Site administration → Notifications** and complete the upgrade.

There are no plugin settings — the widget list comes entirely from the Generico filters'
template configuration.

## Usage

1. Edit any content that uses the TinyMCE editor and click the **Generico Widgets** toolbar
   button (also available from the Insert menu).
2. A widget picker dialog lists the site's available templates. When both Generico and
   Generico Two are active, their widgets appear in labelled groups.
3. Choose a widget. If the template has variables, a settings form appears with a field for each
   one, pre-filled with the template's defaults (pipe-separated defaults become dropdowns), plus
   any instructions the template author provided.
4. Click **Insert** and the finished `{GENERICO:...}` or `{G2:...}` filter string is placed in
   the editor. When the page is viewed, the filter replaces it with the rendered widget.

### Which widgets appear in the picker

- Only templates from filters that are **active in the current context** are offered, so an
  inserted tag is never dead text.
- Templates hidden in the Generico filter settings are not shown.
- Generico Two templates honour their own per-context restrictions (`allowedcontexts`), so a
  template limited to certain courses or contexts only appears where it is allowed.

## Capabilities

| Capability | Default | Purpose |
|---|---|---|
| `tiny/generico:visible` | Teachers, editing teachers, course creators, managers | Whether the toolbar button is shown |
| `tiny/generico:use` | All users | Whether Generico widgets can be used from TinyMCE |

Sites that want students to insert widgets too can allow `tiny/generico:visible` for the student
role.

## Privacy

Tiny Generico stores no personal data.

## Support

- Documentation: https://support.poodll.com
- Email: poodllsupport@gmail.com
- Issues: https://github.com/justinhunt/moodle-tiny_generico/issues

## Credits

Developed by Justin Hunt / [Poodll](https://poodll.com).

## License

GNU GPL v3 or later — see http://www.gnu.org/licenses/gpl.txt
This software is provided "AS IS" without a warranty of any kind.
