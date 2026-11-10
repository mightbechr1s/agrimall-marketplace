import AsyncStorage from '@react-native-async-storage/async-storage';

// Last successful response per request path, so the app still shows something
// when the phone loses signal. Cleared on sign-in and sign-out.
const PREFIX = 'agrimall.cache.';

export async function readCache<T>(key: string): Promise<T | null> {
  try {
    const raw = await AsyncStorage.getItem(PREFIX + key);
    return raw ? (JSON.parse(raw) as T) : null;
  } catch {
    return null;
  }
}

export async function writeCache(key: string, value: unknown): Promise<void> {
  try {
    await AsyncStorage.setItem(PREFIX + key, JSON.stringify(value));
  } catch {
    // A full disk or a private-mode browser is not worth failing a screen for.
  }
}

export async function clearCache(): Promise<void> {
  try {
    const keys = await AsyncStorage.getAllKeys();
    await AsyncStorage.multiRemove(keys.filter((key) => key.startsWith(PREFIX)));
  } catch {
    // ignore
  }
}
