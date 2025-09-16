# Screener Dropdown

A WordPress plugin that allows dynamic filtering of data using multiple metrics, operators, and values. The filtered results are displayed in a responsive table.

---

## Features

- Add and remove multiple filters dynamically.
- Supports numeric, string, and categorical metrics.
- Operators include: equals, contains, greater than, less than, between, in (multi), etc.
- AJAX-based filtering for fast updates.
- Responsive table with DataTables.js.
- Reset all filters easily.

---

## Installation

1. Copy the plugin folder to `wp-content/plugins/` in your WordPress setup.
2. Activate the plugin in the WordPress admin panel.
3. Ensure dependencies are loaded:
   - jQuery
   - Select2
   - DataTables.js

---

## Usage

1. Open the page where the plugin is active.
2. Click **Add Filter** to add a filter row.
3. Select a metric, operator, and enter the value(s).
4. Click **Apply Filters** to fetch the filtered data.
5. Click **Reset Filters** to clear filters and table.

---

## File Structure

screener-dropdown/
├── css/ -> CSS files for styling
├── js/ -> JavaScript for dynamic filters
├── screener-dropdown.php -> Main plugin file
└── README.md -> Project documentation
