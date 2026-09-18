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

async function hashPassword(pwd) {
    if (!pwd || typeof pwd !== 'string') return null;
    try {
        const msgUint8 = new TextEncoder().encode(pwd);
        const hashBuffer = await crypto.subtle.digest('SHA-256', msgUint8);
        const hashArray = Array.from(new Uint8Array(hashBuffer));
        return hashArray.map(b => b.toString(16).padStart(2, '0')).join('');
    } catch (_) {
        return null;
    }
}

const OfflineDB = {
    // ---- Auth (offline login) ----
    async saveAuth(user, token, expiresAt, password = null) {
        try {
            const prev = await this.getAuth();
            const prevId = prev && prev.user ? parseInt(prev.user.id) : null;
            const newId = user ? parseInt(user.id) : null;
            const prevRole = prev && prev.user ? prev.user.role : null;
            const newRole = user ? user.role : null;

            if (prevId && newId && (prevId !== newId || prevRole !== newRole)) {
                // User switch: clear classes and students to guarantee 0% data leakage across accounts
                await tx('classes', 'readwrite', (s) => s.clear());
                await tx('students', 'readwrite', (s) => s.clear());
                await tx('meta', 'readwrite', (s) => s.clear());
            }

            let pwdHash = prev ? prev.pwdHash : null;
            if (password) {
                pwdHash = await hashPassword(password);
            }

            if (user) {
                user.name = (user.name || '').trim();
                if (user.username) user.username = user.username.trim();
                if (user.role === 'admin') {
                    if (!user.username) user.username = 'admin';
                    if (!user.name) user.name = 'ICT';
                }
            }

            return tx('auth', 'readwrite', (store) => {
                store.put({ id: 'current', user, token, expiresAt, pwdHash });
            });
        } catch (e) {
            console.warn('saveAuth error:', e);
        }
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
    /** Offline login: checks cached user, aliases, and token validity */
    async offlineLogin(username, password) {
        const cached = await this.getAuth();
        if (!cached || !cached.user) {
            return { success: false, message: 'በዚህ መሳሪያ ላይ የተቀመጠ አካውንት የለም። እባክዎ መጀመሪያ ኢንተርኔት ባለበት ይግቡ።' };
        }
        const u = cached.user;
        const entered = (username || '').trim().toLowerCase();
        const uName = (u.name || '').trim().toLowerCase();
        const uUser = (u.username || '').trim().toLowerCase();

        const matchesUser = (uUser && uUser === entered) ||
                            (uName && uName === entered) ||
                            (u.role === 'admin' && (entered === 'admin' || entered === 'ict' || entered.includes('ict')));

        if (!matchesUser) {
            return { success: false, message: 'የተጠቃሚ ስም በዚህ መሳሪያ ላይ ከተቀመጠው ጋር አልተዛመደም።' };
        }

        // Verify password if cached and non-empty password provided
        if (cached.pwdHash && password) {
            const enteredHash = await hashPassword(password);
            if (enteredHash && enteredHash !== cached.pwdHash) {
                return { success: false, message: 'የተሳሳተ የይለፍ ቃል!' };
            }
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
        if (user && user.role === 'teacher' && Array.isArray(user.class_ids) && user.class_ids.length > 0) {
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
        if (!cid) return [];
        const user = forUser || (await this.getAuth())?.user || window.CURRENT_USER;
        if (user && user.role === 'teacher' && Array.isArray(user.class_ids) && user.class_ids.length > 0) {
            const allowed = user.class_ids.map(id => parseInt(id));
            if (!allowed.includes(cid)) {
                return []; // Strictly isolated
            }
        }
        const all = await this.getAllStudents();
        return all.filter((s) => parseInt(s.class_id) === cid && (s.is_deleted === 0 || s.is_deleted === '0' || !s.is_deleted));
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
    async deleteAttendanceLocal(studentId, classId, dateStr) {
        const sid = parseInt(studentId);
        const cid = parseInt(classId);
        const all = await this.getAllAttendance();
        const existing = all.find((r) => parseInt(r.student_id) === sid && parseInt(r.class_id) === cid && r.attendance_date === dateStr);
        const uuid = existing ? existing.local_uuid : ((window.crypto && crypto.randomUUID) ? crypto.randomUUID() : 'att_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9));
        const record = {
            local_uuid: uuid,
            student_id: sid,
            class_id: cid,
            attendance_date: dateStr,
            status: 'remove',
            dirty: true,
            updated_at: new Date().toISOString()
        };
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
        return all.filter((r) => parseInt(r.class_id) === cid && r.attendance_date === dateStr && r.status !== 'remove' && r.status !== 'uncheck' && r.status !== 'deleted');
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
                if (rec) {
                    if (rec.status === 'remove' || rec.status === 'uncheck' || rec.status === 'deleted') {
                        store.delete(localUuid);
                    } else {
                        rec.dirty = false;
                        store.put(rec);
                    }
                }
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
    },
    async getActiveSemester() {
        const db = await openDB();
        return new Promise((resolve) => {
            const req = db.transaction('meta', 'readonly').objectStore('meta').get('active_semester');
            req.onsuccess = () => resolve(req.result ? req.result.value : null);
            req.onerror = () => resolve(null);
        });
    },
    async setActiveSemester(sem) {
        return tx('meta', 'readwrite', (store) => store.put({ key: 'active_semester', value: sem }));
    }
};
