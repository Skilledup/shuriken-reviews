/**
 * Shuriken Reviews Admin Charts
 *
 * Builds the Chart.js visualisations for the item-stats, context-stats and
 * voter-activity admin pages. Each page only emits a small inline data object
 * (the same pattern as the analytics dashboard); all chart-construction logic
 * lives here so the near-identical setup is defined once.
 *
 * Reads shared utilities (colors, formatDate) from window.shurikenAnalyticsUtils
 * exposed by admin-analytics.js.
 *
 * @package Shuriken_Reviews
 * @since 1.15.6
 */

(function ($) {
    'use strict';

    const u = window.shurikenAnalyticsUtils || {};
    const palette = u.colors || { grid: '#f0f0f1', tick: '#646970' };
    const gridColor = palette.grid;
    const tickColor = palette.tick;
    const formatDate = u.formatDate || function (dateStr) {
        const date = new Date(dateStr);
        return date.toLocaleDateString(undefined, { month: 'short', day: 'numeric' });
    };

    // Distribution palette (low → high)
    const DIST_COLORS = ['#dc3232', '#f56e28', '#ffb900', '#7ad03a', '#00a32a'];

    /**
     * Approval ring (doughnut) — like/dislike split.
     */
    const initApprovalRing = (canvasId, likes, dislikes, i18n) => {
        const ctx = document.getElementById(canvasId);
        if (!ctx || (likes + dislikes) <= 0) return;
        new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: [i18n.likes, i18n.dislikes],
                datasets: [{ data: [likes, dislikes], backgroundColor: ['#00a32a', '#dc3232'], borderColor: '#fff', borderWidth: 3, hoverOffset: 8 }]
            },
            options: {
                responsive: true, maintainAspectRatio: false, cutout: '60%',
                plugins: {
                    legend: { position: 'bottom', labels: { usePointStyle: true, padding: 15 } },
                    tooltip: {
                        backgroundColor: '#1d2327', titleColor: '#fff', bodyColor: '#fff', padding: 12,
                        callbacks: {
                            label: (item) => {
                                const total = likes + dislikes;
                                return `${item.label}: ${item.parsed} (${Math.round((item.parsed / total) * 100)}%)`;
                            }
                        }
                    }
                }
            }
        });
    };

    /**
     * Approval rate trend (line, 0–100%).
     */
    const initApprovalTrend = (canvasId, trend, i18n) => {
        const ctx = document.getElementById(canvasId);
        if (!ctx || !trend || !trend.length) return;
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: trend.map((r) => formatDate(r.vote_date)),
                datasets: [{ label: i18n.approvalRate, data: trend.map((r) => parseFloat(r.approval_rate)), borderColor: '#00a32a', backgroundColor: 'rgba(0, 163, 42, 0.1)', borderWidth: 2, fill: true, tension: 0.3, pointRadius: 3 }]
            },
            options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { x: { grid: { display: false }, ticks: { maxRotation: 45, autoSkip: true, maxTicksLimit: 12, color: tickColor } }, y: { beginAtZero: true, max: 100, grid: { color: gridColor }, ticks: { callback: (v) => v + '%', color: tickColor } } } }
        });
    };

    /**
     * Cumulative approvals (line).
     */
    const initCumulative = (canvasId, cumulative, i18n) => {
        const ctx = document.getElementById(canvasId);
        if (!ctx || !cumulative || !cumulative.length) return;
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: cumulative.map((r) => formatDate(r.vote_date)),
                datasets: [{ label: i18n.cumulative, data: cumulative.map((r) => parseInt(r.cumulative_count, 10)), borderColor: '#8c5383', backgroundColor: 'rgba(140, 83, 131, 0.1)', borderWidth: 2, fill: true, tension: 0.3, pointRadius: 2 }]
            },
            options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { x: { grid: { display: false }, ticks: { maxRotation: 45, autoSkip: true, maxTicksLimit: 12, color: tickColor } }, y: { beginAtZero: true, grid: { color: gridColor }, ticks: { precision: 0, color: tickColor } } } }
        });
    };

    /**
     * Rating distribution (bar) for stars/numeric.
     */
    const initDistribution = (canvasId, labels, data, i18n) => {
        const ctx = document.getElementById(canvasId);
        if (!ctx) return;
        const distData = data || [];
        const barColors = distData.length <= DIST_COLORS.length ? DIST_COLORS.slice(DIST_COLORS.length - distData.length) : DIST_COLORS;
        const distLabels = labels || distData.map((_, i) => `${i + 1} \u2605`);
        new Chart(ctx, {
            type: 'bar',
            data: { labels: distLabels, datasets: [{ label: i18n.votes, data: distData, backgroundColor: barColors, borderColor: barColors, borderWidth: 1, borderRadius: 4 }] },
            options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { x: { grid: { display: false }, ticks: { color: tickColor } }, y: { beginAtZero: true, grid: { color: gridColor }, ticks: { precision: 0, color: tickColor } } } }
        });
    };

    /**
     * Votes vs. rolling average (dual-axis bar + line) for stars/numeric.
     */
    const initDualAxis = (canvasId, dualAxisData, i18n) => {
        const ctx = document.getElementById(canvasId);
        if (!ctx || !dualAxisData || !dualAxisData.length) return;
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: dualAxisData.map((r) => formatDate(r.vote_date)),
                datasets: [
                    { type: 'bar', label: i18n.votes, data: dualAxisData.map((r) => parseInt(r.vote_count, 10)), backgroundColor: 'rgba(34, 113, 177, 0.3)', borderColor: '#2271b1', borderWidth: 1, borderRadius: 3, yAxisID: 'y' },
                    { type: 'line', label: i18n.average, data: dualAxisData.map((r) => parseFloat(r.display_daily_avg)), borderColor: '#f59e0b', backgroundColor: 'transparent', borderWidth: 2, tension: 0.3, pointRadius: 3, pointBackgroundColor: '#f59e0b', yAxisID: 'y1' }
                ]
            },
            options: {
                responsive: true, maintainAspectRatio: false, interaction: { intersect: false, mode: 'index' },
                plugins: { legend: { position: 'top', labels: { usePointStyle: true, padding: 12 } }, tooltip: { backgroundColor: '#1d2327', titleColor: '#fff', bodyColor: '#fff', padding: 12 } },
                scales: {
                    x: { grid: { display: false }, ticks: { maxRotation: 45, autoSkip: true, maxTicksLimit: 12, color: tickColor } },
                    y: { beginAtZero: true, position: 'left', grid: { color: gridColor }, ticks: { precision: 0, color: tickColor }, title: { display: true, text: i18n.votes, color: tickColor } },
                    y1: { beginAtZero: true, position: 'right', grid: { drawOnChartArea: false }, ticks: { color: '#f59e0b' }, title: { display: true, text: i18n.average, color: '#f59e0b' } }
                }
            }
        });
    };

    /**
     * Type-aware chart set shared by item-stats (global view) and context-stats.
     *
     * @param {Object} d   Data object with ratingType + i18n.
     * @param {Object} ids Canvas IDs: { ring, trend, cumulative, dist, dual }.
     */
    const initTypeAwareCharts = (d, ids) => {
        if (d.ratingType === 'like_dislike') {
            initApprovalRing(ids.ring, d.likes, d.dislikes, d.i18n);
            initApprovalTrend(ids.trend, d.approvalTrend, d.i18n);
        } else if (d.ratingType === 'approval') {
            initCumulative(ids.cumulative, d.cumulativeApprovals, d.i18n);
        } else {
            initDistribution(ids.dist, d.distributionLabels, d.ratingDistribution, d.i18n);
            initDualAxis(ids.dual, d.dualAxisData, d.i18n);
        }
    };

    /**
     * Top contexts by votes (horizontal bar).
     */
    const initTopContexts = (canvasId, topContexts, i18n) => {
        const ctx = document.getElementById(canvasId);
        if (!ctx || !topContexts || !topContexts.length) return;
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: topContexts.map((c) => {
                    const t = c.title || '#' + c.context_id;
                    return t.length > 30 ? t.substring(0, 27) + '...' : t;
                }),
                datasets: [{ label: i18n.votes, data: topContexts.map((c) => c.ctx_votes), backgroundColor: 'rgba(34, 113, 177, 0.6)', borderColor: '#2271b1', borderWidth: 1, borderRadius: 4 }]
            },
            options: {
                indexAxis: 'y', responsive: true, maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    x: { beginAtZero: true, grid: { color: gridColor }, ticks: { precision: 0, color: tickColor } },
                    y: { grid: { display: false }, ticks: { color: tickColor, font: { size: 11 } } }
                }
            }
        });
    };

    /**
     * Average rating distribution across posts (bar).
     */
    const initContextAvgDist = (canvasId, avgDistribution, i18n) => {
        const ctx = document.getElementById(canvasId);
        if (!ctx || !avgDistribution) return;
        const labels = Object.keys(avgDistribution);
        const data = Object.values(avgDistribution);
        const barColors = data.length <= DIST_COLORS.length
            ? DIST_COLORS.slice(DIST_COLORS.length - data.length)
            : data.map((_, i) => DIST_COLORS[i % DIST_COLORS.length]);
        new Chart(ctx, {
            type: 'bar',
            data: { labels: labels, datasets: [{ label: i18n.posts, data: data, backgroundColor: barColors, borderColor: barColors, borderWidth: 1, borderRadius: 4 }] },
            options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { x: { grid: { display: false }, ticks: { color: tickColor } }, y: { beginAtZero: true, grid: { color: gridColor }, ticks: { precision: 0, color: tickColor } } } }
        });
    };

    /**
     * Contextual voting activity over time (line).
     */
    const initContextActivity = (canvasId, votingActivity, i18n) => {
        const ctx = document.getElementById(canvasId);
        if (!ctx || !votingActivity || !votingActivity.length) return;
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: votingActivity.map((r) => formatDate(r.vote_date)),
                datasets: [{ label: i18n.votes, data: votingActivity.map((r) => parseInt(r.vote_count, 10)), borderColor: '#2271b1', backgroundColor: 'rgba(34, 113, 177, 0.1)', borderWidth: 2, fill: true, tension: 0.3, pointRadius: 3 }]
            },
            options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { x: { grid: { display: false }, ticks: { maxRotation: 45, autoSkip: true, maxTicksLimit: 12, color: tickColor } }, y: { beginAtZero: true, grid: { color: gridColor }, ticks: { precision: 0, color: tickColor } } } }
        });
    };

    /**
     * Context overview charts (item-stats contextual view).
     */
    const initContextOverviewCharts = (d) => {
        initTopContexts('ctxTopPostsChart', d.topContexts, d.i18n);
        initContextAvgDist('ctxAvgDistChart', d.avgDistribution, d.i18n);
        initContextActivity('ctxVotingActivityChart', d.votingActivity, d.i18n);
    };

    /**
     * Voter deviation-from-average distribution (bar).
     */
    const initVoterDistribution = (canvasId, labels, data, i18n) => {
        const ctx = document.getElementById(canvasId);
        if (!ctx) return;
        const deviationColors = [
            'rgba(239, 68, 68, 0.8)',
            'rgba(249, 115, 22, 0.8)',
            'rgba(148, 163, 184, 0.8)',
            'rgba(132, 204, 22, 0.8)',
            'rgba(34, 197, 94, 0.8)'
        ];
        new Chart(ctx.getContext('2d'), {
            type: 'bar',
            data: { labels: labels, datasets: [{ label: i18n.votes, data: data, backgroundColor: deviationColors, borderRadius: 6 }] },
            options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } } }
        });
    };

    /**
     * Voter activity over time (line).
     */
    const initVoterActivity = (canvasId, votesOverTime, i18n) => {
        const ctx = document.getElementById(canvasId);
        if (!ctx || !votesOverTime || !votesOverTime.length) return;
        new Chart(ctx.getContext('2d'), {
            type: 'line',
            data: {
                labels: votesOverTime.map((item) => item.vote_date),
                datasets: [{ label: i18n.votes, data: votesOverTime.map((item) => parseInt(item.vote_count, 10)), borderColor: 'rgba(102, 126, 234, 1)', backgroundColor: 'rgba(102, 126, 234, 0.1)', fill: true, tension: 0.3, pointRadius: 4, pointBackgroundColor: 'rgba(102, 126, 234, 1)' }]
            },
            options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } }, x: { ticks: { maxRotation: 45, minRotation: 0 } } } }
        });
    };

    /**
     * Voter activity page: sort control navigation.
     */
    const initVoterSort = () => {
        const $sort = $('#voter-sort');
        if (!$sort.length) return;
        $sort.on('change', function () {
            const parts = $(this).val().split('-');
            const url = new URL(window.location.href);
            url.searchParams.set('orderby', parts[0]);
            url.searchParams.set('order', parts[1]);
            url.searchParams.delete('paged');
            window.location.href = url.toString();
        });
    };

    $(document).ready(() => {
        initVoterSort();

        if (typeof Chart === 'undefined') return;

        const contextStatsData = window.shurikenContextStatsData;
        if (contextStatsData) {
            initTypeAwareCharts(contextStatsData, {
                ring: 'ctxApprovalRingChart',
                trend: 'ctxApprovalTrendChart',
                cumulative: 'ctxCumulativeChart',
                dist: 'ctxRatingDistChart',
                dual: 'ctxDualAxisChart'
            });
        }

        const itemStatsData = window.shurikenItemStatsData;
        if (itemStatsData) {
            initTypeAwareCharts(itemStatsData, {
                ring: 'itemApprovalRingChart',
                trend: 'itemApprovalTrendChart',
                cumulative: 'itemCumulativeChart',
                dist: 'itemRatingDistributionChart',
                dual: 'itemDualAxisChart'
            });
        }

        const contextData = window.shurikenContextData;
        if (contextData) {
            initContextOverviewCharts(contextData);
        }

        const voterActivityData = window.shurikenVoterActivityData;
        if (voterActivityData) {
            initVoterDistribution('voterRatingDistributionChart', voterActivityData.distributionLabels, voterActivityData.ratingDistribution, voterActivityData.i18n);
            initVoterActivity('voterActivityChart', voterActivityData.votesOverTime, voterActivityData.i18n);
        }
    });

})(jQuery);
