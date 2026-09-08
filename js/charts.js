"use strict";
const Charts = {
    defaults: {
        font: { family: "Inter", size: 11 },
        grid: () => document.documentElement.getAttribute("data-theme") === "dark" ? "rgba(255,255,255,0.08)" : "rgba(0,0,0,0.06)",
        text: () => document.documentElement.getAttribute("data-theme") === "dark" ? "#94A3B8" : "#64748B",
        tooltip: () => ({
            backgroundColor: document.documentElement.getAttribute("data-theme") === "dark" ? "#1E293B" : "#FFFFFF",
            titleColor: document.documentElement.getAttribute("data-theme") === "dark" ? "#F1F5F9" : "#0F172A",
            bodyColor: document.documentElement.getAttribute("data-theme") === "dark" ? "#94A3B8" : "#475569",
            borderColor: document.documentElement.getAttribute("data-theme") === "dark" ? "rgba(255,255,255,0.12)" : "#E2E8F0",
            borderWidth: 1,
            padding: 10,
            cornerRadius: 8,
        }),
    },
    base(canvas, type, data, options = {}) {
        if (!canvas) return null;
        const d = this.defaults;
        const isMaintain = options.maintainAspectRatio !== undefined ? options.maintainAspectRatio : (options.aspectRatio !== false);
        const { aspectRatio, maintainAspectRatio, plugins, scales, ...restOptions } = options;

        const chartConfig = {
            type,
            data,
            options: {
                responsive: true,
                maintainAspectRatio: isMaintain,
                plugins: {
                    legend: {
                        labels: {
                            color: d.text(),
                            font: d.font,
                            usePointStyle: true,
                            padding: 12,
                        }
                    },
                    tooltip: d.tooltip(),
                    ...(plugins || {}),
                },
                scales: (type === "pie" || type === "doughnut" || type === "polarArea") ? undefined : {
                    x: {
                        grid: { color: d.grid() },
                        ticks: { color: d.text(), font: d.font }
                    },
                    y: {
                        grid: { color: d.grid() },
                        ticks: { color: d.text(), font: d.font },
                        beginAtZero: true,
                    },
                    ...(scales || {}),
                },
                ...restOptions,
            },
        };

        if (typeof aspectRatio === 'number') {
            chartConfig.options.aspectRatio = aspectRatio;
        }

        return new Chart(canvas, chartConfig);
    },
};
