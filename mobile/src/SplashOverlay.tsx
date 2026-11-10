import * as SplashScreen from 'expo-splash-screen';
import { useEffect, useRef } from 'react';
import {
  AccessibilityInfo,
  Animated,
  Easing,
  StyleSheet,
  Text,
  useColorScheme,
} from 'react-native';
import { useTheme } from './theme';

/**
 * Covers the app while the brand mark settles and the wordmark arrives.
 *
 * The native splash is held back (preventAutoHideAsync in App.tsx) until this
 * mounts, then hidden immediately, so the mark never blinks: both screens show
 * the same 180px image in the same place. Only the scale and the wordmark move.
 */

const MARK = 180;
const SETTLE = 460; // mark scale 1.06 -> 1
const WORD_IN = 280;
const HOLD = 160;
const FADE_OUT = 180;

export function SplashOverlay({ onDone }: { onDone: () => void }) {
  const t = useTheme();
  const dark = useColorScheme() === 'dark';
  const mark = useRef(new Animated.Value(1.06)).current;
  const word = useRef(new Animated.Value(0)).current;
  const screen = useRef(new Animated.Value(1)).current;

  useEffect(() => {
    let cancelled = false;
    let timer: ReturnType<typeof setTimeout> | undefined;
    const finish = () => {
      if (!cancelled) onDone();
    };

    void SplashScreen.hideAsync().catch(() => {});

    const run = (reduced: boolean) => {
      if (cancelled) return;
      if (reduced) {
        mark.setValue(1);
        word.setValue(1);
        timer = setTimeout(() => {
          Animated.timing(screen, {
            toValue: 0,
            duration: 150,
            useNativeDriver: true,
          }).start(finish);
        }, 450);
        return;
      }
      Animated.sequence([
        Animated.timing(mark, {
          toValue: 1,
          duration: SETTLE,
          easing: Easing.out(Easing.cubic),
          useNativeDriver: true,
        }),
        Animated.timing(word, {
          toValue: 1,
          duration: WORD_IN,
          easing: Easing.out(Easing.cubic),
          useNativeDriver: true,
        }),
        Animated.delay(HOLD),
        Animated.timing(screen, {
          toValue: 0,
          duration: FADE_OUT,
          easing: Easing.in(Easing.cubic),
          useNativeDriver: true,
        }),
      ]).start(({ finished }) => {
        if (finished) finish();
      });
    };

    AccessibilityInfo.isReduceMotionEnabled()
      .then(run)
      .catch(() => run(false));

    return () => {
      cancelled = true;
      if (timer) clearTimeout(timer);
    };
  }, [mark, word, screen, onDone]);

  return (
    <Animated.View
      pointerEvents="none"
      style={[StyleSheet.absoluteFill, styles.root, { backgroundColor: t.canvas, opacity: screen }]}
    >
      <Animated.Image
        source={dark ? require('../assets/splash-icon-dark.png') : require('../assets/splash-icon.png')}
        style={[
          styles.mark,
          {
            transform: [{ scale: mark }],
          },
        ]}
      />
      <Animated.View
        style={[
          styles.words,
          {
            opacity: word,
            transform: [
              { translateY: word.interpolate({ inputRange: [0, 1], outputRange: [10, 0] }) },
            ],
          },
        ]}
      >
        <Text style={[styles.wordmark, { color: t.text }]}>AgriMALL</Text>
        <Text style={[styles.tagline, { color: t.muted }]}>
          Farm produce, direct from Filipino farmers.
        </Text>
      </Animated.View>
    </Animated.View>
  );
}

const styles = StyleSheet.create({
  root: {
    alignItems: 'center',
    justifyContent: 'center',
  },
  // Pinned to the same centre the native splash uses, so the hand-off is still.
  mark: {
    position: 'absolute',
    width: MARK,
    height: MARK,
    top: '50%',
    marginTop: -MARK / 2,
  },
  words: {
    position: 'absolute',
    top: '50%',
    marginTop: MARK / 2 + 16,
    left: 0,
    right: 0,
    alignItems: 'center',
  },
  wordmark: {
    fontSize: 30,
    fontWeight: '700',
    letterSpacing: 0.4,
  },
  tagline: {
    marginTop: 6,
    fontSize: 14,
    textAlign: 'center',
    paddingHorizontal: 32,
  },
});
