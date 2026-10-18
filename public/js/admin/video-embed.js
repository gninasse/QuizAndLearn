/**
 * Parseur partagé d'URL vidéo YouTube / Vimeo (éditeurs quiz, examen, flashcard, article).
 * Expose window.parseVideoEmbed et module.exports (tests vitest).
 * Retourne {provider, id[, hash]} ou null (autre domaine, forme inconnue).
 */
(function (root, factory) {
    var api = factory();
    if (typeof module === 'object' && module.exports) { module.exports = api; }
    if (root) { root.parseVideoEmbed = api.parseVideoEmbed; root.buildVideoEmbedHtml = api.buildVideoEmbedHtml; root.videoEmbedError = api.videoEmbedError; }
})(typeof window !== 'undefined' ? window : null, function () {
    var YT_ID = /^[A-Za-z0-9_-]{11}$/;
    var VIMEO_ID = /^\d+$/;
    var VIMEO_HASH = /^[a-f0-9]+$/;
    var YT_HOSTS = ['youtube.com', 'www.youtube.com', 'm.youtube.com', 'music.youtube.com', 'youtube-nocookie.com', 'www.youtube-nocookie.com'];

    function parseVideoEmbed(input) {
        if (typeof input !== 'string') { return null; }
        var raw = input.trim();
        if (!raw) { return null; }
        if (!/^https?:\/\//i.test(raw)) { raw = 'https://' + raw; }
        var url;
        try { url = new URL(raw); } catch (e) { return null; }
        if (url.protocol !== 'https:' && url.protocol !== 'http:') { return null; }

        var host = url.hostname.toLowerCase();
        var parts = url.pathname.split('/').filter(Boolean);

        if (host === 'youtu.be' || host === 'www.youtu.be') {
            return parts.length >= 1 && YT_ID.test(parts[0]) ? { provider: 'youtube', id: parts[0] } : null;
        }
        if (YT_HOSTS.indexOf(host) !== -1) {
            var id = null;
            if (parts[0] === 'watch') { id = url.searchParams.get('v'); }
            else if (parts.length >= 2 && (parts[0] === 'shorts' || parts[0] === 'embed' || parts[0] === 'live' || parts[0] === 'v')) { id = parts[1]; }
            return id && YT_ID.test(id) ? { provider: 'youtube', id: id } : null;
        }
        if (host === 'vimeo.com' || host === 'www.vimeo.com') {
            if (parts.length < 1 || !VIMEO_ID.test(parts[0])) { return null; }
            var res = { provider: 'vimeo', id: parts[0] };
            var h = parts[1] || url.searchParams.get('h');
            if (h && VIMEO_HASH.test(h)) { res.hash = h; }
            return res;
        }
        if (host === 'player.vimeo.com') {
            if (parts.length < 2 || parts[0] !== 'video' || !VIMEO_ID.test(parts[1])) { return null; }
            var out = { provider: 'vimeo', id: parts[1] };
            var hh = url.searchParams.get('h');
            if (hh && VIMEO_HASH.test(hh)) { out.hash = hh; }
            return out;
        }
        return null;
    }

    /** HTML d'embed de marque : seuls fournisseur, identifiant (et hash) sont stockés. */
    function buildVideoEmbedHtml(v) {
        var label = v.provider === 'youtube' ? 'YouTube' : 'Vimeo';
        return '<div class="brand-video" data-embed="' + v.provider + '" data-id="' + v.id + '"' +
            (v.hash ? ' data-hash="' + v.hash + '"' : '') +
            ' contenteditable="false"><span>Vidéo ' + label + '</span></div><p><br></p>';
    }

    /** Message d'erreur FR pour une saisie refusée, ou null si l'URL est acceptée. */
    function videoEmbedError(value, allowed) {
        var v = parseVideoEmbed(value);
        if (!v) { return 'Lien non reconnu : seuls les liens YouTube et Vimeo sont acceptés.'; }
        if (Array.isArray(allowed) && allowed.indexOf(v.provider) === -1) {
            return (v.provider === 'youtube' ? 'YouTube' : 'Vimeo') + ' n\'est pas activé dans les réglages vidéo.';
        }
        return null;
    }

    return { parseVideoEmbed: parseVideoEmbed, buildVideoEmbedHtml: buildVideoEmbedHtml, videoEmbedError: videoEmbedError };
});
