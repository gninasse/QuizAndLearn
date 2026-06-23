import axios from 'axios';
window.axios = axios;
window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

// Enregistrer le Service Worker pour le mode Hors Ligne
if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
        navigator.serviceWorker.register('/service-worker.js')
            .then(reg => console.log('Service Worker enregistré !', reg.scope))
            .catch(err => console.error('Erreur d\'enregistrement du Service Worker', err));
    });
}
