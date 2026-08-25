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
 * Ajoute un bouton « Insérer un audio » à côté de chaque bouton image des
 * barres wysiwyg (quiz, examens, flashcards) — sans modifier les vues.
 */
$(function() {
    $('.wysiwyg-toolbar .wysiwyg-btn[data-cmd="image"]').each(function() {
        const $img = $(this);
        if ($img.siblings('[data-cmd="audio"]').length) return;
        const $audio = $img.clone()
            .attr('data-cmd', 'audio')
            .attr('title', 'Insérer un audio')
            .html('<i class="bi bi-music-note-beamed"></i>');
        $img.after($audio);
    });
});

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
        case 'image':
            uploadAndInsertMedia('image/*', "l'image", function(res) {
                return `<img src="${res.url}" alt="${res.name || 'Image'}" class="img-fluid my-2" />`;
            });
            return;
        case 'audio':
            uploadAndInsertMedia('audio/*,.mp3,.wav,.ogg,.m4a,.flac', "l'audio", function(res) {
                return `<audio controls preload="metadata" src="${res.url}" class="my-2 w-100"></audio>`;
            });
            return;
    }

    // Téléverse un fichier vers /cores/media/upload puis insère le HTML rendu.
    function uploadAndInsertMedia(accept, label, buildHtml) {
        const input = document.createElement('input');
        input.type = 'file';
        input.accept = accept;
        input.onchange = function() {
            const file = this.files[0];
            if (!file) return;

            const formData = new FormData();
            formData.append('file', file);
            formData.append('_token', $('meta[name="csrf-token"]').attr('content') || $('input[name="_token"]').val() || '');

            Swal.fire({
                title: 'Téléversement...',
                text: 'Veuillez patienter pendant le chargement de ' + label + '.',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });

            $.ajax({
                url: '/cores/media/upload',
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(res) {
                    Swal.close();
                    if (res.success) {
                        const mediaHtml = buildHtml(res);
                        el.value = text.substring(0, start) + mediaHtml + text.substring(end);
                        el.focus();
                        el.selectionStart = start + mediaHtml.length;
                        el.selectionEnd = start + mediaHtml.length;
                        $textarea.trigger('input');
                    } else {
                        Swal.fire('Erreur', res.message || 'Échec du téléversement', 'error');
                    }
                },
                error: function(xhr) {
                    Swal.close();
                    Swal.fire('Erreur', xhr.responseJSON?.message || 'Une erreur est survenue lors du téléversement.', 'error');
                }
            });
        };
        input.click();
        return;
    }

    el.value = text.substring(0, start) + replacement + text.substring(end);
    el.focus();
    el.selectionStart = start + replacement.length;
    el.selectionEnd = start + replacement.length;
    $textarea.trigger('input');
};
