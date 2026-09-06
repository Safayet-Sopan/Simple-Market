(function () {
    'use strict';

    function labelFor(field) {
        return field.getAttribute('data-label') || field.name || 'This field';
    }

    function errorSlot(field) {
        var next = field.nextElementSibling;
        while (next) {
            if (next.className && next.className.indexOf('error') !== -1) {
                return next;
            }
            if (next.tagName === 'INPUT' || next.tagName === 'SELECT' || next.tagName === 'TEXTAREA') {
                return null;
            }
            next = next.nextElementSibling;
        }
        return null;
    }

    function showError(field, message) {
        var slot = errorSlot(field);
        if (slot) {
            slot.textContent = message;
        }
        if (message) {
            field.setAttribute('aria-invalid', 'true');
        } else {
            field.removeAttribute('aria-invalid');
        }
    }

    function validateField(form, field) {
        var value = (field.value || '').trim();
        var label = labelFor(field);

        var requiredRole = field.getAttribute('data-required-if-role');
        var roleSelect = form.querySelector('#role-select');
        var conditionallyRequired = requiredRole && roleSelect && roleSelect.value === requiredRole;

        if (field.hasAttribute('data-required') || conditionallyRequired) {
            if (value === '') {
                return label + ' is required';
            }
        }

        if (value === '') {
            return '';
        }

        if (field.hasAttribute('data-email')) {
            if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value)) {
                return 'Invalid email format';
            }
        }

        if (field.hasAttribute('data-password')) {
            if (value.length < 8) {
                return 'Password must be at least 8 characters';
            }
            if (!/[A-Za-z]/.test(value) || !/[0-9]/.test(value)) {
                return 'Password must contain at least one letter and one number';
            }
        }

        var pattern = field.getAttribute('data-pattern');
        if (pattern) {
            var re;
            try {
                re = new RegExp(pattern);
            } catch (e) {
                re = null;
            }
            if (re && !re.test(value)) {
                return field.getAttribute('data-pattern-message') || (label + ' is not valid');
            }
        }

        var matchName = field.getAttribute('data-match');
        if (matchName) {
            var other = form.elements[matchName];
            if (other && other.value !== field.value) {
                return field.getAttribute('data-match-message') || (label + ' does not match');
            }
        }

        if (field.hasAttribute('data-integer')) {
            if (!/^\d+$/.test(value)) {
                return label + ' must be a whole number';
            }
        }

        if (field.hasAttribute('data-number') || field.hasAttribute('data-integer')) {
            var num = parseFloat(value);
            if (isNaN(num)) {
                return label + ' must be a number';
            }
            var min = field.getAttribute('data-min');
            var max = field.getAttribute('data-max');
            if (min !== null && num < parseFloat(min)) {
                return label + ' must be at least ' + min;
            }
            if (max !== null && num > parseFloat(max)) {
                return label + ' must be at most ' + max;
            }
        }

        var maxLength = field.getAttribute('data-maxlength');
        if (maxLength !== null && value.length > parseInt(maxLength, 10)) {
            return 'Keep ' + label.toLowerCase() + ' under ' + maxLength + ' characters';
        }

        return '';
    }

    function fieldsOf(form) {
        return form.querySelectorAll('[data-label]');
    }

    function wire(form) {
        var fields = fieldsOf(form);
        if (fields.length === 0) {
            return;
        }

        Array.prototype.forEach.call(fields, function (field) {
            field.addEventListener('blur', function () {
                showError(field, validateField(form, field));
            });
            field.addEventListener('input', function () {
                if (field.getAttribute('aria-invalid') === 'true') {
                    showError(field, validateField(form, field));
                }
            });
        });

        form.addEventListener('submit', function (event) {
            var firstBad = null;

            Array.prototype.forEach.call(fieldsOf(form), function (field) {
                var message = validateField(form, field);
                showError(field, message);
                if (message && !firstBad) {
                    firstBad = field;
                }
            });

            if (firstBad) {
                event.preventDefault();
                firstBad.focus();
            }
        });
    }

    function wireRoleFields() {
        var roleSelect = document.getElementById('role-select');
        if (!roleSelect) {
            return;
        }

        var sellerFields = document.getElementById('seller-fields');
        var riderFields = document.getElementById('rider-fields');

        function toggle() {
            var role = roleSelect.value;
            if (sellerFields) {
                sellerFields.style.display = (role === 'seller') ? 'block' : 'none';
            }
            if (riderFields) {
                riderFields.style.display = (role === 'rider') ? 'block' : 'none';
            }
        }

        roleSelect.addEventListener('change', toggle);
        toggle();
    }

    document.addEventListener('DOMContentLoaded', function () {
        Array.prototype.forEach.call(document.querySelectorAll('form'), wire);
        wireRoleFields();
    });
})();
