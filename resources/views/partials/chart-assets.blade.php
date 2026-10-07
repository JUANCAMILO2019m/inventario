<script src="https://cdn.jsdelivr.net/npm/chart.js@4"></script>
<script>
    window.ChartUtil = (function () {
        const DAY = 86400000;
        const toDay = iso => {
            const parts = iso.split('-').map(Number);
            return Date.UTC(parts[0], parts[1] - 1, parts[2]) / DAY;
        };
        const fromDay = day => new Date(day * DAY);
        const grouped = (n, decimals) => (Number(n) || 0).toLocaleString('en-US', { maximumFractionDigits: decimals });

        return {
            toDay: toDay,
            dayLabel: day => fromDay(day).toLocaleDateString('es-CO', { day: '2-digit', month: 'short', timeZone: 'UTC' }),
            fullDate: day => fromDay(day).toLocaleDateString('es-CO', { day: '2-digit', month: '2-digit', year: 'numeric', timeZone: 'UTC' }),
            monthLabel: key => {
                const parts = key.split('-').map(Number);
                return new Date(Date.UTC(parts[0], parts[1] - 1, 1))
                    .toLocaleDateString('es-CO', { month: 'short', year: '2-digit', timeZone: 'UTC' });
            },
            num: n => grouped(n, 2),
            money: n => (n < 0 ? '−' : '') + '$' + grouped(Math.abs(n), 0),
            moneyShort: n => {
                const v = Math.abs(Number(n) || 0);
                let s;
                if (v >= 1e6) s = parseFloat((v / 1e6).toFixed(1)) + ' M';
                else if (v >= 1e3) s = Math.round(v / 1e3) + ' mil';
                else s = String(Math.round(v));
                return (n < 0 ? '−' : '') + '$' + s;
            },
        };
    })();

    Chart.defaults.font.family = 'ui-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto, Arial, sans-serif';
    Chart.defaults.color = '#4b5563';
</script>