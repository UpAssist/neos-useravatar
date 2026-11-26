// Backend modules (Fluid): put the user's photo on the initials (Neos 9) or the user icon (Neos 8)
// in the user menu. Without a photo, /neos/user-avatar answers 404 and nothing changes.
(function () {
    'use strict';

    var AVATAR_URI = '/neos/user-avatar';

    function apply() {
        var menus = document.querySelectorAll('.neos-user-menu');
        if (menus.length === 0) {
            return;
        }
        var image = new Image();
        image.onload = function () {
            Array.prototype.forEach.call(menus, function (menu) {
                menu.style.setProperty('--upassist-useravatar-image', 'url("' + image.src + '")');
                menu.classList.add('upassist-useravatar--loaded');
            });
        };
        image.src = AVATAR_URI;
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', apply);
    } else {
        apply();
    }
})();
