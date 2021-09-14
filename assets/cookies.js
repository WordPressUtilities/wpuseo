/* ----------------------------------------------------------
  WPU SEO Cookies Helpers
---------------------------------------------------------- */
/* Thx : https://www.w3schools.com/js/js_cookies.asp */

function wpuseo_setcookie(cname, cvalue, exdays) {
    var d = new Date();
    d.setTime(d.getTime() + (exdays * 24 * 60 * 60 * 1000));
    var expires = "expires=" + d.toUTCString();
    document.cookie = cname + "=" + cvalue + ";" + expires + ";path=/";
}

/* ----------------------------------------------------------
  Display Cookie notice
---------------------------------------------------------- */

jQuery(document).ready(function wpuseo_cookienotice($) {
    /* Cookies are not needed */
    if(typeof wpuseo_getcookie == 'undefined'){
        return;
    }
    var _hasCookies = wpuseo_getcookie('wpuseo_cookies');

    /* Actions */
    jQuery('.cookie-notice').on('click', '[data-cookie-action]', function(e) {
        e.preventDefault();
        var $this = jQuery(this),
            _action = $this.attr('data-cookie-action');

        /* Set cookie */
        wpuseo_setcookie('wpuseo_cookies', _action);

        /* Hide cookie notice */
        jQuery('body').attr('data-cookie-notice', 0);

        /* Restart analytics & pixel if available */
        if (_action == '1') {
            if (typeof wpuseo_init_analytics == 'function') {
                wpuseo_init_analytics();
            }
            if (typeof wpuseo_init_fbpixel == 'function') {
                wpuseo_init_fbpixel();
            }
            if (typeof wpuseo_init_custom_tracking == 'function') {
                wpuseo_init_custom_tracking();
            }
        }
    });

    /* User has enabled or refused cookies */
    if (_hasCookies == '1' || _hasCookies == '-1') {
        return;
    }

    /* Display cookie notice */
    jQuery('body').attr('data-cookie-notice', 1);

});

