(function ($, Drupal) {
    "use strict";

    Drupal.behaviors.proposal = {
        attach: function (context, settings) {
            $('.field--name-field-line-item >.field--item:nth-child(2)', context).block({
                message: '<h3>Service not compatible with selected</h3>',
                css: { border: '1px solid #000' }
            });
        }
    };
});