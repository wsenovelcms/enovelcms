

(function () {
    'use strict';
    var L = window.admLang || {};

    
    function el(tag, attrs, children) {
        var node = document.createElement(tag);
        if (attrs) {
            Object.keys(attrs).forEach(function (k) {
                if (k === 'className') node.className = attrs[k];
                else if (k === 'text') node.textContent = attrs[k];
                else if (k === 'html') node.innerHTML = attrs[k];
                else if (k === 'style' && typeof attrs[k] === 'object') {
                    Object.assign(node.style, attrs[k]);
                } else if (k.indexOf('on') === 0 && typeof attrs[k] === 'function') {
                    node.addEventListener(k.slice(2), attrs[k]);
                } else if (attrs[k] !== null && attrs[k] !== undefined) {
                    node.setAttribute(k, attrs[k]);
                }
            });
        }
        if (children) {
            (Array.isArray(children) ? children : [children]).forEach(function (c) {
                if (c == null) return;
                node.appendChild(typeof c === 'string' ? document.createTextNode(c) : c);
            });
        }
        return node;
    }

    
    var toastBox = null;
    function toast(msg, type) {
        if (!toastBox) {
            toastBox = el('div', { className: 'ui-toast-box' });
            document.body.appendChild(toastBox);
        }
        var t = type || 'info';
        var iconMap = { success: 'fa-check-circle', error: 'fa-exclamation-circle', info: 'fa-circle-info' };
        var item = el('div', { className: 'ui-toast ui-toast-' + t }, [
            el('i', { className: 'fas ' + (iconMap[t] || iconMap.info) }),
            el('span', { text: String(msg) })
        ]);
        toastBox.appendChild(item);
        requestAnimationFrame(function () { item.classList.add('ui-show'); });
        setTimeout(function () {
            item.classList.remove('ui-show');
            setTimeout(function () { if (item.parentNode) item.parentNode.removeChild(item); }, 220);
        }, 2600);
    }

    
    function openModal(opts) {
        return new Promise(function (resolve) {
            var closed = false;
            function close(result) {
                if (closed) return;
                closed = true;
                if (mask.parentNode) mask.parentNode.removeChild(mask);
                document.removeEventListener('keydown', onKey);
                resolve(result);
            }

            var titleIcon = opts.titleIcon
                ? el('i', { className: 'fas ' + opts.titleIcon, style: { marginRight: '6px' } })
                : null;
            var closeBtn = el('button', {
                type: 'button', className: 'modal-close',
                'aria-label': L.ui_close || 'Close', html: '&times;', onclick: function () { close(opts.cancelValue !== undefined ? opts.cancelValue : null); }
            });
            var head = el('div', { className: 'modal-head' }, [
                el('h3', {}, [titleIcon, opts.title || '']),
                closeBtn
            ]);
            var body = el('div', { className: 'modal-body' }, [opts.body]);
            var foot = el('div', { className: 'modal-foot' });

            if (opts.showCancel) {
                foot.appendChild(el('button', {
                    type: 'button', className: 'btn btn-outline',
                    text: opts.cancelText || L.ui_cancel || 'Cancel',
                    onclick: function () { close(opts.cancelValue !== undefined ? opts.cancelValue : false); }
                }));
            }
            var okBtn = el('button', { type: 'button', className: 'btn', text: opts.okText || L.ui_ok || 'OK' });
            foot.appendChild(okBtn);

            var modal = el('div', { className: 'modal' }, [head, body, foot]);
            var mask = el('div', { className: 'modal-mask ui-modal' }, [modal]);
            if (opts.wide) mask.classList.add('ui-wide');
            mask.addEventListener('click', function (e) {
                if (e.target === mask) close(opts.cancelValue !== undefined ? opts.cancelValue : null);
            });
            function onKey(e) { if (e.key === 'Escape') close(opts.cancelValue !== undefined ? opts.cancelValue : null); }
            document.addEventListener('keydown', onKey);

            okBtn.addEventListener('click', function () {
                var ret = opts.onOk ? opts.onOk(okBtn) : undefined;
                if (ret && typeof ret.then === 'function') {
                    okBtn.disabled = true;
                    ret.then(function (v) { if (v !== false) close(v === undefined ? true : v); else okBtn.disabled = false; });
                } else if (ret !== false) {
                    close(ret === undefined ? true : ret);
                }
            });

            document.body.appendChild(mask);
            requestAnimationFrame(function () { mask.classList.add('show'); });
            if (opts.focus) opts.focus();
        });
    }

    
    function alertBox(opts) {
        var o = opts || {};
        var iconClass = o.type === 'success' ? 'ui-icon-success'
            : o.type === 'error' ? 'ui-icon-error' : 'ui-icon-info';
        var fasIcon = o.type === 'success' ? 'fa-circle-check'
            : o.type === 'error' ? 'fa-circle-exclamation' : 'fa-circle-info';
        var inner;
        if (o.type) {
            inner = el('div', { className: 'ui-text-icon' }, [
                el('i', { className: 'fas ' + fasIcon + ' ' + iconClass }),
                el('p', { className: 'ui-text', text: o.message || '' })
            ]);
        } else {
            inner = el('p', { className: 'ui-text', text: o.message || '' });
        }
        return openModal({
            title: o.title || L.ui_tip || 'Notice',
            titleIcon: o.titleIcon || null,
            body: inner,
            okText: o.okText || L.ui_ok || 'OK',
            showCancel: false,
            cancelValue: null
        });
    }

    
    function confirmBox(opts) {
        var o = opts || {};
        return openModal({
            title: o.title || L.ui_confirm || 'Please confirm',
            titleIcon: o.titleIcon || 'fa-circle-question',
            body: el('p', { className: 'ui-text', text: o.message || '' }),
            okText: o.okText || L.ui_ok || 'OK',
            cancelText: o.cancelText || L.ui_cancel || 'Cancel',
            showCancel: true,
            cancelValue: false
        });
    }

    
    function imagePreview(opts) {
        var o = opts || {};
        var img = el('img', { className: 'ui-image', src: o.src || '', alt: o.title || '' });
        return openModal({
            title: o.title || L.ui_image || 'Image',
            titleIcon: o.titleIcon || 'fa-image',
            body: img,
            okText: o.okText || L.ui_close || 'Close',
            showCancel: false,
            cancelValue: null,
            wide: true
        });
    }

    function renderField(field) {
        var wrapper = el('div', { className: 'ui-field' });
        if (field.label) wrapper.appendChild(el('label', { className: 'ui-label', text: field.label }));
        var ctrl;
        if (field.type === 'textarea') {
            ctrl = el('textarea', {
                className: 'form-control', name: field.name,
                rows: field.rows || 3, placeholder: field.placeholder || '',
                maxlength: field.maxlength || null
            });
        } else if (field.type === 'select') {
            ctrl = el('select', { className: 'form-control', name: field.name });
            (field.options || []).forEach(function (op) {
                ctrl.appendChild(el('option', { value: op.value, text: op.label }));
            });
        } else if (field.type === 'checkbox') {
            var check = el('label', { className: 'ui-check' }, [
                el('input', { type: 'checkbox', name: field.name, value: field.value || '1' }),
                document.createTextNode(field.text || field.label || '')
            ]);
            wrapper.innerHTML = '';
            wrapper.appendChild(check);
            ctrl = wrapper.querySelector('input');
        } else if (field.type === 'html') {
            wrapper.innerHTML = '';
            wrapper.appendChild(el('div', { className: 'ui-html', html: field.html || '' }));
            ctrl = null;
        } else if (field.type === 'captcha') {
            var capInput = el('input', {
                className: 'form-control', type: 'text', name: field.name || 'captcha',
                placeholder: field.placeholder || L.ui_captcha_ph || 'Enter the captcha code', autocomplete: 'off', required: 'required'
            });
            var capImg = el('img', {
                className: 'ui-captcha-img', src: '/api/captcha',
                alt: L.ui_captcha || 'Captcha', title: L.ui_refresh || 'Click to refresh'
            });
            capImg.addEventListener('click', function () {
                capImg.src = '/api/captcha?' + Date.now();
            });
            wrapper.appendChild(el('div', { className: 'ui-captcha' }, [capInput, capImg]));
            ctrl = capInput;
        } else {
            ctrl = el('input', {
                className: 'form-control', type: field.type || 'text', name: field.name,
                placeholder: field.placeholder || '', value: field.value !== undefined ? field.value : '',
                min: field.min, max: field.max, step: field.step, maxlength: field.maxlength,
                required: field.required ? 'required' : null
            });
        }
        if (field.type !== 'checkbox' && field.type !== 'captcha' && field.type !== 'html') wrapper.appendChild(ctrl);
        return { node: wrapper, control: ctrl, field: field };
    }

    
    function formBox(opts) {
        var o = opts || {};
        var rendered = (o.fields || []).map(renderField);
        var formBody = el('div', {}, rendered.map(function (r) { return r.node; }));

        function collect() {
            var values = {};
            for (var i = 0; i < rendered.length; i++) {
                var r = rendered[i], f = r.field;
                if (!r.control) continue;
                if (f.type === 'checkbox') values[f.name] = r.control.checked ? (r.control.value || 1) : 0;
                else values[f.name] = r.control.value;
            }
            return values;
        }
        function validate() {
            for (var i = 0; i < rendered.length; i++) {
                var r = rendered[i], f = r.field;
                if (!r.control) continue;
                if (f.required) {
                    var v = f.type === 'checkbox' ? r.control.checked : (r.control.value && r.control.value !== '');
                    if (!v) { toast((f.label || f.name) + ' ' + (L.ui_required || 'is required'), 'error'); return false; }
                }
                if (f.type === 'number' && r.control.value !== '') {
                    var n = parseFloat(r.control.value);
                    if (isNaN(n)) { toast(L.ui_number || 'Enter a valid number', 'error'); return false; }
                    if (f.min !== undefined && n < parseFloat(f.min)) { toast((L.ui_min || 'Must be at least') + ' ' + f.min, 'error'); return false; }
                    if (f.max !== undefined && n > parseFloat(f.max)) { toast((L.ui_max || 'Must be at most') + ' ' + f.max, 'error'); return false; }
                }
            }
            return true;
        }

        var firstInput = null;
        for (var k = 0; k < rendered.length; k++) { if (rendered[k].control) { firstInput = rendered[k].control; break; } }
        return openModal({
            title: o.title || L.ui_form || 'Form',
            titleIcon: o.titleIcon || null,
            body: formBody,
            okText: o.okText || L.ui_submit || 'Submit',
            showCancel: true,
            cancelText: o.cancelText || L.ui_cancel || 'Cancel',
            cancelValue: null,
            focus: function () { if (firstInput) firstInput.focus(); },
            onOk: function () {
                if (!validate()) return false;
                if (o.onSubmit) {
                    var r = o.onSubmit(collect());
                    if (r && typeof r.then === 'function') return r.then(function (ok) { return ok === false ? false : collect(); });
                    if (r === false) return false;
                }
                return collect();
            }
        });
    }

    

    function submitForm(form, submitter) {
        if (submitter && typeof form.requestSubmit === 'function') {
            form.requestSubmit(submitter);
            return;
        }
        if (submitter && submitter.name) {
            var stash = el('input', { type: 'hidden', name: submitter.name, value: submitter.value });
            form.appendChild(stash);
        }
        form.submit();
    }

    document.addEventListener('click', function (e) {
        var a = e.target.closest ? e.target.closest('a[data-confirm]') : null;
        if (a) {
            e.preventDefault();
            confirmBox({ message: a.getAttribute('data-confirm') }).then(function (ok) {
                if (ok) window.location.href = a.href;
            });
            return;
        }
        var btn = e.target.closest ? e.target.closest('button[data-confirm][type="submit"]') : null;
        if (btn) {
            e.preventDefault();
            confirmBox({ message: btn.getAttribute('data-confirm') }).then(function (ok) {
                if (ok) { var f = btn.closest('form'); if (f) submitForm(f, btn); }
            });
        }
    });
    document.addEventListener('submit', function (e) {
        var f = e.target.closest ? e.target.closest('form[data-confirm]') : null;
        if (!f) return;
        e.preventDefault();
        confirmBox({ message: f.getAttribute('data-confirm') }).then(function (ok) {
            if (ok) f.submit();
        });
    });

    function initCheckAll() {
        document.addEventListener('change', function (e) {
            var master = e.target.closest ? e.target.closest('[data-check-all]') : null;
            if (master) {
                var group = master.getAttribute('data-check-all');
                document.querySelectorAll('[data-check-item="' + group + '"]').forEach(function (item) {
                    item.checked = master.checked;
                });
                return;
            }
            var single = e.target.closest ? e.target.closest('[data-check-item]') : null;
            if (single) {
                var g = single.getAttribute('data-check-item');
                var items = document.querySelectorAll('[data-check-item="' + g + '"]');
                var m = document.querySelector('[data-check-all="' + g + '"]');
                if (m) m.checked = items.length > 0 && Array.prototype.every.call(items, function (i) { return i.checked; });
            }
        });
    }

    document.addEventListener('DOMContentLoaded', initCheckAll);

    function refreshCaptcha(img) {
        img.src = '/api/captcha?' + Date.now();
        var wrap = img.closest('.captcha-wrap');
        var input = wrap ? wrap.querySelector('.captcha-input') : null;
        if (input) { input.value = ''; input.focus(); }
    }

    function initCaptcha() {
        var imgs = document.querySelectorAll('.captcha-img');
        for (var i = 0; i < imgs.length; i++) {
            (function (img) {
                img.addEventListener('click', function () { refreshCaptcha(img); });
                var wrap = img.closest('.captcha-wrap');
                var hint = wrap ? wrap.querySelector('.captcha-refresh-hint') : null;
                if (hint) hint.addEventListener('click', function () { refreshCaptcha(img); });
            })(imgs[i]);
        }
    }

    document.addEventListener('DOMContentLoaded', initCaptcha);
    window.addEventListener('pageshow', function (e) {
        if (!e.persisted) return;
        var imgs = document.querySelectorAll('.captcha-img');
        for (var i = 0; i < imgs.length; i++) {
            imgs[i].src = '/api/captcha?' + Date.now();
            var wrap = imgs[i].closest('.captcha-wrap');
            var input = wrap ? wrap.querySelector('.captcha-input') : null;
            if (input) input.value = '';
        }
    });

    window.UI = {
        toast: toast,
        alert: alertBox,
        confirm: confirmBox,
        form: formBox,
        image: imagePreview
    };
})();
