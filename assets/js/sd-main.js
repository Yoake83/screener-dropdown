jQuery(document).ready(function($){
  const metrics = SD_DATA.metrics || []; // [{id,label,datatype}, ...]
  const $filters = $('#sd-filters');
  const $add = $('#sd-add-filter');
  const $apply = $('#sd-apply-filters');
  const $reset = $('#sd-reset-filters');
  let rowCounter = 0;
  let table = null;

  const operatorSets = {
    numeric: [
      {id:'>', text:'>'},
      {id:'<', text:'<'},
      {id:'>=', text:'>='},
      {id:'<=', text:'<='},
      {id:'=', text:'='},
      {id:'!=', text:'!='},
      {id:'between', text:'between'}
    ],
    string: [
      {id:'equals', text:'equals'},
      {id:'contains', text:'contains'},
      {id:'starts', text:'starts with'},
      {id:'ends', text:'ends with'},
      {id:'in', text:'in (multi)'}
    ],
    categorical: [ 
      {id:'equals', text:'equals'},
      {id:'in', text:'in (multi)'}
    ]
  };

  function createMetricSelect(id){
    const $s = $('<select class="sd-metric" />').attr('data-row', id);
    $s.append($('<option>').val('').text('-- select metric --'));
    metrics.forEach(m => $s.append($('<option>').val(m.id).attr('data-dtype', m.datatype).text(m.label)));
    return $s;
  }

  function createOperatorSelect(id, dtype){
    const ops = operatorSets[dtype] || operatorSets.string;
    const $s = $('<select class="sd-operator" />').attr('data-row',id);
    ops.forEach(o => $s.append($('<option>').val(o.id).text(o.text)));
    return $s;
  }

  function createValueInput(id, dtype){
    // for 'in' or multiple select use Select2 with multiple
    if (dtype === 'categorical' || dtype === 'string') {
      return $('<input type="text" class="sd-value" />').attr('data-row', id);
    } else { // numeric or default
      return $('<input type="text" class="sd-value" />').attr('data-row', id).attr('placeholder','number or comma-separated for between');
    }
  }

  function addFilterRow(prefill){
    rowCounter++;
    const id = 'row'+rowCounter;
    const $row = $('<div class="sd-filter-row" />').attr('data-id', id);
    const $metric = createMetricSelect(id);
    const $operator = createOperatorSelect(id,'string');
    const $value = createValueInput(id,'string');
    const $del = $('<button class="sd-del button">Remove</button>');

    $row.append($('<div class="sd-col metric-col">').append($metric));
    $row.append($('<div class="sd-col operator-col">').append($operator));
    $row.append($('<div class="sd-col value-col">').append($value));
    $row.append($('<div class="sd-col del-col">').append($del));
    $filters.append($row);

    // Init Select2 on metric
    $metric.select2({width:'100%'});

    // If datatype changes -> update operator and value input
    $metric.on('change', function(){
      const dtype = $(this).find('option:selected').attr('data-dtype') || 'string';
      const $parent = $(this).closest('.sd-filter-row');
      $parent.find('.sd-operator').replaceWith(createOperatorSelect(id, dtype));
      $parent.find('.sd-value').replaceWith(createValueInput(id, dtype));
      $parent.find('.sd-operator').select2({width:'100%'});
      $parent.find('.sd-value').select2 ? $parent.find('.sd-value').select2({width:'100%'}) : null;
    });

    $del.on('click', function(){
      $row.remove();
    });

    // optionally prefill values
    if (prefill) {
      $metric.val(prefill.metric).trigger('change');
      setTimeout(function(){
        $row.find('.sd-operator').val(prefill.operator).trigger('change');
        $row.find('.sd-value').val(prefill.value);
      }, 50);
    }

    return $row;
  }

  $add.on('click', function(e){
    e.preventDefault();
    addFilterRow();
  });

  $reset.on('click', function(e){
    e.preventDefault();
    $filters.empty();
    if (table) {
      table.clear().draw();
      $('#sd-table-head').empty();
    }
  });

  $apply.on('click', function(e){
    e.preventDefault();

    const filters = [];

    $('.sd-filter-row').each(function(){
        const metric = $(this).find('.sd-metric').val();
        const operator = $(this).find('.sd-operator').val();
        let value = $(this).find('.sd-value').val();
        const dtype = $(this).find('.sd-metric option:selected').attr('data-dtype') || 'string';

        if (!metric || !operator || !value) return; // skip empty rows

        if (operator === 'between' || operator === 'in') {
            // split by comma
            value = value.split(',').map(v => v.trim());
            if (dtype === 'numeric') {
                value = value.map(v => Number(v)).filter(v => !isNaN(v));
            }
        } else if (dtype === 'numeric') {
            value = Number(value);
            if (isNaN(value)) return; // skip invalid numbers
        } else {
            value = value.trim(); // for strings
        }

        filters.push({metric: metric, operator: operator, value: value, dtype: dtype});
    });

    console.log('Filters sent:', filters); // debug

    $.ajax({
        url: SD_DATA.ajax_url,
        method: 'POST',
        dataType: 'json',
        data: {
            action: 'sd_filter',
            nonce: SD_DATA.nonce,
            filters: filters
        },
        success: function(resp){
            console.log('Server response:', resp); // debug

            if (!resp.success) {
                alert('Error: ' + (resp.data || 'unknown'));
                return;
            }

            const colnames = resp.data.columns;
            const rows = resp.data.data;

            $('#sd-table-head').empty();
            colnames.forEach(c => $('#sd-table-head').append($('<th>').text(c)));

            if (table) {
                table.destroy();
                $('#sd-results tbody').empty();
            }

            table = $('#sd-results').DataTable({
                data: rows.map(r => colnames.map(c => r[c])),
                columns: colnames.map(c => ({ title: c })),
                pageLength: 25,
                destroy: true,
                responsive: true
            });
        },
        error: function(xhr, status, err){
            alert('AJAX error: ' + err);
        }
    });
});


  // Add an initial empty filter row
  addFilterRow();
});
