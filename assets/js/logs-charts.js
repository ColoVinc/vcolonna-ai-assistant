(function () {
    if (typeof Chart === 'undefined' || typeof vcai_logs_data === 'undefined') return;

    var dailyData = vcai_logs_data.daily;
    var providerData = vcai_logs_data.provider;
    var i18n = vcai_logs_data.i18n;

    // Grafico giornaliero
    new Chart(document.getElementById('vcai-chart-daily'), {
        type: 'bar',
        data: {
            labels: dailyData.map(function(d) { return d.day; }),
            datasets: [
                {
                    label: i18n.calls,
                    data: dailyData.map(function(d) { return parseInt(d.calls); }),
                    backgroundColor: 'rgba(15, 52, 96, 0.7)',
                    borderRadius: 4,
                    yAxisID: 'y',
                },
                {
                    label: i18n.tokens,
                    data: dailyData.map(function(d) { return parseInt(d.prompt_tokens) + parseInt(d.completion_tokens); }),
                    type: 'line',
                    borderColor: '#533483',
                    backgroundColor: 'rgba(83, 52, 131, 0.1)',
                    fill: true,
                    tension: 0.3,
                    yAxisID: 'y1',
                }
            ]
        },
        options: {
            responsive: true,
            interaction: { mode: 'index', intersect: false },
            scales: {
                y: { position: 'left', beginAtZero: true, title: { display: true, text: i18n.calls } },
                y1: { position: 'right', beginAtZero: true, grid: { drawOnChartArea: false }, title: { display: true, text: i18n.tokens } },
            },
            plugins: { legend: { position: 'bottom' } }
        }
    });

    // Grafico provider
    var providerColors = { gemini: '#4285F4', openai: '#10a37f', claude: '#d97706', groq: '#f97316' };
    new Chart(document.getElementById('vcai-chart-provider'), {
        type: 'doughnut',
        data: {
            labels: providerData.map(function(d) { return d.provider.charAt(0).toUpperCase() + d.provider.slice(1); }),
            datasets: [{
                data: providerData.map(function(d) { return parseInt(d.calls); }),
                backgroundColor: providerData.map(function(d) { return providerColors[d.provider] || '#999'; }),
            }]
        },
        options: {
            responsive: true,
            plugins: { legend: { position: 'bottom' } }
        }
    });
})();
