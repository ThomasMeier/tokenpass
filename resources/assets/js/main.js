$(function(){
  $(document).on('scroll', function (e) {
    var stop = Math.round($(window).scrollTop());
    if (stop > 1){
      $('.landing-nav').removeClass('pristine');
    } else {
      $('.landing-nav').addClass('pristine');
    }
  });

  $('.toggle-mobile-nav').on('click', function (e) {
    var $nav = $('#mobile-nav')
    if ($nav.hasClass('active')) {
      $nav.removeClass('active');
    } else {
      $nav.addClass('active');
    }
  });

  $('[data-tooltip]').on('click', function (e) {
    var $this = $(this);
    var uniqid = $this.data('tooltipid');
    if (uniqid) {
      $('#' + uniqid).remove();
      $this.data('tooltipid', null)
    } else {
      var text = $this.data('tooltip');
      uniqid = Date.now();
      $this.data('tooltipid', uniqid);
      var $tooltip = $.parseHTML('<div id="' + uniqid + '" class="tooltip">' + text + '</div>');
      $this.append($tooltip);
    }
  });

  $('.active-toggle-module').on('click', function(e) {
    var $this = $(this);
    var currentState = parseInt($this.attr('data-toggle'));
    var newState = currentState ? 0 : 1;
    $this.attr('data-toggle', newState);
  });

  // Responsive tables
  var tables = $('.table--responsive');

  $.each(tables, function(i, table) {
    var $table = $(tables[i]);
    var headings = $table.find('thead').find('th');
    var dataRows = $table.find('tbody').find('tr');
    $.each(dataRows, function(i, dataRow) {
      var dataCells = $(dataRow).find('td');
      $.each(dataCells, function(i, datum) {
        $(datum).attr('data-heading', headings[i].innerText);
      });
    });
  });

  // Use jQuery UI datepicker if html5 is unavailable
  if (!Modernizr.inputtypes.date) {
      $('input[type=date]').datepicker({
          // Consistent format with the HTML5 picker
          dateFormat: 'yy-mm-dd'
      });
  }
});