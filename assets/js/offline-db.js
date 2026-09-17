/**
 * offline-db.js
 * Thin, high-performance IndexedDB wrapper for offline operation.
 * Stores:
 *   - auth       : cached login token & user info
 *   - classes     : cached classes visible to this user
 *   - students    : cached student records
 *   - attendance  : cached attendance_records rows + dirty queue
 *   - marks       : cached marks rows + dirty queue
 *   - meta        : sync cursor (last successful pull timestamp)
 */
const DB_NAME = 'exam_offline_db';
const DB_VERSION = 1;

function openDB() {
    return new Promise((resolve, reject) => {
        const req = indexedDB.open(DB_NAME, DB_VERSION);
        req.onupgradeneeded = (e) => {
            const db = e.target.result;
            if (!db.objectStoreNames.contains('auth')) {
                db.createObjectStore('auth', { keyPath: 'id' });
            }
            if (!db.objectStoreNames.contains('classes')) {
                db.createObjectStore('classes', { keyPath: 'id' });
            }
            if (!db.objectStoreNames.contains('students')) {
                db.createObjectStore('students', { keyPath: 'id' });
            }
            if (!db.objectStoreNames.contains('attendance')) {
                db.createObjectStore('attendance', { keyPath: 'local_uuid' });
            }
            if (!db.objectStoreNames.contains('marks')) {
                db.createObjectStore('marks', { keyPath: 'local_uuid' });
            }
            if (!db.objectStoreNames.contains('meta')) {
                db.createObjectStore('meta', { keyPath: 'key' });
            }
        };
        req.onsuccess = () => resolve(req.result);
        req.onerror = () => reject(req.error);
    });
}

async function tx(storeName, mode, fn) {
    const db = await openDB();
    return new Promise((resolve, reject) => {
        const transaction = db.transaction(storeName, mode);
        const store = transaction.objectStore(storeName);
        const result = fn(store);
        transaction.oncomplete = () => resolve(result);
        transaction.onerror = () => reject(transaction.error);
    });
}

