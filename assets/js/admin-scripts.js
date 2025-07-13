jQuery(document).ready(function($) {
    'use strict';

    // Tab navigation
    $('.reoptimize-nav .nav-tab').on('click', function(e) {
        e.preventDefault();

        // Remove active class from all tabs and content
        $('.reoptimize-nav .nav-tab').removeClass('nav-tab-active');
        $('.reoptimize-tabs .tab-content').removeClass('active');

        // Add active class to the clicked tab
        $(this).addClass('nav-tab-active');

        // Show the corresponding content
        var targetTab = $(this).attr('href');
        $(targetTab).addClass('active');
    });

    // Trigger click on the first tab to show its content by default
    if ($('.reoptimize-nav .nav-tab.nav-tab-active').length === 0) {
        $('.reoptimize-nav .nav-tab:first').trigger('click');
    }
});

