/* ============================================================
   CITY PLANNER — MAP.JS
   Leaflet.js integration for dashboard + live command
   ============================================================ */
'use strict';

const MapView = {
    map: null,
    incidentLayer: null,
    trackerLayer: null,
    stationLayer: null,
    stationsVisible: false,
    stationData: [],
    refreshTimer: null,

    init(containerId = 'dashMap', options = {}) {
        const container = document.getElementById(containerId);
        if (!container || this.map) return;

        const isDark = document.documentElement.getAttribute('data-theme') === 'dark';

        this.map = L.map(containerId, {
            center:         options.center   || [18.5204, 73.8567],
            zoom:           options.zoom     || 12,
            zoomControl:    true,
            attributionControl: false,
        });

        // Dark/Light tile layers
        const lightTiles = L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '© OpenStreetMap contributors',
        });
        const darkTiles  = L.tileLayer('https://{s}.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}{r}.png', {
            attribution: '© OpenStreetMap contributors, © CARTO',
        });

        if (isDark) {
            darkTiles.addTo(this.map);
        } else {
            lightTiles.addTo(this.map);
        }

        this.incidentLayer = L.layerGroup().addTo(this.map);
        this.trackerLayer  = L.layerGroup().addTo(this.map);
        this.stationLayer  = L.layerGroup();

        // Load initial markers
        this.loadMarkers();

        if (options.autoRefresh !== false) {
            this.refreshTimer = setInterval(() => this.loadMarkers(), 30000);
        }

        // Theme change listener
        const observer = new MutationObserver((mutations) => {
            mutations.forEach(m => {
                if (m.attributeName === 'data-theme') {
                    const dark = document.documentElement.getAttribute('data-theme') === 'dark';
                    this.map.eachLayer(l => { if (l instanceof L.TileLayer) this.map.removeLayer(l); });
                    (dark ? darkTiles : lightTiles).addTo(this.map);
                    // Re-render stations with updated theme colors
                    if (this.stationsVisible && this.stationData.length) {
                        this.renderStations(this.stationData);
                    }
                }
            });
        });
        observer.observe(document.documentElement, { attributes: true });
    },

    async loadMarkers() {
        try {
            const data = await API.get('dashboard_stats.php', { action: 'map_markers' });
            if (!data || !data.success) return;
            this.renderMarkers(data.markers || [], data.trackers || []);
            // Cache station data from map_markers response
            if (data.stations && data.stations.length) {
                this.stationData = data.stations;
                if (this.stationsVisible) {
                    this.renderStations(data.stations);
                }
            }
        } catch (e) {
            console.error('Map markers load failed:', e);
        }
    },

    renderMarkers(incidents, trackers) {
        this.incidentLayer.clearLayers();
        this.trackerLayer.clearLayers();

        const urgencyColors = {
            emergency: '#EF4444',
            high:      '#F59E0B',
            medium:    '#3B82F6',
            low:       '#22C55E',
        };

        incidents.forEach(inc => {
            if (!inc.lat || !inc.lng) return;

            const color   = urgencyColors[inc.urgency] || '#3B82F6';
            const isEmerg = inc.urgency === 'emergency';

            const icon = L.divIcon({
                className: '',
                iconSize:  [32, 32],
                iconAnchor:[16, 16],
                html: `
                <div style="
                    width:${isEmerg ? 32 : 26}px;
                    height:${isEmerg ? 32 : 26}px;
                    background:${color};
                    border:3px solid ${color}40;
                    border-radius:50%;
                    display:flex;align-items:center;justify-content:center;
                    box-shadow:0 0 ${isEmerg ? 16 : 8}px ${color}80;
                    ${isEmerg ? 'animation:pulse-map 1.5s infinite;' : ''}
                ">
                    <div style="width:8px;height:8px;background:#fff;border-radius:50%;"></div>
                </div>`,
            });

            const marker = L.marker([inc.lat, inc.lng], { icon });
            marker.bindPopup(`
                <div style="font-family:Inter,sans-serif;min-width:220px;">
                    <div style="font-weight:800;font-size:13px;margin-bottom:6px;">${escHtml(inc.category)}</div>
                    <div style="font-size:11px;color:#64748B;margin-bottom:8px;">${inc.id}</div>
                    <div style="display:flex;gap:6px;margin-bottom:8px;">
                        <span style="background:${urgencyColors[inc.urgency]}20;color:${urgencyColors[inc.urgency]};padding:2px 8px;border-radius:999px;font-size:10px;font-weight:700;text-transform:uppercase;">${inc.urgency}</span>
                        <span style="background:#F1F5F9;color:#64748B;padding:2px 8px;border-radius:999px;font-size:10px;font-weight:600;">${inc.status}</span>
                    </div>
                    <div style="font-size:11px;color:#64748B;margin-bottom:8px;">${escHtml(inc.address)}</div>
                    <a href="${(typeof window !== 'undefined' && window.BASE_URL) || ''}request_detail.php?id=${encodeURIComponent(inc.id)}"
                       style="display:block;text-align:center;background:#3B82F6;color:#fff;padding:6px 12px;border-radius:8px;font-size:12px;font-weight:600;text-decoration:none;">
                       View Details
                    </a>
                </div>
            `, { maxWidth: 260 });

            this.incidentLayer.addLayer(marker);
        });

        // Responder/tracker markers
        trackers.forEach(t => {
            if (!t.lat || !t.lng) return;
            const color = t.stale ? '#94A3B8' : '#22C55E';
            const trackerIcon = L.divIcon({
                className: '',
                iconSize: [26, 26],
                iconAnchor: [13, 13],
                html: `<div style="
                    width:26px;height:26px;
                    background:${color};
                    border:2px solid #fff;
                    border-radius:6px;
                    display:flex;align-items:center;justify-content:center;
                    box-shadow:0 2px 8px ${color}60;
                    font-size:12px;color:#fff;font-weight:700;">📡</div>`,
            });
            L.marker([t.lat, t.lng], { icon: trackerIcon })
             .bindPopup(`<b>Live Responder</b><br>${t.id}<br>${t.stale ? '⚠️ Stale data' : '🟢 Live'}`)
             .addTo(this.trackerLayer);
        });

        // Add CSS animation for pulsing markers
        if (!document.getElementById('map-pulse-style')) {
            const style = document.createElement('style');
            style.id = 'map-pulse-style';
            style.textContent = `
                @keyframes pulse-map {
                    0%, 100% { transform: scale(1); box-shadow: 0 0 0 0 rgba(239,68,68,0.6); }
                    50% { transform: scale(1.15); box-shadow: 0 0 0 8px rgba(239,68,68,0); }
                }
            `;
            document.head.appendChild(style);
        }
    },

    /* ── Station Layer Methods ─────────────────────────────── */

    renderStations(stations) {
        this.stationLayer.clearLayers();
        if (!stations || !stations.length) return;

        const isDark = document.documentElement.getAttribute('data-theme') === 'dark';

        stations.forEach(stn => {
            // Skip stations with missing/invalid coordinates
            if (!stn.lat || !stn.lng || (stn.lat === 0 && stn.lng === 0)) return;

            const color = stn.color || '#6366F1';

            const stationIcon = L.divIcon({
                className: 'station-marker-wrapper',
                iconSize:  [36, 42],
                iconAnchor:[18, 42],
                popupAnchor: [0, -44],
                html: `
                <div class="station-map-marker" style="--stn-color:${color};">
                    <div class="station-map-marker-pin">
                        <i class="bi bi-building-fill"></i>
                    </div>
                    <div class="station-map-marker-shadow"></div>
                </div>`,
            });

            const marker = L.marker([stn.lat, stn.lng], { icon: stationIcon });

            const popupText = isDark ? '#F1F5F9' : '#0F172A';
            const popupMuted = isDark ? '#94A3B8' : '#64748B';
            const popupBorder = isDark ? '#1E293B' : '#E2E8F0';

            marker.bindPopup(`
                <div class="station-popup" style="font-family:Inter,sans-serif;min-width:250px;max-width:300px;">
                    <div style="display:flex;align-items:center;gap:10px;margin-bottom:10px;">
                        <div style="width:38px;height:38px;border-radius:10px;background:${color}18;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                            <i class="bi bi-building-fill" style="font-size:18px;color:${color};"></i>
                        </div>
                        <div style="min-width:0;">
                            <div style="font-weight:800;font-size:14px;color:${popupText};line-height:1.2;">${escHtml(stn.name)}</div>
                            <span style="display:inline-block;background:${color}18;color:${color};padding:2px 8px;border-radius:999px;font-size:10px;font-weight:700;margin-top:3px;">${escHtml(stn.service_label)}</span>
                        </div>
                    </div>
                    <div style="display:flex;flex-direction:column;gap:6px;margin-bottom:12px;padding:10px;background:${isDark ? '#1E293B' : '#F8FAFC'};border-radius:8px;border:1px solid ${popupBorder};">
                        ${stn.address ? `<div style="display:flex;align-items:flex-start;gap:8px;">
                            <i class="bi bi-geo-alt-fill" style="color:${popupMuted};font-size:13px;margin-top:1px;flex-shrink:0;"></i>
                            <span style="font-size:12px;color:${popupText};line-height:1.3;">${escHtml(stn.address)}</span>
                        </div>` : ''}
                        ${stn.phone ? `<div style="display:flex;align-items:center;gap:8px;">
                            <i class="bi bi-telephone-fill" style="color:${popupMuted};font-size:12px;flex-shrink:0;"></i>
                            <a href="tel:${escHtml(stn.phone)}" style="font-size:12px;color:${color};font-weight:600;text-decoration:none;">${escHtml(stn.phone)}</a>
                        </div>` : ''}
                        ${stn.email ? `<div style="display:flex;align-items:center;gap:8px;">
                            <i class="bi bi-envelope-fill" style="color:${popupMuted};font-size:12px;flex-shrink:0;"></i>
                            <a href="mailto:${escHtml(stn.email)}" style="font-size:12px;color:${color};font-weight:600;text-decoration:none;overflow:hidden;text-overflow:ellipsis;">${escHtml(stn.email)}</a>
                        </div>` : ''}
                        <div style="display:flex;align-items:center;gap:8px;">
                            <i class="bi bi-people-fill" style="color:${popupMuted};font-size:12px;flex-shrink:0;"></i>
                            <span style="font-size:12px;color:${popupText};font-weight:600;">${stn.staff_count} Staff Member${stn.staff_count !== 1 ? 's' : ''}</span>
                        </div>
                    </div>
                    <div style="display:flex;gap:8px;">
                        <a href="https://www.google.com/maps/dir/?api=1&destination=${stn.lat},${stn.lng}" target="_blank" rel="noopener"
                           style="flex:1;display:flex;align-items:center;justify-content:center;gap:6px;background:${color};color:#fff;padding:8px 12px;border-radius:8px;font-size:12px;font-weight:700;text-decoration:none;">
                            <i class="bi bi-cursor-fill" style="font-size:13px;"></i> Get Directions
                        </a>
                        <a href="${(typeof window !== 'undefined' && window.BASE_URL) || ''}employees.php?station=${encodeURIComponent(stn.station_id)}" 
                           style="display:flex;align-items:center;justify-content:center;gap:4px;background:${isDark ? '#1E293B' : '#F1F5F9'};color:${popupText};padding:8px 12px;border-radius:8px;font-size:12px;font-weight:600;text-decoration:none;border:1px solid ${popupBorder};">
                            <i class="bi bi-people-fill" style="font-size:12px;"></i> Staff
                        </a>
                    </div>
                </div>
            `, { maxWidth: 320, className: 'station-popup-container' });

            this.stationLayer.addLayer(marker);
        });
    },

    async loadStations(params = {}) {
        try {
            const data = await API.get('dashboard_stats.php', { action: 'station_markers', ...params });
            if (!data || !data.success) return [];
            this.stationData = data.stations || [];
            this.renderStations(this.stationData);
            return this.stationData;
        } catch (e) {
            console.error('Station markers load failed:', e);
            return [];
        }
    },

    toggleStations(visible) {
        if (!this.map) return;
        this.stationsVisible = typeof visible === 'boolean' ? visible : !this.stationsVisible;

        if (this.stationsVisible) {
            this.stationLayer.addTo(this.map);
            // If no station markers rendered yet, load them
            if (this.stationLayer.getLayers().length === 0) {
                if (this.stationData.length) {
                    this.renderStations(this.stationData);
                } else {
                    this.loadStations();
                }
            }
        } else {
            this.map.removeLayer(this.stationLayer);
        }
        return this.stationsVisible;
    },

    fitToStations() {
        if (!this.map) return;
        const layers = this.stationLayer.getLayers();
        if (layers.length === 0) return;

        const group = L.featureGroup(layers);
        this.map.fitBounds(group.getBounds().pad(0.15), { maxZoom: 14 });
    },

    destroy() {
        if (this.refreshTimer) clearInterval(this.refreshTimer);
        if (this.map) { this.map.remove(); this.map = null; }
    },
};

// Auto-init if dashMap exists
document.addEventListener('DOMContentLoaded', () => {
    if (document.getElementById('dashMap')) {
        MapView.init('dashMap');
    }
});

function escHtml(str) {
    if (!str) return '';
    return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

