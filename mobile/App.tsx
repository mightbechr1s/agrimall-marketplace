import { DarkTheme, DefaultTheme, NavigationContainer } from '@react-navigation/native';
import * as SplashScreen from 'expo-splash-screen';
import { StatusBar } from 'expo-status-bar';
import { useCallback, useEffect, useState } from 'react';
import { StyleSheet, useColorScheme, View } from 'react-native';
import { SafeAreaProvider } from 'react-native-safe-area-context';
import { AppNavigator, linking, navigationRef } from './src/navigation';
import { onNotificationTap, registerForPush, unregisterPush } from './src/push';
import { LoginScreen } from './src/screens/LoginScreen';
import { SessionProvider, useSession } from './src/session';
import { SplashOverlay } from './src/SplashOverlay';
import { useTheme } from './src/theme';
import { Loading } from './src/ui';

// Hold the native splash until SplashOverlay paints; it hides it on mount.
void SplashScreen.preventAutoHideAsync().catch(() => {});

export default function App() {
  const [splashDone, setSplashDone] = useState(false);
  const finishSplash = useCallback(() => setSplashDone(true), []);

  return (
    <SessionProvider>
      <SafeAreaProvider>
        <View style={styles.root}>
          <Root />
          {splashDone ? null : <SplashOverlay onDone={finishSplash} />}
        </View>
      </SafeAreaProvider>
    </SessionProvider>
  );
}

const styles = StyleSheet.create({ root: { flex: 1 } });

function Root() {
  const t = useTheme();
  const scheme = useColorScheme();
  const { session, ready } = useSession();

  useEffect(() => {
    const subscription = onNotificationTap();
    return () => subscription.remove();
  }, []);

  const userId = session?.user.id;
  useEffect(() => {
    if (userId) {
      void registerForPush();
    } else {
      void unregisterPush();
    }
  }, [userId]);

  if (!ready) {
    return <Loading />;
  }

  const base = scheme === 'dark' ? DarkTheme : DefaultTheme;
  const navTheme = {
    ...base,
    colors: {
      ...base.colors,
      primary: t.primary,
      background: t.canvas,
      card: t.canvas,
      text: t.text,
      border: t.border,
      notification: t.accent,
    },
  };

  return (
    <>
      <StatusBar style={scheme === 'dark' ? 'light' : 'dark'} />
      <NavigationContainer ref={navigationRef} theme={navTheme} linking={linking}>
        {session ? <AppNavigator role={session.user.role} /> : <LoginScreen />}
      </NavigationContainer>
    </>
  );
}
