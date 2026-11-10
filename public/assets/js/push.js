/**
 * Device notifications toggle (settings page).
 *
 * Registers the service worker, asks for permission only on a user click, and
 * stores/removes the subscription server-side. Push messages are payload-less:
 * sw.js fetches the text after waking up.
 */
(function () {
  var btn = document.getElementById('push-toggle');
  if (!btn) return;

  var status = document.getElementById('push-status');
  var endpoint = btn.dataset.endpoint || '/push_subscribe.php';
  var vapidKey = btn.dataset.vapid || '';

  function setStatus(message) {
    if (status) status.textContent = message;
  }

  function post(data) {
    var body = new FormData();
    body.append('csrf_token', btn.dataset.csrf || '');
    Object.keys(data).forEach(function (key) { body.append(key, data[key]); });
    return fetch(endpoint, {
      method: 'POST',
      body: body,
      credentials: 'same-origin'
    }).then(function (response) {
      return response.json().catch(function () { return { ok: false }; });
    });
  }

  function base64ToBytes(value) {
    var padded = (value + '='.repeat((4 - value.length % 4) % 4))
      .replace(/-/g, '+').replace(/_/g, '/');
    var raw = atob(padded);
    var bytes = new Uint8Array(raw.length);
    for (var i = 0; i < raw.length; i++) bytes[i] = raw.charCodeAt(i);
    return bytes;
  }

  function markOn() {
    btn.dataset.state = 'on';
    btn.textContent = btn.dataset.disable || 'Disable on this device';
    setStatus('Notifications are on for this device.');
  }

  function markOff() {
    btn.dataset.state = 'off';
    btn.textContent = btn.dataset.enable || 'Enable on this device';
  }

  if (!('serviceWorker' in navigator) || !('PushManager' in window) || !('Notification' in window) || !vapidKey) {
    btn.disabled = true;
    setStatus('This browser does not support notifications.');
    return;
  }

  navigator.serviceWorker.register('/sw.js').catch(function () {});
  navigator.serviceWorker.ready.then(function (registration) {
    return registration.pushManager.getSubscription().then(function (subscription) {
      if (subscription) markOn(); else markOff();
    });
  }).catch(function () {
    btn.disabled = true;
    setStatus('Notifications are not available right now.');
  });

  btn.addEventListener('click', function () {
    btn.disabled = true;
    navigator.serviceWorker.ready.then(function (registration) {
      if (btn.dataset.state === 'on') {
        return registration.pushManager.getSubscription().then(function (subscription) {
          if (!subscription) return null;
          return subscription.unsubscribe().then(function () {
            return post({ action: 'unsubscribe', endpoint: subscription.endpoint });
          });
        }).then(function () {
          markOff();
          setStatus('Notifications are off for this device.');
        });
      }

      if (Notification.permission === 'denied') {
        setStatus('Notifications are blocked in your browser settings.');
        return null;
      }

      return Notification.requestPermission().then(function (permission) {
        if (permission !== 'granted') {
          setStatus('Permission was not granted.');
          return null;
        }
        return registration.pushManager.subscribe({
          userVisibleOnly: true,
          applicationServerKey: base64ToBytes(vapidKey)
        }).then(function (subscription) {
          var json = subscription.toJSON();
          return post({
            action: 'subscribe',
            endpoint: json.endpoint,
            p256dh: json.keys.p256dh,
            auth: json.keys.auth
          }).then(function (result) {
            if (!result.ok) throw new Error('save_failed');
            markOn();
          });
        });
      });
    }).catch(function () {
      setStatus('Could not update notifications. Try again.');
    }).then(function () {
      btn.disabled = false;
    });
  });
})();
