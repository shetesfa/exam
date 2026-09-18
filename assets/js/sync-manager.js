/**
 * sync-manager.js
 * Runs the push/pull delta-sync cycle whenever connection is available.
 */
const SyncManager = {
    get apiBase() {
        if (window.EXAM_API_BASE) return window.EXAM_API_BASE;
        const path = window.location.pathname;
        if (path.includes('/exam-main/')) {
            return 'api/';
        }
        return '/exam/api/';
    },

    _syncing: false,
    listeners: [],

    onStatusChange(fn) { this.listeners.push(fn); },
    _emit(status, details) { this.listeners.forEach((fn) => fn(status, details)); },

    /** Real connectivity check — checks if local or remote server responds to ping */
    async isReallyOnline() {
        try {
            const controller = new AbortController();
            const timer = setTimeout(() => controller.abort(), 1500);
            const pingUrl = this.apiBase + 'ping.php?t=' + Date.now();
            const res = await fetch(pingUrl, { cache: 'no-store', method: 'GET', signal: controller.signal });
            clearTimeout(timer);
            return res.ok;
        } catch {
            return false;
        }
    },

    async getToken() {
        const auth = await OfflineDB.getAuth();
        return auth ? auth.token : null;
    },

    async pushChanges() {
        const token = await this.getToken();
        if (!token) return { success: false, message: 'Not logged in' };

        const dirtyAttendance = await OfflineDB.getDirtyAttendance();
        const dirtyMarks = await OfflineDB.getDirtyMarks();
        if (dirtyAttendance.length === 0 && dirtyMarks.length === 0) {
            return { success: true, pushed: 0, conflicts: 0 };
        }

        this._emit('syncing', { text: 'ያልተላኩ መረጃዎችን በመላክ ላይ... (Pushing changes...)', pending: dirtyAttendance.length + dirtyMarks.length });

        try {
            const pushUrl = this.apiBase + 'sync_push.php?token=' + encodeURIComponent(token);
            const res = await fetch(pushUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Authorization': 'Bearer ' + token },
                credentials: 'same-origin',
                body: JSON.stringify({ attendance: dirtyAttendance, marks: dirtyMarks, token: token }),
            });
            const data = await res.json();

            let pushed = 0, conflicts = 0;
            (data.results?.attendance || []).forEach((r) => {
                if (r.success) { OfflineDB.markAttendanceSynced(r.local_uuid); pushed++; }
                if (r.conflict) conflicts++;
            });
            (data.results?.marks || []).forEach((r) => {
                if (r.success) { OfflineDB.markMarksSynced(r.local_uuid); pushed++; }
                if (r.conflict) conflicts++;
            });

            return { success: true, pushed, conflicts };
        } catch (err) {
            return { success: false, error: err.message };
        }
    },

    async pullChanges() {
        const token = await this.getToken();
        if (!token) return { success: false, message: 'Not logged in' };

        try {
            // Check if local cache has classes/students. If empty, pull from 1970 to re-populate
            const existingClasses = await OfflineDB.getAllClasses();
            const existingStudents = await OfflineDB.getAllStudents();
            let since = await OfflineDB.getLastSync();
            if (existingClasses.length === 0 || existingStudents.length === 0) {
                since = '1970-01-01T00:00:00Z';
            }

            const pullUrl = this.apiBase + 'sync_pull.php?since=' + encodeURIComponent(since) + '&token=' + encodeURIComponent(token);
            const res = await fetch(pullUrl, {
                headers: { 'Authorization': 'Bearer ' + token },
                credentials: 'same-origin'
            });
            const data = await res.json();
            if (data.classes && data.classes.length) await OfflineDB.cacheClasses(data.classes);
            if (data.students && data.students.length) await OfflineDB.cacheStudents(data.students);
            if (data.attendance && data.attendance.length) await OfflineDB.cacheAttendance(data.attendance);
            if (data.marks && data.marks.length) await OfflineDB.cacheMarks(data.marks);
            if (data.active_semester && OfflineDB.setActiveSemester) await OfflineDB.setActiveSemester(data.active_semester);
            if (data.server_time) await OfflineDB.setLastSync(data.server_time);

            return {
                success: true,
                students: data.students?.length || 0,
                attendance: data.attendance?.length || 0,
                marks: data.marks?.length || 0
            };
        } catch (err) {
            return { success: false, error: err.message };
        }
    },

    /** Call this on page load, after saving offline records, or when 'online' event fires */
    async fullSync() {
        if (this._syncing) return;
        this._syncing = true;
        this._emit('syncing', { text: 'መረጃዎችን በማመሳሰል ላይ...' });
        try {
            const online = await this.isReallyOnline();
            if (!online) { 
                const pending = await OfflineDB.getPendingCounts();
                this._emit('offline', {
                    text: pending.total > 0 ? `Offline (${pending.total} ያልተላኩ)` : 'Offline',
                    pending: pending.total
                }); 
                return { success: false, offline: true }; 
            }

            const pushResult = await this.pushChanges();
            const pullResult = await this.pullChanges();

            const pending = await OfflineDB.getPendingCounts();

            if (pushResult.conflicts > 0) {
                this._emit('conflict', {
                    text: `⚠️ ${pushResult.conflicts} መረጃዎች ክለሳ ያስፈልጋቸዋል`,
                    conflicts: pushResult.conflicts,
                    pending: pending.total
                });
            } else {
                this._emit('synced', {
                    text: pending.total > 0 ? `ተመሳስሏል (${pending.total} ያልተላኩ)` : 'ሁሉም ተመሳስሏል',
                    pushed: pushResult.pushed || 0,
                    pending: pending.total
                });
            }
            return { success: true, pushResult, pullResult };
        } catch (err) {
            this._emit('error', { text: 'የማመሳሰል ስህተት ተከስቷል', error: err.message });
            return { success: false, message: err.message };
        } finally {
            this._syncing = false;
        }
    }
};

// Auto-sync on connectivity changes
window.addEventListener('online', () => {
    console.log('[SyncManager] Online detected, triggering full sync...');
    SyncManager.fullSync();
});

window.addEventListener('offline', () => {
    OfflineDB.getPendingCounts().then((p) => {
        SyncManager._emit('offline', {
            text: p.total > 0 ? `Offline (${p.total} ያልተላኩ)` : 'Offline',
            pending: p.total
        });
    });
});

document.addEventListener('DOMContentLoaded', () => {
    SyncManager.fullSync();
});
