<?php
/*
Plugin Name: Screener Dropdown
Description: Shortcode [screener-dropdown] - dynamic filtering of screener_data.csv using screener_list.csv (Select2 + DataTables)
Version: 1.0
Author: Your Name
*/

if (!defined('ABSPATH')) exit;

define('SD_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('SD_PLUGIN_URL', plugin_dir_url(__FILE__));

/* ---------- Enqueue scripts/styles ---------- */
function sd_enqueue_assets() {
    // Select2 and DataTables from CDN
    wp_enqueue_style('sd-select2-css','https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css');
    wp_enqueue_style('sd-datatables-css','https://cdn.datatables.net/1.13.4/css/jquery.dataTables.min.css');
    wp_enqueue_style('sd-style', SD_PLUGIN_URL . 'assets/css/sd-style.css');

    wp_enqueue_script('sd-select2-js','https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js', array('jquery'), '4.1.0', true);
    wp_enqueue_script('sd-datatables-js','https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js', array('jquery'), '1.13.4', true);
    wp_enqueue_script('sd-main', SD_PLUGIN_URL . 'assets/js/sd-main.js', array('jquery','sd-select2-js','sd-datatables-js'), '1.0', true);

    // Pass metrics and AJAX vars to JS
    wp_localize_script('sd-main', 'SD_DATA', array(
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce'    => wp_create_nonce('sd_nonce'),
        'metrics'  => sd_get_metrics_array(),
        // optional: send a small sample of categorical values for some metrics if desired
    ));
}
add_action('wp_enqueue_scripts','sd_enqueue_assets');

/* ---------- Read screener_list.csv and return array ---------- */
function sd_get_metrics_array() {
    $file = SD_PLUGIN_DIR . 'data/screener_list.csv';
    $out = array();
    if (!file_exists($file)) return $out;
    if (($h = fopen($file, 'r')) !== false) {
        $header = fgetcsv($h);
        while (($row = fgetcsv($h)) !== false) {
            if (!count($row)) continue;
            $r = array_combine($header, $row);
            // Expect columns: metric,datatype,statement
            $out[] = array(
                'id' => $r['metric'],
                'label' => $r['statement'],
                'datatype' => $r['datatype']
            );
        }
        fclose($h);
    }
    return $out;
}

/* ---------- Shortcode output: filter area + table placeholder ---------- */
function sd_shortcode_handler($atts) {
    ob_start(); ?>
    <div class="sd-wrapper">
      <div id="sd-controls">
        <div id="sd-filters"></div>
        <div class="sd-controls-buttons">
          <button id="sd-add-filter" class="button">+ Add filter</button>
          <button id="sd-apply-filters" class="button button-primary">Apply Filters</button>
          <button id="sd-reset-filters" class="button">Reset</button>
        </div>
      </div>

      <div id="sd-results-wrap">
        <table id="sd-results" class="display" style="width:100%">
          <thead><tr id="sd-table-head"></tr></thead>
          <tbody></tbody>
        </table>
      </div>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode('screener-dropdown','sd_shortcode_handler');

/* ---------- AJAX handler: filter dataset server-side ---------- */
add_action('wp_ajax_sd_filter', 'sd_ajax_filter');
add_action('wp_ajax_nopriv_sd_filter', 'sd_ajax_filter');

function sd_ajax_filter() {
    check_ajax_referer('sd_nonce','nonce');
    $filters = isset($_POST['filters']) ? $_POST['filters'] : array();

    $data_file = SD_PLUGIN_DIR . 'data/screener_data.csv';
    if (!file_exists($data_file)) {
        wp_send_json_error('Data file not found in plugin/data');
    }

    // stream CSV and filter row-by-row so it's memory-friendly
    $h = fopen($data_file, 'r');
    $header = fgetcsv($h);
    $results = array();
    $limit = 500; // safe cap for demo — adjust as needed
    while (($row = fgetcsv($h)) !== false) {
        $assoc = array_combine($header, $row);
        if (sd_row_matches_filters($assoc, $filters)) {
            $results[] = $assoc;
            if (count($results) >= $limit) break;
        }
    }
    fclose($h);

    wp_send_json_success(array('columns' => $header, 'data' => $results));
}

/* ---------- Filter row evaluator (simple, robust) ---------- */
function sd_row_matches_filters($row, $filters) {
    if (!is_array($filters)) return true; // no filters => pass
    foreach ($filters as $f) {
        $metric = isset($f['metric']) ? $f['metric'] : '';
        $op     = isset($f['operator']) ? $f['operator'] : '';
        $value  = isset($f['value']) ? $f['value'] : null;

        if ($metric === '' || !array_key_exists($metric, $row)) return false;

        $cell = $row[$metric];

        // treat empty cells as non-matching (customizable)
        if ($cell === '' || $cell === null) return false;

        // numeric check
        if (is_numeric($cell) && (!is_array($value) ? is_numeric($value) : true)) {
            $num = floatval($cell);
            switch ($op) {
                case '>': if (!($num > floatval($value))) return false; break;
                case '<': if (!($num < floatval($value))) return false; break;
                case '>=': if (!($num >= floatval($value))) return false; break;
                case '<=': if (!($num <= floatval($value))) return false; break;
                case '=': if (!($num == floatval($value))) return false; break;
                case '!=': if (!($num != floatval($value))) return false; break;
                case 'between':
                    if (!is_array($value) || count($value) !== 2) return false;
                    if (!($num >= floatval($value[0]) && $num <= floatval($value[1]))) return false;
                    break;
                default: return false;
            }
        } else {
            // string-based operators
            $cell_l = strtolower($cell);
            if (is_array($value)) {
                $vals_l = array_map('strtolower', $value);
            } else {
                $vals_l = strtolower($value);
            }
            switch ($op) {
                case 'equals':
                    if (is_array($vals_l)) {
                        if (!in_array($cell_l, $vals_l)) return false;
                    } else {
                        if ($cell_l !== $vals_l) return false;
                    }
                    break;
                case 'contains':
                    if (strpos($cell_l, $vals_l) === false) return false;
                    break;
                case 'starts':
                    if (strpos($cell_l, $vals_l) !== 0) return false;
                    break;
                case 'ends':
                    if (substr($cell_l, -strlen($vals_l)) !== $vals_l) return false;
                    break;
                case 'in':
                    if (!is_array($value) || !in_array($cell, $value)) return false;
                    break;
                default:
                    return false;
            }
        }
    }
    return true;
}

/* ---------- Optional helper to import CSV to DB on activation (not required) ---------- */
register_activation_hook(__FILE__, 'sd_on_activate');
function sd_on_activate() {
    // Optionally import CSV into a custom table for better performance
    // Implementation left as exercise — see "Performance" section below
}
