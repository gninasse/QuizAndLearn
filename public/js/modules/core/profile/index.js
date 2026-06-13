$(document).ready(function () {
    // Preview de l'avatar au changement
    $('#profile_avatar').on('change', function (e) {
        const file = e.target.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onload = function (event) {
                $('#profile-avatar-preview').attr('src', event.target.result);

                // Upload automatique de l'avatar
                updateAvatar(file);
            };
            reader.readAsDataURL(file);
        }
    });

    function updateAvatar(file) {
        const formData = new FormData();
        formData.append('avatar', file);

        $.ajax({
            url: route('cores.profile.avatar'),
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function (response) {
                if (response.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Avatar mis à jour',
                        text: response.message,
                        timer: 2000,
                        showConfirmButton: false
                    });

                    // Mettre à jour les avatars dans le layout
                    $('.user-image, .user-header img').attr('src', response.avatar_url);
                }
            },
            error: function (xhr) {
                Swal.fire({
                    icon: 'error',
                    title: 'Erreur',
                    text: xhr.responseJSON.message || 'Erreur lors de la mise à jour de l\'avatar'
                });
                // Restaurer l'ancien aperçu? Difficile sans stocker l'URL initiale
            }
        });
    }

    // Toggle password visibility
    $('#passwordForm').on('submit', function (e) {
        e.preventDefault();

        const btn = $('#btn-update-password');
        const initialText = btn.html();
        btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Modification...');

        $.ajax({
            url: route('cores.profile.password'),
            type: 'POST',
            data: $(this).serialize(),
            success: function (response) {
                Swal.fire({
                    icon: 'success',
                    title: 'Mot de passe modifié',
                    text: response.message,
                    timer: 3000,
                    showConfirmButton: false
                }).then(() => {
                    window.location.href = response.redirect;
                });
            },
            error: function (xhr) {
                let message = 'Erreur lors de la modification';
                if (xhr.status === 422) {
                    const errors = xhr.responseJSON.errors;
                    message = Object.values(errors).flat().join('<br>');
                } else if (xhr.responseJSON && xhr.responseJSON.message) {
                    message = xhr.responseJSON.message;
                }

                Swal.fire({
                    icon: 'error',
                    title: 'Erreur',
                    html: message
                });
            },
            complete: function () {
                btn.prop('disabled', false).html(initialText);
            }
        });
    });

    // Toggle password visibility
    $('.toggle-password').on('click', function () {
        const targetSelector = $(this).data('target');
        const target = $(targetSelector);
        const icon = $(this).find('i');

        if (target.attr('type') === 'password') {
            target.attr('type', 'text');
            icon.removeClass('fa-eye').addClass('fa-eye-slash');
        } else {
            target.attr('type', 'password');
            icon.removeClass('fa-eye-slash').addClass('fa-eye');
        }
    });
});