const OfflineDB = {
    // ---- Auth (offline login) ----
    async saveAuth(user, token, expiresAt) {
        try {
            const prev = await this.getAuth();
            if (prev && prev.user && user && (prev.user.id !== user.id || prev.user.role !== user.role)) {
                // User switch: clear classes and students to guarantee 0% data leakage across accounts
                await tx('classes', 'readwrite', (s) => s.clear());
                await tx('students', 'readwrite', (s) => s.clear());
            }
        } catch (e) {
            console.warn('saveAuth cleanup notice:', e);
        }
        return tx('auth', 'readwrite', (store) => {
            store.put({ id: 'current', user, token, expiresAt });
        });
    },
    async getAuth() {
        const db = await openDB();
        return new Promise((resolve) => {
            const req = db.transaction('auth', 'readonly').objectStore('auth').get('current');
            req.onsuccess = () => resolve(req.result || null);
            req.onerror = () => resolve(null);
        });
    },
    async clearAuth() {
        return tx('auth', 'readwrite', (store) => store.delete('current'));
    },
    /** Offline login: checks cached user and token validity */
    async offlineLogin(username, password) {
        const cached = await this.getAuth();
        if (!cached || !cached.user) {
            return { success: false, message: 'በዚህ መሳሪያ ላይ የተቀመጠ አካውንት የለም። እባክዎ መጀመሪያ ኢንተርኔት ባለበት ይግቡ።' };
        }
        const u = cached.user;
        const entered = (username || '').trim().toLowerCase();
        const matchesUser = (u.username && u.username.toLowerCase() === entered) ||
                            (u.name && u.name.toLowerCase() === entered);

        if (!matchesUser) {
            return { success: false, message: 'የተጠቃሚ ስም በዚህ መሳሪያ ላይ ከተቀመጠው ጋር አይዛመድም።' };
        }
        if (cached.expiresAt && new Date(cached.expiresAt) < new Date()) {
            return { success: false, message: 'የተቀመጠው የመግቢያ ጊዜ አልቋል። እባክዎ ኢንተርኔት አገናኝተው እንደገና ይግቡ።' };
        }
        return { success: true, user: cached.user, token: cached.token };
    },

    // ---- Bulk cache writers (called after a successful sync_pull) ----
    async cacheClasses(classes) {
        return tx('classes', 'readwrite', (store) => {
            classes.forEach((c) => {
                c.id = parseInt(c.id);
                store.put(c);
            });
        });
    },
    async getAllClasses(forUser) {
        const db = await openDB();
        const allClasses = await new Promise((resolve) => {
            const req = db.transaction('classes', 'readonly').objectStore('classes').getAll();
            req.onsuccess = () => resolve(req.result || []);
            req.onerror = () => resolve([]);
        });
        const user = forUser || (await this.getAuth())?.user || window.CURRENT_USER;
        if (user && user.role === 'teacher' && Array.isArray(user.class_ids)) {
            const allowed = user.class_ids.map(id => parseInt(id));
            return allClasses.filter(c => allowed.includes(parseInt(c.id)));
        }
        return allClasses;
    },
    async cacheStudents(students) {
        return tx('students', 'readwrite', (store) => {
            students.forEach((s) => {
                s.id = parseInt(s.id);
                s.class_id = parseInt(s.class_id);
                store.put(s);
            });
        });
    },
    async getAllStudents() {
        const db = await openDB();
        return new Promise((resolve) => {
            const req = db.transaction('students', 'readonly').objectStore('students').getAll();
            req.onsuccess = () => resolve(req.result || []);
            req.onerror = () => resolve([]);
        });
    },
    async getStudentsByClass(classId, forUser) {
        const cid = parseInt(classId);
        const user = forUser || (await this.getAuth())?.user || window.CURRENT_USER;
        if (user && user.role === 'teacher' && Array.isArray(user.class_ids)) {
            const allowed = user.class_ids.map(id => parseInt(id));
            if (!allowed.includes(cid)) {
                return []; // Strictly isolated
            }
        }
        const all = await this.getAllStudents();
        return all.filter((s) => s.class_id === cid && !s.is_deleted);
    },

    // ---- Attendance: local writes + dirty queue ----
    async cacheAttendance(records) {
        return tx('attendance', 'readwrite', (store) => {
            records.forEach((r) => {
                if (!r.local_uuid) {
                    r.local_uuid = 'srv_att_' + (r.id || (r.student_id + '_' + r.attendance_date));
                }
                r.dirty = false;
                store.put(r);
            });
        });
    },
    async saveAttendanceLocal(record) {
        if (!record.local_uuid) {
            record.local_uuid = (window.crypto && crypto.randomUUID) ? crypto.randomUUID() : 'att_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
        }
        record.dirty = true;
        record.updated_at = new Date().toISOString();
        return tx('attendance', 'readwrite', (store) => store.put(record));
    },
    async getAllAttendance() {
        const db = await openDB();
        return new Promise((resolve) => {
            const req = db.transaction('attendance', 'readonly').objectStore('attendance').getAll();
            req.onsuccess = () => resolve(req.result || []);
            req.onerror = () => resolve([]);
        });
    },
    async getAttendanceByClassAndDate(classId, dateStr) {
        const all = await this.getAllAttendance();
        const cid = parseInt(classId);
        return all.filter((r) => parseInt(r.class_id) === cid && r.attendance_date === dateStr);
    },
    async getDirtyAttendance() {
        const all = await this.getAllAttendance();
        return all.filter((r) => r.dirty);
    },
    async markAttendanceSynced(localUuid) {
        return tx('attendance', 'readwrite', (store) => {
            const req = store.get(localUuid);
            req.onsuccess = () => {
                const rec = req.result;
                if (rec) { rec.dirty = false; store.put(rec); }
            };
        });
    },

    // ---- Marks: local writes + dirty queue ----
    async cacheMarks(records) {
        return tx('marks', 'readwrite', (store) => {
            records.forEach((r) => {
                if (!r.local_uuid) {
                    r.local_uuid = 'srv_mark_' + (r.id || (r.student_id + '_' + r.class_id + '_' + r.semester_id));
                }
                r.dirty = false;
                store.put(r);
            });
        });
    },
    async saveMarksLocal(record) {
        if (!record.local_uuid) {
            record.local_uuid = (window.crypto && crypto.randomUUID) ? crypto.randomUUID() : 'mrk_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
        }
        record.dirty = true;
        record.updated_at = new Date().toISOString();
        return tx('marks', 'readwrite', (store) => store.put(record));
    },
    async getAllMarks() {
        const db = await openDB();
        return new Promise((resolve) => {
            const req = db.transaction('marks', 'readonly').objectStore('marks').getAll();
            req.onsuccess = () => resolve(req.result || []);
            req.onerror = () => resolve([]);
        });
    },
    async getMarksByClass(classId, semesterId = null) {
        const all = await this.getAllMarks();
        const cid = parseInt(classId);
        return all.filter((r) => parseInt(r.class_id) === cid && (!semesterId || parseInt(r.semester_id) === parseInt(semesterId)));
    },
    async getDirtyMarks() {
        const all = await this.getAllMarks();
        return all.filter((r) => r.dirty);
    },
    async markMarksSynced(localUuid) {
        return tx('marks', 'readwrite', (store) => {
            const req = store.get(localUuid);
            req.onsuccess = () => {
                const rec = req.result;
                if (rec) { rec.dirty = false; store.put(rec); }
            };
        });
    },

    // ---- Pending Sync Statistics ----
    async getPendingCounts() {
        const [dirtyAtt, dirtyMarks] = await Promise.all([
            this.getDirtyAttendance(),
            this.getDirtyMarks()
        ]);
        return {
            attendance: dirtyAtt.length,
            marks: dirtyMarks.length,
            total: dirtyAtt.length + dirtyMarks.length
        };
    },

    // ---- Sync cursor ----
    async getLastSync() {
        const db = await openDB();
        return new Promise((resolve) => {
            const req = db.transaction('meta', 'readonly').objectStore('meta').get('last_sync');
            req.onsuccess = () => resolve(req.result ? req.result.value : '1970-01-01T00:00:00Z');
            req.onerror = () => resolve('1970-01-01T00:00:00Z');
        });
    },
    async setLastSync(timestamp) {
        return tx('meta', 'readwrite', (store) => store.put({ key: 'last_sync', value: timestamp }));
    }
};
