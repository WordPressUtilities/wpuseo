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

function wpuseo_getcookie(cname) {
    var name = cname + "=";
    var decodedCookie = decodeURIComponent(document.cookie);
    var ca = decodedCookie.split(';');
    for (var i = 0; i < ca.length; i++) {
        var c = ca[i];
        while (c.charAt(0) == ' ') {
            c = c.substring(1);
        }
        if (c.indexOf(name) == 0) {
            return c.substring(name.length, c.length);
        }
    }
    return "";
}

/* ----------------------------------------------------------
  Display Cookie notice
---------------------------------------------------------- */

jQuery(document).ready(function wpuseo_cookienotice($) {
    var _hasCookies = wpuseo_getcookie('wpuseo_cookies');
    /* User has enabled or refused cookies */
    if (_hasCookies == '1' || _hasCookies == '-1') {
        return;
    }
    /* Enable cookie notice */
    jQuery('body').attr('data-cookie-notice', 1);

    /* Actions */
    jQuery('.cookie-notice').on('click', '[data-cookie-action]', function(e) {
        e.preventDefault();
        var $this = jQuery(this);
        /* Set cookie */
        wpuseo_setcookie('wpuseo_cookies', $this.attr('data-cookie-action'));
        /* Disable cookie notice */
        jQuery('body').attr('data-cookie-notice', 0);

    });

});
