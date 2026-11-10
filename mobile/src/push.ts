import Constants from 'expo-constants';
import * as Device from 'expo-device';
import * as Notifications from 'expo-notifications';
import { Platform } from 'react-native';
import { api } from './api';
import { openFromLink } from './navigation';

// Show order updates while the app is open too.
Notifications.setNotificationHandler({
  handleNotification: async () => ({
    shouldShowBanner: true,
    shouldShowList: true,
    shouldPlaySound: true,
    shouldSetBadge: false,
  }),
});

let registeredToken: string | null = null;

function projectId(): string | undefined {
  const extra = Constants.expoConfig?.extra as { eas?: { projectId?: string } } | undefined;
  return extra?.eas?.projectId ?? (Constants as { easConfig?: { projectId?: string } }).easConfig?.projectId;
}

/**
 * Ask for permission and hand the Expo push token to the API.
 * Returns false when push is unavailable (web, simulator, no EAS project).
 */
export async function registerForPush(): Promise<boolean> {
  if (Platform.OS === 'web' || !Device.isDevice) return false;
  const id = projectId();
  // Expo push tokens need an EAS project id; without one (plain `expo start`)
  // the app still works, it just receives no remote notifications.
  if (!id) return false;

  try {
    let permission = (await Notifications.getPermissionsAsync()).status;
    if (permission !== 'granted') {
      permission = (await Notifications.requestPermissionsAsync()).status;
    }
    if (permission !== 'granted') return false;

    if (Platform.OS === 'android') {
      await Notifications.setNotificationChannelAsync('default', {
        name: 'AgriMALL',
        importance: Notifications.AndroidImportance.DEFAULT,
      });
    }

    const token = (await Notifications.getExpoPushTokenAsync({ projectId: id })).data;
    await api.registerPushToken(token, Platform.OS === 'ios' ? 'ios' : 'android', Device.deviceName ?? undefined);
    registeredToken = token;
    return true;
  } catch {
    return false;
  }
}

/** Drop this device's token on sign-out so the next account starts clean. */
export async function unregisterPush(): Promise<void> {
  const token = registeredToken;
  registeredToken = null;
  if (!token) return;
  await api.unregisterPushToken(token).catch(() => undefined);
}

/** Navigate when the user taps a notification. Returns the unsubscribe fn. */
export function onNotificationTap() {
  return Notifications.addNotificationResponseReceivedListener((response) => {
    const data = response.notification.request.content.data as { link?: string } | undefined;
    openFromLink(data?.link);
  });
}
