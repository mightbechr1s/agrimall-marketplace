import { Ionicons } from '@expo/vector-icons';
import { useFocusEffect, useNavigation } from '@react-navigation/native';
import { useCallback, useState } from 'react';
import {
  ActivityIndicator,
  Pressable,
  RefreshControl,
  ScrollView,
  StyleSheet,
  Text,
  View,
} from 'react-native';
import { api, type FarmerOverview } from '../api';
import type { RootNav } from '../navigation';
import { useSession } from '../session';
import { date, money, useTheme } from '../theme';
import { ErrorNote, OfflineBanner, StatusBadge } from '../ui';

export function FarmerDashboardScreen() {
  const t = useTheme();
  const navigation = useNavigation<RootNav>();
  const { session } = useSession();
  const [data, setData] = useState<FarmerOverview | null>(null);
  const [stale, setStale] = useState(false);
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [error, setError] = useState('');

  const load = useCallback(async () => {
    try {
      const res = await api.farmerOverview();
      setData(res);
      setStale(res.stale);
      setError('');
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Could not load your dashboard.');
    } finally {
      setLoading(false);
    }
  }, []);

  useFocusEffect(
    useCallback(() => {
      load();
    }, [load]),
  );

  const refresh = () => {
    setRefreshing(true);
    load().finally(() => setRefreshing(false));
  };

  if (loading) {
    return (
      <View style={[styles.fill, { backgroundColor: t.canvas }]}>
        <ActivityIndicator color={t.primary} />
      </View>
    );
  }

  const stats = data?.stats;

  return (
    <ScrollView
      style={{ backgroundColor: t.canvas }}
      contentContainerStyle={{ padding: 16, paddingBottom: 32 }}
      refreshControl={<RefreshControl refreshing={refreshing} onRefresh={refresh} tintColor={t.primary} />}
    >
      <OfflineBanner visible={stale} />

      <View style={{ marginBottom: 16 }}>
        <Text style={{ color: t.text, fontSize: 22, fontWeight: '700' }}>
          {data?.farm_name || session?.user.email}
        </Text>
        <View style={styles.verifyRow}>
          <Ionicons
            name={data?.verification_status === 'approved' ? 'shield-checkmark' : 'shield-outline'}
            size={15}
            color={data?.verification_status === 'approved' ? t.accent : t.gold}
          />
          <Text
            style={{
              color: data?.verification_status === 'approved' ? t.accent : t.gold,
              fontSize: 13,
              textTransform: 'capitalize',
            }}
          >
            {data?.verification_status === 'approved'
              ? 'Verified farm'
              : `Verification ${data?.verification_status ?? 'pending'}`}
          </Text>
        </View>
      </View>

      {error ? <ErrorNote message={error} onRetry={load} /> : null}

      <View style={styles.grid}>
        <Stat label="Needs action" value={String(stats?.pending_orders ?? 0)} icon="alert-circle-outline" tone={t.gold} />
        <Stat label="In progress" value={String(stats?.active_orders ?? 0)} icon="time-outline" tone={t.accent} />
        <Stat label="Active listings" value={String(stats?.active_listings ?? 0)} icon="pricetags-outline" tone={t.primary} />
        <Stat label="Sold out" value={String(stats?.sold_out_listings ?? 0)} icon="cube-outline" tone={t.muted} />
      </View>

      <View style={[styles.revenue, { borderColor: t.border, backgroundColor: t.surface }]}>
        <Text style={{ color: t.muted, fontSize: 13 }}>Completed sales this month</Text>
        <Text style={{ color: t.text, fontSize: 26, fontWeight: '800', marginTop: 4 }}>
          {money(stats?.revenue_this_month ?? 0)}
        </Text>
        <Text style={{ color: t.muted, fontSize: 12, marginTop: 4 }}>
          {stats?.completed_orders ?? 0} completed orders all time · paid directly by buyers
        </Text>
      </View>

      <Text style={{ color: t.text, fontSize: 17, fontWeight: '700', marginTop: 8, marginBottom: 10 }}>
        Recent orders
      </Text>

      {data && data.recent_orders.length === 0 ? (
        <Text style={{ color: t.muted }}>No orders yet. Buyers will show up here.</Text>
      ) : null}

      {data?.recent_orders.map((order) => (
        <Pressable
          key={order.id}
          accessibilityRole="button"
          onPress={() => navigation.navigate('OrderDetail', { id: order.id })}
          style={({ pressed }) => [
            styles.orderRow,
            { borderColor: t.border, opacity: pressed ? 0.85 : 1 },
          ]}
        >
          <View style={{ flex: 1 }}>
            <Text style={{ color: t.text, fontWeight: '600' }} numberOfLines={1}>
              {order.listing_name}
            </Text>
            <Text style={{ color: t.muted, fontSize: 12, marginTop: 2 }}>
              {order.quantity} {order.unit} · {date(order.created_at)}
            </Text>
            <Text style={{ color: t.text, fontWeight: '700', marginTop: 4 }}>{money(order.total)}</Text>
          </View>
          <StatusBadge status={order.status} />
        </Pressable>
      ))}

      <Pressable
        accessibilityRole="button"
        onPress={() => navigation.navigate('Tabs', { screen: 'Orders' })}
        style={styles.linkRow}
      >
        <Text style={{ color: t.primary, fontWeight: '600' }}>See all orders</Text>
        <Ionicons name="chevron-forward" size={16} color={t.primary} />
      </Pressable>
    </ScrollView>
  );
}

function Stat({
  label,
  value,
  icon,
  tone,
}: {
  label: string;
  value: string;
  icon: keyof typeof Ionicons.glyphMap;
  tone: string;
}) {
  const t = useTheme();
  return (
    <View style={[styles.stat, { borderColor: t.border }]}>
      <Ionicons name={icon} size={18} color={tone} />
      <Text style={{ color: t.text, fontSize: 22, fontWeight: '800', marginTop: 6 }}>{value}</Text>
      <Text style={{ color: t.muted, fontSize: 12 }}>{label}</Text>
    </View>
  );
}

const styles = StyleSheet.create({
  fill: { flex: 1, alignItems: 'center', justifyContent: 'center' },
  verifyRow: { flexDirection: 'row', alignItems: 'center', gap: 5, marginTop: 4 },
  grid: { flexDirection: 'row', flexWrap: 'wrap', gap: 10, marginBottom: 12 },
  stat: { width: '47.5%', flexGrow: 1, borderWidth: 1, borderRadius: 14, padding: 14 },
  revenue: { borderWidth: 1, borderRadius: 14, padding: 16, marginBottom: 16 },
  orderRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 10,
    borderWidth: 1,
    borderRadius: 14,
    padding: 14,
    marginBottom: 10,
  },
  linkRow: { flexDirection: 'row', alignItems: 'center', gap: 4, alignSelf: 'flex-end', marginTop: 4 },
});
