/**
 * push-notifications.js
 * Client-side Web Push notification manager for Atsede Tiguhan Sunday School.
 * Handles VAPID key exchange, ServiceWorker push subscription, and testing.
 */

function urlBase64ToUint8Array(base64String) {
    const padding = '='.repeat((4 - base64String.length % 4) % 4);
    const base64 = (base64String + padding)
        .replace(/\-/g, '+')
        .replace(/_/g, '/');

    const rawData = window.atob(base64);
    const outputArray = new Uint8Array(rawData.length);

    for (let i = 0; i < rawData.length; ++i) {
        outputArray[i] = rawData.charCodeAt(i);
    }
    return outputArray;
}

const PushNotifications = {
    _supported: false,
    _subscribed: false,
    _registration: null,

    get apiBase() {
        if (window.EXAM_API_BASE) return window.EXAM_API_BASE;
        const path = window.location.pathname;
        if (path.includes('/exam-main/')) return 'api/';
        return '/exam/api/';
    },

    async init() {
        if (!('serviceWorker' in navigator) || !('PushManager' in window) || !('Notification' in window)) {
            console.log('[Push] Push notifications not supported by this browser.');
            this.updateUI('unsupported');
            return;
        }

        this._supported = true;

        try {
            this._registration = await navigator.serviceWorker.ready;
            const subscription = await this._registration.pushManager.getSubscription();
            this._subscribed = !!subscription;

            if (this._subscribed) {
                this.updateUI('subscribed');
            } else if (Notification.permission === 'denied') {
                this.updateUI('denied');
            } else {
                this.updateUI('unsubscribed');
            }
        } catch (err) {
            console.warn('[Push] Error checking subscription:', err);
            this.updateUI('unsubscribed');
        }
    },

    async subscribe() {
        if (!this._supported) {
            alert('ይህ ብሮውዘር የቀጥታ የስልክ ማሳወቂያዎችን አይደግፍም።');
            return false;
        }

        try {
            const permission = await Notification.requestPermission();
            if (permission !== 'granted') {
                this.updateUI('denied');
                alert('የማሳወቂያ ፈቃድ አልተሰጠም። እባክዎ በብሮውዘር ቅንብሮች ውስጥ ይፍቀዱ።');
                return false;
            }

            // Fetch VAPID public key
            const keyRes = await fetch(this.apiBase + 'push_subscribe.php?action=vapid_public_key');
            const keyData = await keyRes.json();
            if (!keyData.success || !keyData.publicKey) {
                throw new Error(keyData.error || 'Failed to get VAPID key');
            }

            const convertedKey = urlBase64ToUint8Array(keyData.publicKey);
            const registration = await navigator.serviceWorker.ready;

            const subscription = await registration.pushManager.subscribe({
                userVisibleOnly: true,
                applicationServerKey: convertedKey
            });

            // Extract p256dh and auth keys
            const rawKey = subscription.getKey ? subscription.getKey('p256dh') : null;
            const p256dh = rawKey ? btoa(String.fromCharCode.apply(null, new Uint8Array(rawKey))) : '';

            const rawAuth = subscription.getKey ? subscription.getKey('auth') : null;
            const auth = rawAuth ? btoa(String.fromCharCode.apply(null, new Uint8Array(rawAuth))) : '';

            // Send subscription to server
            const saveRes = await fetch(this.apiBase + 'push_subscribe.php?action=subscribe', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    endpoint: subscription.endpoint,
                    p256dh: p256dh,
                    auth: auth,
                    device_name: navigator.userAgent
                })
            });

            const saveData = await saveRes.json();
            if (saveData.success) {
                this._subscribed = true;
                this.updateUI('subscribed');
                alert('✅ የቀጥታ የስልክ ማሳወቂያ በትክክል በርቷል!');
                return true;
            } else {
                throw new Error(saveData.error || 'Failed to register subscription');
            }
        } catch (err) {
            console.error('[Push] Subscription failed:', err);
            alert('⚠️ ማሳወቂያ ማብራት አልተሳካም: ' + err.message);
            return false;
        }
    },

    async unsubscribe() {
        try {
            const reg = await navigator.serviceWorker.ready;
            const subscription = await reg.pushManager.getSubscription();
            if (subscription) {
                await subscription.unsubscribe();
                await fetch(this.apiBase + 'push_subscribe.php?action=unsubscribe', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ endpoint: subscription.endpoint })
                });
            }
            this._subscribed = false;
            this.updateUI('unsubscribed');
            alert('ℹ️ የቀጥታ የስልክ ማሳወቂያ ጠፍቷል።');
            return true;
        } catch (err) {
            console.error('[Push] Unsubscribe failed:', err);
            return false;
        }
    },

    async toggle() {
        if (this._subscribed) {
            return this.unsubscribe();
        } else {
            return this.subscribe();
        }
    },

    async sendTestPush() {
        try {
            const res = await fetch(this.apiBase + 'push_subscribe.php?action=test_push', {
                method: 'POST'
            });
            const data = await res.json();
            if (data.success) {
                alert('📨 የሙከራ ማሳወቂያ ተልኳል! ጥቂት ሰኮንዶች ይጠብቁ። (Test push dispatched!)');
            } else {
                alert('⚠️ ስህተት: ' + (data.error || 'Test failed'));
            }
        } catch (err) {
            alert('⚠️ ስህተት: ' + err.message);
        }
    },

    async subscribeSilently() {
        if (!this._supported) return false;
        try {
            if (Notification.permission !== 'granted') return false;
            const keyRes = await fetch(this.apiBase + 'push_subscribe.php?action=vapid_public_key');
            const keyData = await keyRes.json();
            if (!keyData.success || !keyData.publicKey) return false;

            const convertedKey = urlBase64ToUint8Array(keyData.publicKey);
            const registration = await navigator.serviceWorker.ready;

            let subscription = await registration.pushManager.getSubscription();
            if (!subscription) {
                subscription = await registration.pushManager.subscribe({
                    userVisibleOnly: true,
                    applicationServerKey: convertedKey
                });
            }

            const rawKey = subscription.getKey ? subscription.getKey('p256dh') : null;
            const p256dh = rawKey ? btoa(String.fromCharCode.apply(null, new Uint8Array(rawKey))) : '';
            const rawAuth = subscription.getKey ? subscription.getKey('auth') : null;
            const auth = rawAuth ? btoa(String.fromCharCode.apply(null, new Uint8Array(rawAuth))) : '';

            const saveRes = await fetch(this.apiBase + 'push_subscribe.php?action=subscribe', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    endpoint: subscription.endpoint,
                    p256dh: p256dh,
                    auth: auth,
                    device_name: navigator.userAgent
                })
            });
            const saveData = await saveRes.json();
            if (saveData.success) {
                this._subscribed = true;
                this.updateUI('subscribed');
                return true;
            }
        } catch (e) {
            console.debug('[Push] Silent subscription check:', e);
        }
        return false;
    },

    autoRequestOnFirstInteraction() {
        if (!('Notification' in window) || !('serviceWorker' in navigator)) return;
        const trigger = async () => {
            document.removeEventListener('click', trigger);
            document.removeEventListener('touchstart', trigger);
            if (Notification.permission === 'default') {
                try {
                    const perm = await Notification.requestPermission();
                    if (perm === 'granted') {
                        await this.subscribeSilently();
                    }
                } catch (e) {
                    console.debug('[Push] Auto-request error:', e);
                }
            } else if (Notification.permission === 'granted' && !this._subscribed) {
                await this.subscribeSilently();
            }
        };
        document.addEventListener('click', trigger, { once: true });
        document.addEventListener('touchstart', trigger, { once: true });
    },

    updateUI(state) {
        const toggleBtn = document.getElementById('push-toggle-btn');
        const testBtn = document.getElementById('push-test-btn');
        const statusText = document.getElementById('push-status-text');

        if (!toggleBtn) return;

        if (state === 'subscribed') {
            toggleBtn.textContent = '🔕 ማሳወቂያ አጥፋ';
            toggleBtn.className = 'btn-mark active';
            toggleBtn.style.background = '#EF4444';
            toggleBtn.style.color = '#FFFFFF';
            toggleBtn.style.border = 'none';

            if (testBtn) testBtn.style.display = 'inline-block';
            if (statusText) statusText.innerHTML = '<span style="color:#10B981; font-weight:bold;">● የቀጥታ የስልክ ማሳወቂያ በርቷል</span>';
        } else if (state === 'denied') {
            toggleBtn.textContent = '🚫 ማሳወቂያ በብሮውዘር ተከልክሏል';
            toggleBtn.disabled = true;
            if (testBtn) testBtn.style.display = 'none';
            if (statusText) statusText.innerHTML = '<span style="color:#EF4444;">የብሮውዘር ማሳወቂያ ፈቃድ ተዘግቷል። እባክዎ በስልክዎ Settings ውስጥ ይፍቀዱ።</span>';
        } else if (state === 'unsupported') {
            toggleBtn.textContent = '❌ ማሳወቂያ አይደገፍም';
            toggleBtn.disabled = true;
            if (testBtn) testBtn.style.display = 'none';
            if (statusText) statusText.textContent = 'ይህ ብሮውዘር Web Push ማሳወቂያን አይደግፍም።';
        } else {
            // unsubscribed
            toggleBtn.textContent = '🔔 ማሳወቂያ አብራ';
            toggleBtn.className = 'btn-mark';
            toggleBtn.style.background = 'var(--gold-primary, #FFD700)';
            toggleBtn.style.color = 'var(--brown-dark, #8B4513)';
            toggleBtn.disabled = false;
            if (testBtn) testBtn.style.display = 'none';
            if (statusText) statusText.textContent = 'ፈተናዎችንና አስፈላጊ ማሳወቂያዎችን በቅጽበት በስልክዎ ላይ ያግኙ።';
        }
    }
};

document.addEventListener('DOMContentLoaded', () => {
    PushNotifications.init().then(() => {
        if (Notification.permission === 'granted' && !PushNotifications._subscribed) {
            PushNotifications.subscribeSilently();
        }
        PushNotifications.autoRequestOnFirstInteraction();
    });
});
