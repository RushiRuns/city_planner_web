/* ============================================================
   CITY PLANNER — THEME & TELEMETRY MANAGER
   Dark / Light mode, Agency Lens Switcher & Tactical Audio
   ============================================================ */
'use strict';

const Theme = {
    STORAGE_KEY: 'cp_theme',
    AGENCY_STORAGE_KEY: 'cp_active_agency',

    init() {
        const saved = localStorage.getItem(this.STORAGE_KEY) || 'dark';
        this.apply(saved, false);
        document.getElementById('themeToggle')?.addEventListener('click', () => this.toggle());

        this.initAgencyLens();
        this.initAudioTelemetry();
        this.initSettingsThemeControls();
    },

    apply(theme, animate = true) {
        if (animate) {
            document.body.style.transition = 'background 0.3s ease, color 0.3s ease';
            setTimeout(() => document.body.style.transition = '', 400);
        }
        document.documentElement.setAttribute('data-theme', theme);
        localStorage.setItem(this.STORAGE_KEY, theme);

        const icon = document.getElementById('themeIcon');
        if (icon) {
            icon.className = theme === 'dark' ? 'bi bi-sun-fill' : 'bi bi-moon-fill';
        }

        this.updateSettingsUI(theme);
    },

    toggle() {
        const current = document.documentElement.getAttribute('data-theme');
        this.apply(current === 'dark' ? 'light' : 'dark');
    },

    isDark() {
        return document.documentElement.getAttribute('data-theme') === 'dark';
    },

    // ── Settings Page Theme Controls ──────────────────────────
    initSettingsThemeControls() {
        // Theme card selection
        document.querySelectorAll('[data-set-theme]').forEach(el => {
            el.addEventListener('click', (e) => {
                e.preventDefault();
                const targetTheme = el.getAttribute('data-set-theme');
                if (targetTheme) {
                    this.apply(targetTheme, true);
                    if (typeof Toast !== 'undefined') {
                        Toast.show(`Theme switched to ${targetTheme === 'dark' ? 'Dark Command Center' : 'Daylight Operation'} mode`, 'info', 2000);
                    }
                }
            });
        });

        // Settings toggle switch button if present
        const settingsToggleBtn = document.getElementById('settingsThemeToggleBtn');
        if (settingsToggleBtn) {
            settingsToggleBtn.addEventListener('click', () => {
                this.toggle();
                const nowTheme = document.documentElement.getAttribute('data-theme');
                if (typeof Toast !== 'undefined') {
                    Toast.show(`Theme switched to ${nowTheme === 'dark' ? 'Dark Command Center' : 'Daylight Operation'} mode`, 'info', 2000);
                }
            });
        }

        const current = localStorage.getItem(this.STORAGE_KEY) || 'dark';
        this.updateSettingsUI(current);
    },

    updateSettingsUI(theme) {
        document.querySelectorAll('[data-set-theme]').forEach(el => {
            const elTheme = el.getAttribute('data-set-theme');
            if (elTheme === theme) {
                el.classList.add('active');
            } else {
                el.classList.remove('active');
            }
        });

        const statusLabel = document.getElementById('settingsThemeCurrentLabel');
        if (statusLabel) {
            statusLabel.textContent = theme === 'dark' ? 'Dark Command Center (Active)' : 'Daylight Operation (Active)';
        }
    },

    // ── Agency Lens Management ────────────────────────────────
    initAgencyLens() {
        const lensBtn = document.getElementById('agencyLensBtn');
        const dropdown = document.getElementById('agencyLensDropdown');
        if (!lensBtn || !dropdown) return;

        lensBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            dropdown.classList.toggle('show');
        });

        document.addEventListener('click', (e) => {
            if (!dropdown.contains(e.target) && !lensBtn.contains(e.target)) {
                dropdown.classList.remove('show');
            }
        });
    },

    // ── Tactical Audio Telemetry (Web Audio API Synthesizer) ────
    audioContext: null,
    audioEnabled: false,

    initAudioTelemetry() {
        const btn = document.getElementById('audioTelemetryBtn');
        if (!btn) return;

        this.audioEnabled = localStorage.getItem('cp_audio_telemetry') === 'true';
        if (this.audioEnabled) {
            btn.classList.add('active');
        }

        btn.addEventListener('click', () => {
            this.audioEnabled = !this.audioEnabled;
            btn.classList.toggle('active', this.audioEnabled);
            localStorage.setItem('cp_audio_telemetry', this.audioEnabled);

            if (this.audioEnabled) {
                this.playTone(880, 'sine', 0.1, 0.05); // High confirmation beep
                Toast?.show('Tactical Audio Telemetry Enabled', 'info', 2000);
            } else {
                Toast?.show('Audio Telemetry Muted', 'neutral', 2000);
            }
        });
    },

    playTone(freq = 600, type = 'sine', duration = 0.15, gainVal = 0.08) {
        if (!this.audioEnabled) return;
        try {
            const AudioContext = window.AudioContext || window.webkitAudioContext;
            if (!this.audioContext) this.audioContext = new AudioContext();
            if (this.audioContext.state === 'suspended') this.audioContext.resume();

            const osc = this.audioContext.createOscillator();
            const gain = this.audioContext.createGain();

            osc.type = type;
            osc.frequency.setValueAtTime(freq, this.audioContext.currentTime);

            gain.gain.setValueAtTime(gainVal, this.audioContext.currentTime);
            gain.gain.exponentialRampToValueAtTime(0.0001, this.audioContext.currentTime + duration);

            osc.connect(gain);
            gain.connect(this.audioContext.destination);

            osc.start();
            osc.stop(this.audioContext.currentTime + duration);
        } catch (e) {
            console.debug('Web Audio not allowed without user interaction:', e);
        }
    },

    playEmergencySiren() {
        if (!this.audioEnabled) return;
        this.playTone(880, 'sawtooth', 0.2, 0.1);
        setTimeout(() => this.playTone(660, 'sawtooth', 0.2, 0.1), 220);
    },
};

// Initialize immediately
Theme.init();

