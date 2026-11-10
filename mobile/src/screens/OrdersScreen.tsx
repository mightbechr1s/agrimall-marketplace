import { useFocusEffect, useNavigation } from '@react-navigation/native';
import { useCallback, useState } from 'react';
import {
  ActivityIndicator,
  FlatList,
  Pressable,
  RefreshControl,
  ScrollView,
  StyleSheet,
  Text,
  View,
} from 'react-native';
import { api, type OrderStatus, type OrderSummary } from '../api';
import type { RootNav } from '../navigation';
import { date, money, useTheme } from '../theme';
import { Chip, Empty, ErrorNote, OfflineBanner, StatusBadge } from '../ui';

const filters: { label: string; value: OrderStatus | '' }[] = [
  { label: 'All', value: '' },
  { label: 'Pending', value: 'pending' },
  { label: 'Confirmed', value: 'confirmed' },
  { label: 'Preparing', value: 'preparing' },
  { label: 'Ready', value: 'ready_for_pickup' },
  { label: 'Delivering', value: 'out_for_delivery' },
  { label: 'Completed', value: 'completed' },
  { label: 'Cancelled', value: 'cancelled' },
];

export function OrdersScreen() {
  const t = useTheme();
  const navigation = useNavigation<RootNav>();
  const [status, setStatus] = useState<OrderStatus | ''>('');
  const [orders, setOrders] = useState<OrderSummary[]>([]);
  const [page, setPage] = useState(1);
  const [totalPages, setTotalPages] = useState(1);
  const [loading, setLoading] = useState(true);
  const [loadingMore, setLoadingMore] = useState(false);
  const [refreshing, setRefreshing] = useState(false);
  const [stale, setStale] = useState(false);
  const [error, setError] = useState('');

  const load = useCallback(
    async (targetPage: number, append: boolean) => {
      try {
        const res = await api.orders({ status, page: targetPage });
        setOrders((prev) => (append ? [...prev, ...res.orders] : res.orders));
        setPage(res.page);
        setTotalPages(res.total_pages);
        setStale(res.stale);
        setError('');
      } catch (e) {
        setError(e instanceof Error ? e.message : 'Could not load orders.');
      } finally {
        setLoading(false);
      }
    },
    [status],
  );

  useFocusEffect(
    useCallback(() => {
      load(1, false);
    }, [load]),
  );

  const refresh = () => {
    setRefreshing(true);
    load(1, false).finally(() => setRefreshing(false));
  };

  const loadMore = () => {
    if (loadingMore || page >= totalPages) return;
    setLoadingMore(true);
    load(page + 1, true).finally(() => setLoadingMore(false));
  };

  return (
    <View style={{ flex: 1, backgroundColor: t.canvas }}>
      <ScrollView
        horizontal
        showsHorizontalScrollIndicator={false}
        contentContainerStyle={{ paddingHorizontal: 16, paddingVertical: 12 }}
      >
        {filters.map((filter) => (
          <Chip
            key={filter.label}
            label={filter.label}
            active={status === filter.value}
            onPress={() => {
              setLoading(true);
              setStatus(filter.value);
            }}
          />
        ))}
      </ScrollView>

      {loading ? (
        <View style={styles.fill}>
          <ActivityIndicator color={t.primary} />
        </View>
      ) : (
        <FlatList
          data={orders}
          keyExtractor={(order) => String(order.id)}
          contentContainerStyle={{ padding: 16, paddingTop: 4 }}
          refreshControl={
            <RefreshControl refreshing={refreshing} onRefresh={refresh} tintColor={t.primary} />
          }
          onEndReached={loadMore}
          onEndReachedThreshold={0.4}
          ListHeaderComponent={
            <>
              <OfflineBanner visible={stale} />
              {error ? <ErrorNote message={error} onRetry={refresh} /> : undefined}
            </>
          }
          ListEmptyComponent={
            error ? undefined : (
              <Empty
                icon="receipt-outline"
                title="No orders yet"
                hint="Orders you place will appear here with their status."
              />
            )
          }
          ListFooterComponent={
            loadingMore ? <ActivityIndicator style={{ marginVertical: 16 }} color={t.primary} /> : undefined
          }
          renderItem={({ item }) => (
            <Pressable
              accessibilityRole="button"
              onPress={() => navigation.navigate('OrderDetail', { id: item.id })}
              style={({ pressed }) => [
                styles.card,
                { borderColor: t.border, backgroundColor: t.canvas, opacity: pressed ? 0.85 : 1 },
              ]}
            >
              <View style={{ flexDirection: 'row', justifyContent: 'space-between', gap: 8 }}>
                <Text style={{ color: t.muted, fontSize: 12 }}>{item.order_number}</Text>
                <StatusBadge status={item.status} />
              </View>
              <Text style={{ color: t.text, fontWeight: '600', marginTop: 8 }} numberOfLines={1}>
                {item.listing_name}
              </Text>
              <Text style={{ color: t.muted, fontSize: 12, marginTop: 2 }}>
                {item.quantity} {item.unit} · {item.farm_name}
              </Text>
              <View style={styles.cardFooter}>
                <Text style={{ color: t.muted, fontSize: 12 }}>{date(item.created_at)}</Text>
                <Text style={{ color: t.text, fontWeight: '700' }}>{money(item.total)}</Text>
              </View>
            </Pressable>
          )}
        />
      )}
    </View>
  );
}

const styles = StyleSheet.create({
  fill: { flex: 1, alignItems: 'center', justifyContent: 'center' },
  card: { borderWidth: 1, borderRadius: 14, padding: 14, marginBottom: 12 },
  cardFooter: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginTop: 10,
  },
});
