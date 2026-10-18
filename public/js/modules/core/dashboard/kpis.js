/**
 * Tableau de bord des indicateurs : filtres + rechargement AJAX (jQuery),
 * graphiques ApexCharts. Les URLs viennent de la vue (data-*).
 */
(function ($) {
    'use strict';

    var root = document.getElementById('kpi-app');
    if (!root) return;

    var urls = { kpis: root.dataset.urlKpis, exp: root.dataset.urlExport, members: root.dataset.urlMembers };
    var weeklyChart = null;
    var completionChart = null;
    var requestSeq = 0;

    var DASH = '—';

    function pct(v) { return v === null || v === undefined ? DASH : v + ' %'; }
    function note(v) { return v === null || v === undefined ? DASH : v + '/20'; }
    function num(v) { return v === null || v === undefined ? DASH : String(v); }

    // ApexCharts injecte le titre de l'infobulle en innerHTML : tout texte
    // venant des données doit passer par escapeHtml (les axes SVG, eux, sont en texte brut).
    function escapeHtml(v) {
        return String(v === null || v === undefined ? '' : v)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
    }

    function shortDate(iso) {
        var m = /^(\d{4})-(\d{2})-(\d{2})/.exec(String(iso || ''));
        return m ? m[3] + '/' + m[2] : DASH;
    }

    function formatDate(iso) {
        if (!iso) return DASH;
        var d = new Date(String(iso).length === 10 ? iso + 'T00:00:00' : iso);
        return isNaN(d.getTime()) ? DASH : d.toLocaleDateString('fr-FR');
    }

    function params() {
        var p = { period: $('#kpi-period').val() };
        if (p.period === 'custom') {
            p.from = $('#kpi-from').val();
            p.to = $('#kpi-to').val();
        }
        var g = $('#kpi-group').val();
        if (g) p.group = g;
        return p;
    }

    function showError(msg) { $('#kpi-error').text(msg).removeClass('d-none'); }
    function clearError() { $('#kpi-error').addClass('d-none').text(''); }

    function errorMessage(xhr) {
        var json = xhr.responseJSON || {};
        if (xhr.status === 422) {
            var first = json.errors ? Object.values(json.errors)[0] : null;
            return (first && first[0]) || json.message || 'Les filtres sélectionnés sont invalides.';
        }
        if (xhr.status === 403) return 'Vous n\'avez pas accès à ce ' + window.T('group') + '.';
        if (xhr.status === 0) return 'Connexion impossible. Vérifiez votre réseau puis réessayez.';
        return 'Impossible de charger les indicateurs (erreur ' + xhr.status + '). Veuillez réessayer.';
    }

    function renderTrend(id, value, trend) {
        var cls = 'bi bi-dash fs-5';
        var sr = 'Tendance indisponible';
        if (typeof value === 'number' && typeof trend === 'number') {
            sr = 'Stable par rapport à la période précédente (' + trend + ')';
            if (value > trend) {
                cls = 'bi bi-arrow-up-right fs-5 text-success';
                sr = 'En hausse par rapport à la période précédente (' + trend + ')';
            } else if (value < trend) {
                cls = 'bi bi-arrow-down-right fs-5 text-danger';
                sr = 'En baisse par rapport à la période précédente (' + trend + ')';
            }
        }
        $('#' + id).attr('class', cls)
            .attr('title', typeof trend === 'number' ? 'Période précédente : ' + trend : '');
        $('#' + id + '-sr').text(sr);
    }

    function renderWeekly(points) {
        var el = document.getElementById('kpi-weekly-chart');
        var options = {
            chart: { type: 'line', height: 280, toolbar: { show: false }, animations: { enabled: false } },
            series: [{ name: 'Actifs', data: points.map(function (p) { return p.active; }) }],
            xaxis: { categories: points.map(function (p) { return shortDate(p.week_start); }) },
            yaxis: { min: 0, forceNiceScale: true, labels: { formatter: function (v) { return Math.round(v); } } },
            stroke: { curve: 'smooth', width: 3 },
            markers: { size: 4 },
            grid: { padding: { left: 16, right: 16 } },
            tooltip: {
                x: {
                    formatter: function (v, o) {
                        var p = o && points[o.dataPointIndex];
                        return escapeHtml(p ? 'Semaine du ' + formatDate(p.week_start) : v);
                    }
                }
            },
            noData: { text: 'Aucune donnée' }
        };
        if (weeklyChart) weeklyChart.destroy();
        weeklyChart = new ApexCharts(el, options);
        weeklyChart.render();
    }

    function renderCompletion(rows) {
        var el = document.getElementById('kpi-completion-chart');
        if (completionChart) { completionChart.destroy(); completionChart = null; }
        if (!rows.length) {
            $(el).empty().append($('<p>').addClass('text-muted mb-0').text('Aucun contenu assigné.'));
            return;
        }
        $(el).empty();
        completionChart = new ApexCharts(el, {
            chart: { type: 'bar', height: Math.max(200, rows.length * 32 + 60), toolbar: { show: false }, animations: { enabled: false } },
            plotOptions: {
                bar: { horizontal: true, barHeight: '60%', dataLabels: { position: 'top', hideOverflowingLabels: false } }
            },
            series: [{ name: 'Complétion', data: rows.map(function (r) { return r.rate; }) }],
            xaxis: {
                categories: rows.map(function (r) { return r.title; }),
                min: 0, max: 100,
                labels: { formatter: function (v) { return Math.round(v) + ' %'; } }
            },
            // étiquette à droite de la barre, en sombre : lisible même à 0 %
            dataLabels: {
                enabled: true, offsetX: 30,
                style: { colors: ['#212529'] },
                formatter: function (v) { return v + ' %'; }
            },
            grid: { padding: { right: 40 } },
            tooltip: {
                x: { formatter: function (v) { return escapeHtml(v); } },
                y: {
                    formatter: function (v, o) {
                        var r = rows[o.dataPointIndex];
                        return v + ' % (' + r.completed + '/' + r.assigned + ')';
                    }
                }
            }
        });
        completionChart.render();
    }

    function emptyRow($tbody, cols, text) {
        $tbody.append($('<tr>').append($('<td>').attr('colspan', cols).addClass('text-center text-muted py-3').text(text)));
    }

    function renderFailed(rows) {
        var $tb = $('#kpi-failed-table tbody').empty();
        if (!rows.length) return emptyRow($tb, 4, 'Aucune question à signaler.');
        rows.forEach(function (r) {
            $tb.append($('<tr>')
                .append($('<td>').text(r.label || DASH))
                .append($('<td>').text(r.quiz_title || DASH))
                .append($('<td>').addClass('text-end').text(num(r.answers)))
                .append($('<td>').addClass('text-end').text(pct(r.fail_rate))));
        });
    }

    // « le groupe », « la classe », « l'équipe » selon la terminologie configurée
    function groupWithArticle() {
        var g = window.T('group');
        if (/^[aeiouyhàâéèêëîïôûü]/i.test(g)) return 'l\'' + g;
        return (window.T('fem_e') === 'e' ? 'la ' : 'le ') + g;
    }

    function renderInactive(rows) {
        var $tb = $('#kpi-inactive-table tbody').empty();
        if (!rows.length) return emptyRow($tb, 4, 'Aucun apprenant à relancer.');
        rows.forEach(function (r) {
            var $link = $('<td>');
            if (r.group_id) {
                var label = 'Voir ' + groupWithArticle() + ' de ' + (r.name || 'cet apprenant');
                $link.append($('<a>').attr('href', urls.members.replace('__ID__', encodeURIComponent(r.group_id)))
                    .addClass('btn btn-outline-primary btn-sm').attr({ title: label, 'aria-label': label })
                    .append($('<i>').addClass('bi bi-people').attr('aria-hidden', 'true')));
            }
            $tb.append($('<tr>')
                .append($('<td>').text(r.name || DASH))
                .append($('<td>').text(r.matricule || DASH))
                .append($('<td>').text(r.last_activity ? formatDate(r.last_activity) : 'Jamais'))
                .append($link));
        });
    }

    function render(d) {
        $('#kpi-active-7d').text(num(d.active_7d.value));
        renderTrend('kpi-active-7d-trend', d.active_7d.value, d.active_7d.trend);
        $('#kpi-active-30d').text(num(d.active_30d.value));
        renderTrend('kpi-active-30d-trend', d.active_30d.value, d.active_30d.trend);
        $('#kpi-activation').text(pct(d.activation_rate));
        $('#kpi-retention').text(pct(d.retention_30d));
        $('#kpi-review-regularity').text(pct(d.review_regularity));
        $('#kpi-quiz-score').text(pct(d.quiz_avg_score));
        $('#kpi-exam-note').text(note(d.exam_avg_note));

        renderWeekly(d.weekly_active || []);
        renderCompletion(d.completion || []);
        renderFailed(d.failed_questions || []);
        renderInactive(d.inactive || []);

        var inc = d.incidents || {};
        $('#kpi-inc-screenshots').text(num(inc.screenshots));
        $('#kpi-inc-captures').text(num(inc.exam_captures));
        $('#kpi-inc-navigation').text(num(inc.exam_navigation));
        $('#kpi-inc-cancelled').text(num(inc.exams_cancelled));

        if (d.showcase) {
            $('#kpi-sc-requests').text(num(d.showcase.requests));
            $('#kpi-sc-converted').text(num(d.showcase.converted));
            $('#kpi-sc-rate').text(pct(d.showcase.rate));
            $('#kpi-showcase-col').removeClass('d-none');
        } else {
            $('#kpi-showcase-col').addClass('d-none');
        }
    }

    var CUSTOM_MISSING = 'Renseignez les deux dates de la période personnalisée.';

    function customIncomplete(p) { return p.period === 'custom' && (!p.from || !p.to); }

    function load() {
        var p = params();
        if (customIncomplete(p)) {
            // invalide toute requête en cours : sa réponse ne doit plus s'afficher
            ++requestSeq;
            $('#kpi-loading').addClass('d-none');
            $('#kpi-apply').prop('disabled', false);
            showError(CUSTOM_MISSING);
            return;
        }
        var seq = ++requestSeq;
        clearError();
        $('#kpi-loading').removeClass('d-none');
        $('#kpi-apply').prop('disabled', true);

        $.ajax({ url: urls.kpis, data: p, dataType: 'json', headers: { Accept: 'application/json' } })
            .done(function (d) { if (seq === requestSeq) render(d); })
            .fail(function (xhr) { if (seq === requestSeq) showError(errorMessage(xhr)); })
            .always(function () {
                if (seq !== requestSeq) return;
                $('#kpi-loading').addClass('d-none');
                $('#kpi-apply').prop('disabled', false);
            });
    }

    $('#kpi-period').on('change', function () {
        $('.kpi-custom').toggleClass('d-none', $(this).val() !== 'custom');
        if ($(this).val() !== 'custom') load();
    });
    $('#kpi-group').on('change', load);
    $('#kpi-from, #kpi-to').on('change', function () {
        if ($('#kpi-from').val() && $('#kpi-to').val()) load();
    });
    $('#kpi-apply').on('click', load);

    $('.kpi-export').on('click', function () {
        var p = params();
        if (customIncomplete(p)) {
            showError(CUSTOM_MISSING);
            return;
        }
        var url = urls.exp.replace('__TABLE__', encodeURIComponent($(this).data('table')));
        window.location.href = url + '?' + $.param(p);
    });

    load();
})(jQuery);
