import { Ionicons } from '@expo/vector-icons';
import { useCallback, useEffect, useState } from 'react';
import {
  ActivityIndicator,
  FlatList,
  Pressable,
  RefreshControl,
  ScrollView,
  StyleSheet,
  Text,
  TextInput,
  View,
} from 'react-native';
import { api, type AdminUser, type Role } from '../api';
import { date, useTheme } from '../theme';
import { Chip, Empty, ErrorNote, OfflineBanner } from '../ui';

const roleFilters: { label: string; value: Role | '' }[] = [
  { label: 'Everyone', value: '' },
  { label: 'Buyers', value: 'buyer' },
  { label: 'Farmers', value: 'farmer' },
  { label: 'Managers', value: 'manager' },
  { label: 'Admins', value: 'admin' },
];

const statusColor = (status: AdminUser['status']) =>
  status === 'active' ? 'accent' : status === 'suspended' ? 'danger' : 'gold';

export function AdminUsersScreen() {
  const t = useTheme();
  const [input, setInput] = useState('');
  const [query, setQuery] = useState('');
  const [role, setRole] = useState<Role | ''>('');
  const [users, setUsers] = useState<AdminUser[]>([]);
  const [page, setPage] = useState(1);
  const [totalPages, setTotalPages] = useState(1);
  const [total, setTotal] = useState(0);
  const [stale, setStale] = useState(false);
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [loadingMore, setLoadingMore] = useState(false);
  const [error, setError] = useState('');

  const load = useCallback(
    async (targetPage: number, append: boolean) => {
      try {
        const res = await api.adminUsers({ q: query, role, page: targetPage });
        setUsers((prev) => (append ? [...prev, ...res.users] : res.users));
        setPage(res.page);
        setTotalPages(res.total_pages);
        setTotal(res.total);
        setStale(res.stale);
        setError('');
      } catch (e) {
        setError(e instanceof Error ? e.message : 'Could not load accounts.');
      } finally {
        setLoading(false);
      }
    },
    [query, role],
  );

  useEffect(() => {
    setLoading(true);
    load(1, false);
  }, [load]);

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
      <View style={{ paddingHorizontal: 16, paddingTop: 12 }}>
        <View style={[styles.search, { borderColor: t.border, backgroundColor: t.surface }]}>
          <Ionicons name="search" size={17} color={t.muted} />
          <TextInput
            value={input}
            onChangeText={setInput}
            onSubmitEditing={() => setQuery(input.trim())}
            returnKeyType="search"
            placeholder="Search name or email"
            placeholderTextColor={t.muted}
            style={{ flex: 1, color: t.text, paddingVertical: 9, marginLeft: 8 }}
          />
          {input ? (
            <Pressable
              accessibilityRole="button"
              accessibilityLabel="Clear search"
              hitSlop={8}
              onPress={() => {
                setInput('');
                setQuery('');
              }}
            >
              <Text style={{ color: t.primary, fontWeight: '600' }}>Clear</Text>
            </Pressable>
          ) : null}
        </View>
      </View>

      <ScrollView
        horizontal
        showsHorizontalScrollIndicator={false}
        contentContainerStyle={{ paddingHorizontal: 16, paddingVertical: 12 }}
      >
        {roleFilters.map((filter) => (
          <Chip
            key={filter.label}
            label={filter.label}
            active={role === filter.value}
            onPress={() => {
              setLoading(true);
              setRole(filter.value);
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
          data={users}
          keyExtractor={(item) => String(item.id)}
          contentContainerStyle={{ paddingHorizontal: 16, paddingBottom: 24 }}
          refreshControl={<RefreshControl refreshing={refreshing} onRefresh={refresh} tintColor={t.primary} />}
          onEndReached={loadMore}
          onEndReachedThreshold={0.4}
          ListHeaderComponent={
            <>
              <OfflineBanner visible={stale} />
              {error ? <ErrorNote message={error} onRetry={refresh} /> : undefined}
              <Text style={{ color: t.muted, marginBottom: 10 }}>
                {total} {total === 1 ? 'account' : 'accounts'}
              </Text>
            </>
          }
          ListEmptyComponent={
            error ? undefined : <Empty icon="people-outline" title="No accounts match" />
          }
          ListFooterComponent={
            loadingMore ? <ActivityIndicator style={{ marginVertical: 16 }} color={t.primary} /> : undefined
          }
          renderItem={({ item }) => {
            const tone = statusColor(item.status);
            const color = tone === 'accent' ? t.accent : tone === 'danger' ? t.danger : t.gold;
            return (
              <View style={[styles.card, { borderColor: t.border }]}>
                <View style={{ flexDirection: 'row', justifyContent: 'space-between', gap: 8 }}>
                  <View style={{ flex: 1 }}>
                    <Text style={{ color: t.text, fontWeight: '600' }} numberOfLines={1}>
                      {item.name ?? item.email}
                    </Text>
                    {item.name ? (
                      <Text style={{ color: t.muted, fontSize: 12 }} numberOfLines={1}>
                        {item.email}
                      </Text>
                    ) : null}
                  </View>
                  <View style={[styles.roleChip, { borderColor: t.border }]}>
                    <Text style={{ color: t.muted, fontSize: 11, textTransform: 'capitalize' }}>
                      {item.role}
                    </Text>
                  </View>
                </View>
                <View style={styles.metaRow}>
                  <Text style={{ color, fontSize: 12, textTransform: 'capitalize' }}>
                    {item.status.replace('_', ' ')}
                  </Text>
                  {item.verification_status ? (
                    <Text style={{ color: t.muted, fontSize: 12 }}>
                      Farm {item.verification_status}
                    </Text>
                  ) : null}
                  <Text style={{ color: t.muted, fontSize: 12 }}>Joined {date(item.created_at)}</Text>
                </View>
              </View>
            );
          }}
        />
      )}
    </View>
  );
}

const styles = StyleSheet.create({
  fill: { flex: 1, alignItems: 'center', justifyContent: 'center' },
  search: { flexDirection: 'row', alignItems: 'center', borderWidth: 1, borderRadius: 10, paddingHorizontal: 12 },
  card: { borderWidth: 1, borderRadius: 12, padding: 12, marginBottom: 10 },
  roleChip: { borderWidth: 1, borderRadius: 999, paddingHorizontal: 8, paddingVertical: 2, alignSelf: 'flex-start' },
  metaRow: { flexDirection: 'row', flexWrap: 'wrap', gap: 12, marginTop: 8 },
});
