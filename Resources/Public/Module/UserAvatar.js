// Backend modules (Fluid).
// 1. Every module: put the user's photo on the initials (Neos 9) or the user icon (Neos 8) in the
//    user menu. Without a photo, /neos/user-avatar answers 404 and nothing changes.
// 2. User Settings: add a "Profile photo" block. Its controls belong to a form of their own outside
//    the Neos user form (forms cannot be nested), which posts to /neos/user-avatar/upload or /remove.
(function () {
    'use strict';

    var BASE_URI = '/neos/user-avatar';
    var FORM_ID = 'upassist-useravatar-form';

    function element(tag, attributes, children) {
        var node = document.createElement(tag);
        Object.keys(attributes || {}).forEach(function (name) {
            if (name === 'text') {
                node.textContent = attributes[name];
            } else {
                node.setAttribute(name, attributes[name]);
            }
        });
        (children || []).forEach(function (child) {
            if (child) {
                node.appendChild(child);
            }
        });
        return node;
    }

    function decorateUserMenu(menus) {
        var image = new Image();
        image.onload = function () {
            Array.prototype.forEach.call(menus, function (menu) {
                menu.style.setProperty('--upassist-useravatar-image', 'url("' + image.src + '")');
                menu.classList.add('upassist-useravatar--loaded');
            });
        };
        image.src = BASE_URI;
    }

    function isUserSettingsPage() {
        return /\/user\/usersettings(\/|$)/.test(window.location.pathname) && document.querySelector('form.user-edit') !== null;
    }

    function buildBlock(settings, csrfToken) {
        var labels = settings.labels;
        var form = element('form', {
            id: FORM_ID,
            method: 'post',
            action: BASE_URI + '/upload',
            enctype: 'multipart/form-data',
            hidden: 'hidden'
        }, [element('input', {type: 'hidden', name: '__csrfToken', value: csrfToken})]);

        var photo = null;
        var none = element('p', {text: labels.none});
        if (settings.photoUri) {
            none.hidden = true;
            photo = element('img', {'class': 'upassist-useravatar__photo', src: settings.photoUri, alt: '', width: '96', height: '96'});
            photo.onerror = function () {
                photo.hidden = true;
                none.hidden = false;
            };
        }

        var gravatarNote = settings.gravatarNote ? element('p', {'class': 'neos-help-block', text: settings.gravatarNote}) : null;

        var buttons = element('div', {'class': 'upassist-useravatar__buttons'}, [
            element('button', {type: 'submit', form: FORM_ID, 'class': 'neos-button neos-button-primary', text: labels.save}),
            settings.hasUpload ? element('button', {
                type: 'submit',
                form: FORM_ID,
                formaction: BASE_URI + '/remove',
                formnovalidate: 'formnovalidate',
                'class': 'neos-button neos-button-danger',
                text: labels.remove
            }) : null
        ]);

        var fieldset = element('fieldset', {'class': 'neos-span11 upassist-useravatar'}, [
            element('legend', {text: labels.heading}),
            element('div', {'class': 'upassist-useravatar__current'}, [photo, none]),
            element('div', {'class': 'neos-control-group'}, [
                element('label', {'class': 'neos-control-label', 'for': 'upassist-useravatar-file', text: labels.upload}),
                element('div', {'class': 'neos-controls'}, [
                    element('input', {type: 'file', name: 'avatar', id: 'upassist-useravatar-file', form: FORM_ID, accept: settings.accept, required: 'required'}),
                    element('p', {'class': 'neos-help-block', text: labels.uploadHelp})
                ])
            ]),
            gravatarNote,
            buttons
        ]);

        return {form: form, row: element('div', {'class': 'neos-row-fluid'}, [fieldset])};
    }

    function addSettingsBlock(csrfToken) {
        var userForm = document.querySelector('form.user-edit');
        var firstRow = userForm.querySelector('.neos-row-fluid');
        if (!firstRow || !csrfToken) {
            return;
        }
        fetch(BASE_URI + '/settings', {credentials: 'same-origin', headers: {Accept: 'application/json'}})
            .then(function (response) {
                return response.ok ? response.json() : null;
            })
            .then(function (settings) {
                if (!settings || !settings.labels) {
                    return;
                }
                var block = buildBlock(settings, csrfToken);
                document.body.appendChild(block.form);
                firstRow.parentNode.insertBefore(block.row, firstRow.nextSibling);
            })
            .catch(function () {
                // No block: the page stays as Neos renders it.
            });
    }

    function apply() {
        var menus = document.querySelectorAll('.neos-user-menu');
        if (menus.length > 0) {
            decorateUserMenu(menus);
        }
        if (isUserSettingsPage()) {
            var tokenHolder = document.querySelector('[data-csrf-token]');
            addSettingsBlock(tokenHolder ? tokenHolder.getAttribute('data-csrf-token') : null);
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', apply);
    } else {
        apply();
    }
})();
