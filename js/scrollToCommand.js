(function($, Drupal) {
    /**
     * Add new command for reading a message.
     */
    Drupal.AjaxCommands.prototype.scrollTo = function(ajax, response, status){
        /*var nav = $('.navbar-fixed-top');
        var bottom = nav.offset().top - nav.height();*/
        $('html, body').animate({
            scrollTop: $(response.element).first().offset().top - 170
        }, 500);
    }
})(jQuery, Drupal);