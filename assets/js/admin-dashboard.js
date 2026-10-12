/**
 * assets/js/admin-dashboard.js
 * ---------------------------------------------------------------
 * Admin dashboard chart initialization (Chart.js).
 *
 * Uses only data provided by PHP from actual database queries.
 * Two charts:
 *   - Monthly report trend (line)
 *   - Reports by status (doughnut)
 * ---------------------------------------------------------------
 */

(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        if (typeof window.Chart === 'undefined') return;
        var data = window.TRASHWISE_DASHBOARD;
        if (!data) return;

        var TW = {
            primary:   '#2C6705',
            secondary: '#6BA24B',
            pending:   '#d97706',
            progress:  '#2563eb',
            verified:  '#2C6705',
            resolved:  '#166534',
            rejected:  '#dc2626',
            border:    '#e0e5dc',
            textMuted: '#5a6b52'
        };

        // ---------- Trend (line) ----------
        var trendEl = document.getElementById('trendChart');
        if (trendEl) {
            var labels = data.trend.map(function (t) { return t.month; });
            var values = data.trend.map(function (t) { return t.total; });

            new Chart(trendEl.getContext('2d'), {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'Reports submitted',
                        data: values,
                        borderColor: TW.primary,
                        backgroundColor: 'rgba(107, 162, 75, 0.18)',
                        pointBackgroundColor: TW.secondary,
                        pointBorderColor: '#fff',
                        pointRadius: 5,
                        pointHoverRadius: 7,
                        borderWidth: 2,
                        tension: 0.32,
                        fill: true
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            backgroundColor: 'rgba(0, 0, 0, 0.85)',
                            titleColor: '#fff',
                            bodyColor: '#fff',
                            padding: 10
                        }
                    },
                    scales: {
                        x: {
                            grid: { display: false },
                            ticks: { color: TW.textMuted, font: { size: 11 } }
                        },
                        y: {
                            beginAtZero: true,
                            ticks: {
                                precision: 0,
                                color: TW.textMuted,
                                font: { size: 11 }
                            },
                            grid: { color: TW.border }
                        }
                    }
                }
            });
        }

        // ---------- Status (doughnut) ----------
        var statusEl = document.getElementById('statusChart');
        if (statusEl) {
            new Chart(statusEl.getContext('2d'), {
                type: 'doughnut',
                data: {
                    labels: ['Pending', 'In Progress', 'Verified', 'Resolved', 'Rejected'],
                    datasets: [{
                        data: [
                            data.status.pending,
                            data.status.in_progress,
                            data.status.verified,
                            data.status.resolved,
                            data.status.rejected
                        ],
                        backgroundColor: [
                            TW.pending, TW.progress, TW.secondary, TW.resolved, TW.rejected
                        ],
                        borderColor: '#fff',
                        borderWidth: 2,
                        hoverOffset: 6
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    cutout: '62%',
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                boxWidth: 12,
                                padding: 10,
                                color: TW.textMuted,
                                font: { size: 11 }
                            }
                        },
                        tooltip: {
                            backgroundColor: 'rgba(0, 0, 0, 0.85)',
                            padding: 10
                        }
                    }
                }
            });
        }
    });
})();