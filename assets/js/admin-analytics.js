/**
 * Shuriken Reviews Analytics Charts
 *
 * Handles Chart.js integration for the analytics dashboard.
 *
 * @package Shuriken_Reviews
 * @since 1.3.0
 */

(function ($) {
    'use strict';

    // Color palette
    var colors = {
        blue: '#2271b1',
        blueLight: 'rgba(34, 113, 177, 0.15)',
        green: '#00a32a',
        greenLight: 'rgba(0, 163, 42, 0.15)',
        orange: '#dba617',
        orangeLight: 'rgba(219, 166, 23, 0.15)',
        purple: '#8c5383',
        purpleLight: 'rgba(140, 83, 131, 0.15)',
        grid: '#f0f0f1',
        tick: '#646970',
        tooltipBg: '#1d2327'
    };

    // Type-to-color mapping
    var typeColors = {
        stars: { border: colors.blue, bg: colors.blueLight },
        like_dislike: { border: colors.green, bg: colors.greenLight },
        numeric: { border: colors.orange, bg: colors.orangeLight },
        approval: { border: colors.purple, bg: colors.purpleLight }
    };

    $(document).ready(function () {
        initClickableRows();

        if (typeof shurikenAnalyticsData === 'undefined') {
            console.warn('Shuriken Analytics: No data available');
            return;
        }

        Chart.defaults.font.family = '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif';
        Chart.defaults.font.size = 12;
        Chart.defaults.color = colors.tick;

        initStackedAreaChart();
        initHeatmap();
    });

    /**
     * Initialize Stacked Area Chart — votes over time split by rating type
     */
    function initStackedAreaChart() {
        var ctx = document.getElementById('votesOverTimeChart');
        if (!ctx) return;

        var rawData = shurikenAnalyticsData.votesOverTimeByType || [];
        if (!rawData.length) {
            showEmptyState(ctx, 'No voting activity yet');
            return;
        }

        // Group data: { date: { type: count } }
        var dateMap = {};
        var typesFound = {};
        rawData.forEach(function (row) {
            if (!dateMap[row.vote_date]) dateMap[row.vote_date] = {};
            dateMap[row.vote_date][row.rating_type] = parseInt(row.vote_count, 10);
            typesFound[row.rating_type] = true;
        });

        // Fill missing dates
        var allDates = Object.keys(dateMap).sort();
        var start = new Date(allDates[0]);
        var end = new Date(allDates[allDates.length - 1]);
        var filledDates = [];
        var cur = new Date(start);
        while (cur <= end) {
            var ds = cur.toISOString().split('T')[0];
            filledDates.push(ds);
            if (!dateMap[ds]) dateMap[ds] = {};
            cur.setDate(cur.getDate() + 1);
        }

        var i18n = shurikenAnalyticsData.i18n;
        var typeLabels = {
            stars: i18n.stars,
            like_dislike: i18n.like_dislike,
            numeric: i18n.numeric,
            approval: i18n.approval
        };

        var datasets = [];
        var typeOrder = ['stars', 'like_dislike', 'numeric', 'approval'];
        typeOrder.forEach(function (type) {
            if (!typesFound[type]) return;
            var tc = typeColors[type] || typeColors.stars;
            datasets.push({
                label: typeLabels[type] || type,
                data: filledDates.map(function (d) { return dateMap[d][type] || 0; }),
                borderColor: tc.border,
                backgroundColor: tc.bg,
                borderWidth: 2,
                fill: true,
                tension: 0.3,
                pointRadius: 2,
                pointHoverRadius: 5
            });
        });

        new Chart(ctx, {
            type: 'line',
            data: {
                labels: filledDates.map(formatDate),
                datasets: datasets
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: { intersect: false, mode: 'index' },
                plugins: {
                    legend: {
                        position: 'top',
                        labels: { usePointStyle: true, pointStyle: 'circle', padding: 15 }
                    },
                    tooltip: {
                        backgroundColor: colors.tooltipBg,
                        titleColor: '#fff',
                        bodyColor: '#fff',
                        padding: 12,
                        callbacks: {
                            label: function (context) {
                                return context.dataset.label + ': ' + context.parsed.y + ' ' + i18n.votes.toLowerCase();
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        stacked: true,
                        grid: { display: false },
                        ticks: { maxRotation: 45, autoSkip: true, maxTicksLimit: 12, color: colors.tick }
                    },
                    y: {
                        stacked: true,
                        beginAtZero: true,
                        grid: { color: colors.grid },
                        ticks: { precision: 0, color: colors.tick }
                    }
                }
            }
        });
    }

    /**
     * Initialize Voting Heatmap — CSS grid showing day-of-week × hour activity
     */
    function initHeatmap() {
        var container = document.getElementById('votingHeatmap');
        if (!container) return;

        var rawData = shurikenAnalyticsData.heatmap || [];
        if (!rawData.length) {
            container.innerHTML = '<div class="chart-empty-state"><svg class="shuriken-icon shuriken-icon-clock" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg><p>No voting data yet</p></div>';
            return;
        }

        // Build grid: MySQL DAYOFWEEK returns 1=Sun..7=Sat, hours 0-23
        var maxCount = 0;
        var grid = {};
        rawData.forEach(function (row) {
            var key = row.dow + '-' + row.hour;
            var c = parseInt(row.count, 10);
            grid[key] = c;
            if (c > maxCount) maxCount = c;
        });

        var i18n = shurikenAnalyticsData.i18n;
        var dayLabels = [i18n.sun, i18n.mon, i18n.tue, i18n.wed, i18n.thu, i18n.fri, i18n.sat];

        // Only show hours 0,3,6,9,12,15,18,21 for readability
        var hourSlots = [0, 3, 6, 9, 12, 15, 18, 21];

        var html = '<div class="heatmap-grid">';

        // Header row (hours)
        html += '<div class="heatmap-corner"></div>';
        hourSlots.forEach(function (h) {
            var label = h === 0 ? '12a' : h < 12 ? h + 'a' : h === 12 ? '12p' : (h - 12) + 'p';
            html += '<div class="heatmap-hour-label">' + label + '</div>';
        });

        // Data rows
        for (var day = 1; day <= 7; day++) {
            html += '<div class="heatmap-day-label">' + dayLabels[day - 1] + '</div>';
            hourSlots.forEach(function (h) {
                // Aggregate 3-hour blocks: sum the 3 hours starting at h
                var total = 0;
                for (var offset = 0; offset < 3; offset++) {
                    total += grid[day + '-' + (h + offset)] || 0;
                }
                var intensity = maxCount > 0 ? total / maxCount : 0;
                var opacity = intensity > 0 ? (0.15 + intensity * 0.85).toFixed(2) : 0;
                var bgColor = intensity > 0 ? 'rgba(34, 113, 177, ' + opacity + ')' : 'transparent';
                var title = dayLabels[day - 1] + ' ' + h + ':00-' + (h + 2) + ':59 — ' + total + ' ' + i18n.votes.toLowerCase();
                html += '<div class="heatmap-cell" style="background-color:' + bgColor + '" title="' + title + '"></div>';
            });
        }
        html += '</div>';

        container.innerHTML = html;
    }

    /**
     * Format date string for chart labels
     */
    function formatDate(dateStr) {
        var date = new Date(dateStr);
        return date.toLocaleDateString(undefined, { month: 'short', day: 'numeric' });
    }

    /**
     * Show empty state for a chart canvas
     */
    function showEmptyState(ctx, message) {
        var parent = ctx.parentElement;
        ctx.style.display = 'none';
        var div = document.createElement('div');
        div.className = 'chart-empty-state';
        div.innerHTML = '<svg class="shuriken-icon shuriken-icon-pie-chart" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M21.21 15.89A10 10 0 1 1 8 2.83"/><path d="M22 12A10 10 0 0 0 12 2v10z"/></svg><p>' + message + '</p>';
        div.style.cssText = 'display:flex;flex-direction:column;align-items:center;justify-content:center;height:100%;color:#646970;';
        parent.appendChild(div);
    }

    /**
     * Initialize clickable table rows
     */
    function initClickableRows() {
        $('.shuriken-clickable-row').on('click', function (e) {
            if ($(e.target).is('a') || $(e.target).closest('a').length) return;
            var href = $(this).data('href');
            if (href) window.location.href = href;
        });
    }

})(jQuery);
