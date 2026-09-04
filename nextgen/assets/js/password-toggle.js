(function () {
    'use strict';

    document.querySelectorAll('.password-toggle-wrap').forEach(function (wrap) {
        var input = wrap.querySelector('input');
        var btn = wrap.querySelector('.password-toggle-btn');
        if (!input || !btn) {
            return;
        }
        var iconShow = btn.querySelector('.password-toggle-icon--show');
        var iconHide = btn.querySelector('.password-toggle-icon--hide');

        btn.addEventListener('click', function () {
            var visible = input.type === 'text';
            input.type = visible ? 'password' : 'text';
            btn.setAttribute('aria-pressed', visible ? 'false' : 'true');
            var label = visible ? 'Jelszó megjelenítése' : 'Jelszó elrejtése';
            btn.setAttribute('aria-label', label);
            btn.title = label;
            if (iconShow) {
                iconShow.hidden = !visible;
            }
            if (iconHide) {
                iconHide.hidden = visible;
            }
        });
    });
})();
