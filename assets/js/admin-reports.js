(function () {
  function readChartData() {
    const node = document.getElementById("mm-aggr-chart-data");
    if (node === null || node.textContent === "") {
      return [];
    }
    try {
      const parsed = JSON.parse(node.textContent);
      return Array.isArray(parsed) ? parsed : [];
    } catch {
      return [];
    }
  }

  function groupByCurrency(rows) {
    const groups = {};
    for (const row of rows) {
      const currency = String(row.currency ?? "");
      if (currency === "") {
        continue;
      }
      if (!groups[currency]) {
        groups[currency] = [];
      }
      groups[currency].push(row);
    }
    return groups;
  }

  function boot() {
    const canvas = document.getElementById("mm-aggr-volume-chart");
    if (canvas === null || typeof Chart === "undefined") {
      return;
    }

    const rows = readChartData();
    const groups = groupByCurrency(rows);
    const currencies = Object.keys(groups).sort();
    if (currencies.length === 0) {
      return;
    }

    const labels = [...new Set(rows.map((row) => String(row.date ?? "")))].sort();
    const datasets = currencies.map((currency, index) => {
      const byDate = new Map(
        groups[currency].map((row) => [String(row.date ?? ""), Number(row.total_amount ?? 0)]),
      );
      const palette = ["#ff3366", "#2563eb", "#16a34a", "#d97706", "#7c3aed"];
      const color = palette[index % palette.length];
      return {
        label: currency,
        data: labels.map((label) => byDate.get(label) ?? 0),
        borderColor: color,
        backgroundColor: color + "33",
        tension: 0.2,
      };
    });

    // eslint-disable-next-line no-undef
    new Chart(canvas, {
      type: "line",
      data: {
        labels,
        datasets,
      },
      options: {
        responsive: true,
        plugins: {
          legend: { position: "bottom" },
        },
        scales: {
          y: { beginAtZero: true },
        },
      },
    });
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", boot);
  } else {
    boot();
  }
})();
