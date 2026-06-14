/**
 * utils.js
 * Global helpers for Learn&Quiz administration panel.
 */

// Configure CSRF token setup for all jQuery AJAX requests
$.ajaxSetup({
    headers: {
        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
    }
});

/**
 * Display a premium notification toast using SweetAlert2.
 * @param {string} type - success, error, warning, info
 * @param {string} message - Message to display
 */
window.showToast = function(type, message) {
    const Toast = Swal.mixin({
        toast: true,
        position: 'top-end',
        showConfirmButton: false,
        timer: 3000,
        timerProgressBar: true,
        didOpen: (toast) => {
            toast.addEventListener('mouseenter', Swal.stopTimer)
            toast.addEventListener('mouseleave', Swal.resumeTimer)
        }
    });

    let icon = 'info';
    let background = '#e0f2fe';
    let color = '#0369a1';

    if (type === 'success') {
        icon = 'success';
        background = '#e8f5f1';
        color = '#1e6f5c';
    } else if (type === 'error') {
        icon = 'error';
        background = '#fee2e2';
        color = '#dc2626';
    } else if (type === 'warning') {
        icon = 'warning';
        background = '#fef9e3';
        color = '#b45309';
    }

    Toast.fire({
        icon: icon,
        title: message,
        background: background,
        color: color
    });
};

/**
 * Insert formatting HTML tags at the cursor position in a textarea.
 * @param {jQuery} $textarea - Textarea element
 * @param {string} cmd - Command: bold, italic, underline, list, link, code
 */
window.insertWysiwygTag = function($textarea, cmd) {
    if (!$textarea.length) {
        return;
    }
    const el = $textarea[0];
    const start = el.selectionStart;
    const end = el.selectionEnd;
    const text = el.value;
    const selectedText = text.substring(start, end);
    let replacement = '';

    switch (cmd) {
        case 'bold':
            replacement = '<strong>' + selectedText + '</strong>';
            break;
        case 'italic':
            replacement = '<em>' + selectedText + '</em>';
            break;
        case 'underline':
            replacement = '<u>' + selectedText + '</u>';
            break;
        case 'list':
            replacement = '\n<ul>\n  <li>' + (selectedText || 'Élément') + '</li>\n</ul>\n';
            break;
        case 'link':
            const url = prompt("Entrez l'URL du lien :", "https://");
            if (url) {
                replacement = '<a href="' + url + '">' + (selectedText || 'Lien') + '</a>';
            } else {
                return;
            }
            break;
        case 'code':
            replacement = '<code>' + selectedText + '</code>';
            break;
    }

    el.value = text.substring(0, start) + replacement + text.substring(end);
    el.focus();
    el.selectionStart = start + replacement.length;
    el.selectionEnd = start + replacement.length;
    $textarea.trigger('input');
};
