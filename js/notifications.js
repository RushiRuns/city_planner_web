"use strict";
const Notifications = {
    pollingInterval: 30000, timer: null, lastCount: 0,
    init() { this.poll(); this.timer = setInterval(() => this.poll(), this.pollingInterval); },
    async poll() {
        try {
            const data = await API.get("notifications_api.php", { action: "count" });
            if (data?.count !== undefined && data.count !== this.lastCount) {
                this.lastCount = data.count;
                const badge = document.querySelector(".header-notif-count");
                if (badge) badge.style.display = data.count > 0 ? "block" : "none";
                if (data.count > 0 && data.latest) Toast.warning(data.latest.title);
            }
        } catch(e) {}
    }
};
document.addEventListener("DOMContentLoaded", () => Notifications.init());
