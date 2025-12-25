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

    // Wait for DOM and Chart.js to be ready
    $(document).ready(function () {
        // Initialize clickable rows
        initClickableRows();

        if (typeof shurikenAnalyticsData === 'undefined') {
            console.warn('Shuriken Analytics: No data available');
            return;
        }

        // Detect dark mode
        const isDarkMode = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;

        // Chart.js default configuration
        Chart.defaults.font.family = '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif';
        Chart.defaults.font.size = 12;
        Chart.defaults.color = isDarkMode ? '#94a3b8' : '#646970';

        // Initialize all charts
        initVotesOverTimeChart();
        initRatingDistributionChart();
        initUserTypeChart();
    });

    /**
     * Initialize Votes Over Time Line Chart
     */
    function initVotesOverTimeChart() {
        const ctx = document.getElementById('votesOverTimeChart');
        if (!ctx) return;

        const data = shurikenAnalyticsData.votesOverTime || [];
        
        // Detect dark mode
        const isDarkMode = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
        const gridColor = isDarkMode ? '#334155' : '#f0f0f1';
        const tickColor = isDarkMode ? '#94a3b8' : '#646970';
        
        // Prepare data
        const labels = data.map(item => formatDate(item.vote_date));
        const values = data.map(item => parseInt(item.vote_count, 10));

        // Fill in missing dates with zeros for better visualization
        const filledData = fillMissingDates(data);

        new Chart(ctx, {
            type: 'line',
            data: {
                labels: filledData.labels,
                datasets: [{
                    label: shurikenAnalyticsData.i18n.votes,
                    data: filledData.values,
                    borderColor: '#2271b1',
                    backgroundColor: 'rgba(34, 113, 177, 0.1)',
                    borderWidth: 2,
                    fill: true,
                    tension: 0.3,
                    pointRadius: 3,
                    pointHoverRadius: 6,
                    pointBackgroundColor: '#2271b1',
                    pointBorderColor: isDarkMode ? '#1e293b' : '#fff',
                    pointBorderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: {
                    intersect: false,
                    mode: 'index'
                },
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        backgroundColor: '#1d2327',
                        titleColor: '#fff',
                        bodyColor: '#fff',
                        padding: 12,
                        displayColors: false,
                        callbacks: {
                            title: function(context) {
                                return context[0].label;
                            },
                            label: function(context) {
                                return context.parsed.y + ' ' + shurikenAnalyticsData.i18n.votes.toLowerCase();
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        grid: {
                            display: false
                        },
                        ticks: {
                            maxRotation: 45,
                            minRotation: 0,
                            autoSkip: true,
                            maxTicksLimit: 10,
                            color: tickColor
                        }
                    },
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: gridColor
                        },
                        ticks: {
                            precision: 0,
                            color: tickColor
                        }
                    }
                }
            }
        });
    }

    /**
     * Initialize Rating Distribution Bar Chart
     */
    function initRatingDistributionChart() {
        const ctx = document.getElementById('ratingDistributionChart');
        if (!ctx) return;

        const data = shurikenAnalyticsData.ratingDistribution || [0, 0, 0, 0, 0];

        // Detect dark mode
        const isDarkMode = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
        const gridColor = isDarkMode ? '#334155' : '#f0f0f1';
        const tickColor = isDarkMode ? '#94a3b8' : '#646970';

        // Colors from red (1 star) to green (5 stars)
        const colors = [
            '#dc3232',  // 1 star - red
            '#f56e28',  // 2 stars - orange
            '#ffb900',  // 3 stars - yellow
            '#7ad03a',  // 4 stars - light green
            '#00a32a'   // 5 stars - green
        ];

        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: ['1 ★', '2 ★', '3 ★', '4 ★', '5 ★'],
                datasets: [{
                    label: shurikenAnalyticsData.i18n.votes,
                    data: data,
                    backgroundColor: colors,
                    borderColor: colors,
                    borderWidth: 1,
                    borderRadius: 4,
                    barThickness: 30
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        backgroundColor: '#1d2327',
                        titleColor: '#fff',
                        bodyColor: '#fff',
                        padding: 12,
                        displayColors: false,
                        callbacks: {
                            label: function(context) {
                                const total = data.reduce((a, b) => a + b, 0);
                                const percentage = total > 0 ? Math.round((context.parsed.y / total) * 100) : 0;
                                return context.parsed.y + ' ' + shurikenAnalyticsData.i18n.votes.toLowerCase() + ' (' + percentage + '%)';
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        grid: {
                            display: false
                        },
                        ticks: {
                            color: tickColor
                        }
                    },
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: gridColor
                        },
                        ticks: {
                            precision: 0,
                            color: tickColor
                        }
                    }
                }
            }
        });
    }

    /**
     * Initialize User Type Doughnut Chart
     */
    function initUserTypeChart() {
        const ctx = document.getElementById('userTypeChart');
        if (!ctx) return;

        const data = shurikenAnalyticsData.userTypeData || { members: 0, guests: 0 };
        const total = data.members + data.guests;

        // Show empty state if no data
        if (total === 0) {
            showEmptyState(ctx, 'No voter data available yet');
            return;
        }

        // Detect dark mode
        const isDarkMode = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
        const textColor = isDarkMode ? '#f1f5f9' : '#1d2327';
        const labelColor = isDarkMode ? '#94a3b8' : '#646970';
        const borderColor = isDarkMode ? '#1e293b' : '#fff';
        const legendColor = isDarkMode ? '#e2e8f0' : '#646970';

        new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: [
                    shurikenAnalyticsData.i18n.members,
                    shurikenAnalyticsData.i18n.guests
                ],
                datasets: [{
                    data: [data.members, data.guests],
                    backgroundColor: [
                        '#2271b1',
                        '#72aee6'
                    ],
                    borderColor: borderColor,
                    borderWidth: 3,
                    hoverOffset: 8
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '60%',
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            padding: 20,
                            usePointStyle: true,
                            pointStyle: 'circle',
                            color: legendColor
                        }
                    },
                    tooltip: {
                        backgroundColor: '#1d2327',
                        titleColor: '#fff',
                        bodyColor: '#fff',
                        padding: 12,
                        callbacks: {
                            label: function(context) {
                                const percentage = Math.round((context.parsed / total) * 100);
                                return context.label + ': ' + context.parsed + ' (' + percentage + '%)';
                            }
                        }
                    }
                }
            },
            plugins: [{
                id: 'centerText',
                beforeDraw: function(chart) {
                    const width = chart.width;
                    const height = chart.height;
                    const ctx = chart.ctx;
                    
                    ctx.restore();
                    
                    // Draw total in center
                    const fontSize = (height / 114).toFixed(2);
                    ctx.font = 'bold ' + fontSize + 'em sans-serif';
                    ctx.textBaseline = 'middle';
                    ctx.fillStyle = textColor;
                    
                    const text = total.toString();
                    const textX = Math.round((width - ctx.measureText(text).width) / 2);
                    const textY = height / 2 - 10;
                    
                    ctx.fillText(text, textX, textY);
                    
                    // Draw "Total" label
                    ctx.font = '0.8em sans-serif';
                    ctx.fillStyle = labelColor;
                    const labelText = 'Total';
                    const labelX = Math.round((width - ctx.measureText(labelText).width) / 2);
                    ctx.fillText(labelText, labelX, textY + 20);
                    
                    ctx.save();
                }
            }]
        });
    }

    /**
     * Fill missing dates in the data array
     * 
     * @param {Array} data Original data array
     * @returns {Object} Object with labels and values arrays
     */
    function fillMissingDates(data) {
        if (!data || data.length === 0) {
            return { labels: [], values: [] };
        }

        // Create a map of existing dates
        const dateMap = {};
        data.forEach(item => {
            dateMap[item.vote_date] = parseInt(item.vote_count, 10);
        });

        // Get date range
        const dates = Object.keys(dateMap).sort();
        const startDate = new Date(dates[0]);
        const endDate = new Date(dates[dates.length - 1]);

        const labels = [];
        const values = [];

        // Fill in all dates
        const currentDate = new Date(startDate);
        while (currentDate <= endDate) {
            const dateStr = currentDate.toISOString().split('T')[0];
            labels.push(formatDate(dateStr));
            values.push(dateMap[dateStr] || 0);
            currentDate.setDate(currentDate.getDate() + 1);
        }

        return { labels, values };
    }

    /**
     * Format date for display
     * 
     * @param {string} dateStr Date string in YYYY-MM-DD format
     * @returns {string} Formatted date
     */
    function formatDate(dateStr) {
        const date = new Date(dateStr);
        const options = { month: 'short', day: 'numeric' };
        return date.toLocaleDateString(undefined, options);
    }

    /**
     * Show empty state for a chart
     * 
     * @param {HTMLElement} ctx Canvas element
     * @param {string} message Message to display
     */
    function showEmptyState(ctx, message) {
        const parent = ctx.parentElement;
        ctx.style.display = 'none';
        
        const emptyDiv = document.createElement('div');
        emptyDiv.className = 'chart-empty-state';
        emptyDiv.innerHTML = '<span class="dashicons dashicons-chart-pie"></span><p>' + message + '</p>';
        emptyDiv.style.cssText = 'display: flex; flex-direction: column; align-items: center; justify-content: center; height: 100%; color: #646970;';
        
        parent.appendChild(emptyDiv);
    }

    /**
     * Initialize clickable table rows
     * Clicking anywhere on the row navigates to the rating item
     */
    function initClickableRows() {
        $('.shuriken-clickable-row').on('click', function (e) {
            // Don't navigate if clicking on a link (let the link handle it)
            if ($(e.target).is('a') || $(e.target).closest('a').length) {
                return;
            }
            
            var href = $(this).data('href');
            if (href) {
                window.location.href = href;
            }
        });
    }

})(jQuery);
