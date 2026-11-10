import { Ionicons } from '@expo/vector-icons';
import { useFocusEffect, useNavigation } from '@react-navigation/native';
import * as Notifications from 'expo-notifications';
import { useCallback, useEffect, useState } from 'react';
import { Alert, Pressable, ScrollView, StyleSheet, Text, View } from 'react-native';
import { api, type ProfileResponse } from '../api';
import type { RootNav } from '../navigation';
import { registerForPush } from '../push';
import { useSession } from '../session';
import { useTheme } from '../theme';
import { Button, ErrorNote } from '../ui';

export function ProfileScreen() {
  const t = useTheme();
  const navigation = useNavigation<RootNav>();
  const { session, signOut } = useSession();
  const [data, setData] = useState<ProfileResponse | null>(null);
  const [error, setError] = useState('');
  const [pushOn, setPushOn] = useState<boolean | null>(null);

  useEffect(() => {
    Notifications.getPermissionsAsync()
      .then((permission) => setPushOn(permission.status === 'granted'))
      .catch(() => setPushOn(false));
  }, []);

  const enablePush = async () => {
    const ok = await registerForPush();
    setPushOn(ok);
    if (!ok) {
      Alert.alert(
        'Notifications unavailable',
        'Allow notifications in your phone settings, and run the app from an EAS build to receive remote updates.',
      );
    }
  };

  const load = useCallback(() => {
    api
      .profile()
      .then((res) => {
        setData(res);
        setError('');
      })
      .catch((e) => setError(e instanceof Error ? e.message : 'Could not load your profile.'));
  }, []);

  useFocusEffect(useCallback(() => load(), [load]));

  const confirmSignOut = () =>
    Alert.alert('Sign out?', 'You will need your password to sign back in.', [
      { text: 'Cancel', style: 'cancel' },
      { text: 'Sign out', style: 'destructive', onPress: () => signOut() },
    ]);

  const name =
    data?.profile.full_name ?? data?.profile.farm_name ?? session?.user.email.split('@')[0] ?? '';
  const initials = name
    .split(' ')
    .map((part) => part[0])
    .slice(0, 2)
    .join('')
    .toUpperCase();

  return (
    <ScrollView style={{ backgroundColor: t.canvas }} contentContainerStyle={{ padding: 16 }}>
      <View style={styles.header}>
        <View style={[styles.avatar, { backgroundColor: t.primary }]}>
          <Text style={{ color: t.onPrimary, fontSize: 22, fontWeight: '700' }}>{initials}</Text>
        </View>
        <View style={{ flex: 1 }}>
          <Text style={{ color: t.text, fontSize: 18, fontWeight: '700' }}>{name}</Text>
          <Text style={{ color: t.muted }}>{session?.user.email}</Text>
          <View style={[styles.role, { borderColor: t.accent }]}>
            <Text style={{ color: t.accent, fontSize: 11, fontWeight: '700', textTransform: 'uppercase' }}>
              {session?.user.role}
            </Text>
          </View>
        </View>
      </View>

      {error ? <ErrorNote message={error} onRetry={load} /> : null}

      <View style={[styles.group, { borderColor: t.border }]}>
        <Item
          icon="heart-outline"
          label="Saved listings"
          onPress={() => navigation.navigate('Saved')}
        />
        <Item
          icon="notifications-outline"
          label="Notifications"
          value={data ? String(data.unread_notifications) : undefined}
          onPress={() =>
            Alert.alert(
              'Notifications',
              data && data.unread_notifications > 0
                ? `You have ${data.unread_notifications} unread notification(s).`
                : 'You are all caught up.',
            )
          }
        />
        <Item
          icon={pushOn ? 'phone-portrait-outline' : 'notifications-off-outline'}
          label="Push notifications"
          value={pushOn === null ? undefined : pushOn ? 'On' : 'Off'}
          onPress={() => {
            if (pushOn) {
              Alert.alert('Push notifications', 'Order updates arrive on this device.');
            } else {
              void enablePush();
            }
          }}
        />
        <Item
          icon="shield-checkmark-outline"
          label="Account type"
          value={session?.user.role}
          onPress={() =>
            Alert.alert(
              'Account type',
              session?.user.role === 'farmer'
                ? 'Farmer accounts manage listings and orders from the Dashboard and Orders tabs.'
                : session?.user.role === 'admin' || session?.user.role === 'manager'
                  ? 'Staff accounts review farmer applications and browse accounts.'
                  : 'Buyer accounts browse, order, and message farmers.',
            )
          }
        />
      </View>

      <Text style={{ color: t.muted, fontSize: 12, marginBottom: 16 }}>
        AgriMALL connects buyers and farmers directly. Payments are arranged between both parties —
        the app never handles your money.
      </Text>

      <Button label="Sign out" variant="danger" icon="log-out-outline" onPress={confirmSignOut} />
    </ScrollView>
  );
}

function Item({
  icon,
  label,
  value,
  onPress,
}: {
  icon: keyof typeof Ionicons.glyphMap;
  label: string;
  value?: string;
  onPress: () => void;
}) {
  const t = useTheme();
  return (
    <Pressable
      accessibilityRole="button"
      onPress={onPress}
      style={({ pressed }) => [styles.item, { opacity: pressed ? 0.7 : 1 }]}
    >
      <Ionicons name={icon} size={20} color={t.primary} />
      <Text style={{ color: t.text, flex: 1, marginLeft: 12 }}>{label}</Text>
      {value ? <Text style={{ color: t.muted, marginRight: 6 }}>{value}</Text> : null}
      <Ionicons name="chevron-forward" size={16} color={t.muted} />
    </Pressable>
  );
}

const styles = StyleSheet.create({
  header: { flexDirection: 'row', gap: 14, alignItems: 'center', marginBottom: 20 },
  avatar: { width: 56, height: 56, borderRadius: 28, alignItems: 'center', justifyContent: 'center' },
  role: { alignSelf: 'flex-start', borderWidth: 1, borderRadius: 999, paddingHorizontal: 8, marginTop: 6 },
  group: { borderWidth: 1, borderRadius: 14, marginBottom: 20, overflow: 'hidden' },
  item: { flexDirection: 'row', alignItems: 'center', padding: 14 },
});
