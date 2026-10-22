/**
 * Maruf Traders - Dashboard Analytics & Real-Time KPI Visualizer
 */

let salesChart = null;

async function loadDashboardData(period = 'this_month') {
    try {
        const statsUrl = `${window.BASE_URL}/ajax/dashboard/get_stats.php?period=${period}`;
        const res = await apiRequest(statsUrl);

        if (res.success && res.data) {
            const d = res.data;

            // Update KPI Cards
            const setVal = (id, val) => {
                const el = document.getElementById(id);
                if (el) el.innerText = val;
            };

            setVal('kpi_today_sales', formatBDT(d.today_sales));
            setVal('kpi_today_collection', formatBDT(d.today_collection));
            setVal('kpi_current_stock', (d.current_stock || 0) + ' Bags');
            setVal('kpi_total_due', formatBDT(d.total_due));

            setVal('kpi_month_sales', formatBDT(d.month_sales));
            setVal('kpi_gross_profit', formatBDT(d.gross_profit));
            setVal('kpi_commission_income', formatBDT(d.commission_income));
            setVal('kpi_net_profit', formatBDT(d.net_profit));

            // Profit Overview Card
            setVal('po_gross_profit', formatBDT(d.gross_profit));
            setVal('po_commission', formatBDT(d.commission_income));
            setVal('po_expense', formatBDT(d.expense));
            setVal('po_net_profit', formatBDT(d.net_profit));

            // Target Achievement
            const achPercent = parseFloat(d.target_achievement) || 0;
            const targetQty = d.target_qty || 0;
            const actualQty = d.actual_sales_qty || 0;

            setVal('target_ach_percent', achPercent + '%');
            setVal('target_qty_display', targetQty.toLocaleString() + ' Bags');
            setVal('target_actual_display', actualQty.toLocaleString() + ' Bags');
            setVal('target_remaining_display', Math.max(0, targetQty - actualQty).toLocaleString() + ' Bags');

            // Estimated Commission Card
            setVal('comm_rate_display', '৳ ' + (d.commission_rate || '0.00') + ' / Bag');
            setVal('comm_ach_display', achPercent + '%');
            setVal('comm_qty_display', actualQty.toLocaleString() + ' Bags');
            setVal('comm_estimated_display', formatBDT(d.commission_income));

            // Render/Update Chart
            renderSalesChart(d.chart_labels || [], d.chart_sales || [], d.chart_collections || []);

            // Render Low Stock Alert if any
            renderLowStockAlert(d.low_stock_items || []);
        }
    } catch (err) {
        console.error('Failed to load dashboard data:', err);
    }
}

function renderSalesChart(labels, salesData, collectionData) {
    const canvas = document.getElementById('salesAnalyticsChart');
    if (!canvas) return;

    const ctx = canvas.getContext('2d');

    // Create Purple Gradient for Sales
    const purpleGradient = ctx.createLinearGradient(0, 0, 0, 350);
    purpleGradient.addColorStop(0, 'rgba(139, 92, 246, 0.45)');
    purpleGradient.addColorStop(1, 'rgba(139, 92, 246, 0.0)');

    // Create Cyan Gradient for Collections
    const cyanGradient = ctx.createLinearGradient(0, 0, 0, 350);
    cyanGradient.addColorStop(0, 'rgba(34, 211, 238, 0.4)');
    cyanGradient.addColorStop(1, 'rgba(34, 211, 238, 0.0)');

    if (salesChart) {
        salesChart.destroy();
    }

    salesChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: labels,
            datasets: [
                {
                    label: 'Sales (বিক্রয়)',
                    data: salesData,
                    borderColor: '#8B5CF6',
                    backgroundColor: purpleGradient,
                    fill: true,
                    tension: 0.4,
                    borderWidth: 3,
                    pointBackgroundColor: '#8B5CF6',
                    pointBorderColor: '#151F36',
                    pointHoverRadius: 6
                },
                {
                    label: 'Collections (আদায়)',
                    data: collectionData,
                    borderColor: '#22D3EE',
                    backgroundColor: cyanGradient,
                    fill: true,
                    tension: 0.4,
                    borderWidth: 3,
                    pointBackgroundColor: '#22D3EE',
                    pointBorderColor: '#151F36',
                    pointHoverRadius: 6
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'top',
                    labels: {
                        color: '#94A3B8',
                        font: { family: 'Poppins', size: 12 }
                    }
                },
                tooltip: {
                    backgroundColor: '#10182D',
                    titleColor: '#F8FAFC',
                    bodyColor: '#F8FAFC',
                    borderColor: 'rgba(148, 163, 184, 0.2)',
                    borderWidth: 1,
                    padding: 12,
                    callbacks: {
                        label: function (context) {
                            return context.dataset.label + ': ' + formatBDT(context.raw);
                        }
                    }
                }
            },
            scales: {
                x: {
                    grid: { color: 'rgba(148, 163, 184, 0.06)' },
                    ticks: { color: '#94A3B8', font: { family: 'Poppins', size: 11 } }
                },
                y: {
                    grid: { color: 'rgba(148, 163, 184, 0.06)' },
                    ticks: {
                        color: '#94A3B8',
                        font: { family: 'Poppins', size: 11 },
                        callback: function (val) {
                            return '৳' + val.toLocaleString();
                        }
                    }
                }
            }
        }
    });
}

function renderLowStockAlert(items) {
    const alertBox = document.getElementById('lowStockAlertWrapper');
    if (!alertBox) return;

    if (!items || items.length === 0) {
        alertBox.style.display = 'none';
        return;
    }

    alertBox.style.display = 'flex';
    const alertText = document.getElementById('lowStockAlertText');
    if (alertText) {
        const itemNames = items.map(i => `${i.name} (${i.current_stock} bags left)`).join(', ');
        alertText.innerHTML = `<strong>Attention Required:</strong> Low stock alert for: ${itemNames}`;
    }
}

document.addEventListener('DOMContentLoaded', () => {
    // Initial Load
    loadDashboardData('this_month');

    // Filter Change Handler
    const periodSelect = document.getElementById('dashboardPeriodFilter');
    if (periodSelect) {
        periodSelect.addEventListener('change', function () {
            loadDashboardData(this.value);
        });
    }

    const refreshBtn = document.getElementById('dashboardRefreshBtn');
    if (refreshBtn) {
        refreshBtn.addEventListener('click', () => {
            const period = periodSelect ? periodSelect.value : 'this_month';
            loadDashboardData(period);
            showToast('info', 'Dashboard refreshed');
        });
    }
});
