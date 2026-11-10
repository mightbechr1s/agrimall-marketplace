import * as SecureStore from 'expo-secure-store';
import { createContext, useContext, useEffect, useState, type ReactNode } from 'react';
import { Platform } from 'react-native';
import { loadApiBase, setToken, type User } from './api';
import { clearCache } from './cache';

const KEY = 'agrimall.session';

type Session = { token: string; user: User };

// SecureStore has no web implementation; the web build is only used for previews.
const store = {
  get: () =>
    Platform.OS === 'web'
      ? Promise.resolve(globalThis.localStorage?.getItem(KEY) ?? null)
      : SecureStore.getItemAsync(KEY),
  set: (value: string) =>
    Platform.OS === 'web'
      ? Promise.resolve(globalThis.localStorage?.setItem(KEY, value))
      : SecureStore.setItemAsync(KEY, value),
  clear: () =>
    Platform.OS === 'web'
      ? Promise.resolve(globalThis.localStorage?.removeItem(KEY))
      : SecureStore.deleteItemAsync(KEY),
};

type SessionContextValue = {
  session: Session | null;
  ready: boolean;
  signIn: (token: string, user: User) => Promise<void>;
  signOut: () => Promise<void>;
};

const SessionContext = createContext<SessionContextValue | null>(null);

export function SessionProvider({ children }: { children: ReactNode }) {
  const [session, setSession] = useState<Session | null>(null);
  const [ready, setReady] = useState(false);

  useEffect(() => {
    loadApiBase()
      .catch(() => undefined)
      .then(() => store.get())
      .then((raw) => {
        if (!raw) return;
        const parsed = JSON.parse(raw) as Session;
        setToken(parsed.token);
        setSession(parsed);
      })
      .catch(() => undefined)
      .finally(() => setReady(true));
  }, []);

  const signIn = async (token: string, user: User) => {
    setToken(token);
    await clearCache();
    await store.set(JSON.stringify({ token, user })).catch(() => undefined);
    setSession({ token, user });
  };

  const signOut = async () => {
    setToken(null);
    await clearCache();
    await store.clear().catch(() => undefined);
    setSession(null);
  };

  return (
    <SessionContext.Provider value={{ session, ready, signIn, signOut }}>
      {children}
    </SessionContext.Provider>
  );
}

export function useSession() {
  const value = useContext(SessionContext);
  if (!value) throw new Error('useSession must be used inside SessionProvider');
  return value;
}
