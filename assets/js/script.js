jQuery(document).ready(function($) {
    // Clear filters button functionality
    $('#sc-clear-filters').on('click', function() {
        $('.sc-tag-checkbox, .sc-faculty-checkbox').prop('checked', false);
        $('.sc-tag-item, .sc-faculty-item').removeClass('sc-tag-selected sc-faculty-selected');
    });
    
    // Tag selection styling
    $('.sc-tag-checkbox').on('change', function() {
        $(this).closest('.sc-tag-item').toggleClass('sc-tag-selected', this.checked);
    });
    
    // Faculty selection styling
    $('.sc-faculty-checkbox').on('change', function() {
        $(this).closest('.sc-faculty-item').toggleClass('sc-faculty-selected', this.checked);
    });
});