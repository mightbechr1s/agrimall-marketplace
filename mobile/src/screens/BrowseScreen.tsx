import { Ionicons } from '@expo/vector-icons';
import { useNavigation } from '@react-navigation/native';
import { useCallback, useEffect, useRef, useState } from 'react';
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
import { api, type Category, type Listing } from '../api';
import { ListingCard } from '../components/ListingCard';
import type { RootNav } from '../navigation';
import { useTheme } from '../theme';
import { Chip, Empty, ErrorNote, OfflineBanner } from '../ui';

export function BrowseScreen() {
  const t = useTheme();
  const navigation = useNavigation<RootNav>();
  const [input, setInput] = useState('');
  const [query, setQuery] = useState('');
  const [category, setCategory] = useState('');
  const [categories, setCategories] = useState<Category[]>([]);
  const [items, setItems] = useState<Listing[]>([]);
  const [page, setPage] = useState(1);
  const [totalPages, setTotalPages] = useState(1);
  const [total, setTotal] = useState(0);
  const [stale, setStale] = useState(false);
  const [loading, setLoading] = useState(true);
  const [loadingMore, setLoadingMore] = useState(false);
  const [refreshing, setRefreshing] = useState(false);
  const [error, setError] = useState('');

  const keyRef = useRef('');

  const loadPage = useCallback(
    async (targetPage: number, append: boolean) => {
      const key = `${query}|${category}`;
      keyRef.current = key;
      try {
        const res = await api.listings({ q: query, category, page: targetPage });
        if (keyRef.current !== key) return;
        setItems((prev) => (append ? [...prev, ...res.listings] : res.listings));
        setPage(res.page);
        setTotalPages(res.total_pages);
        setTotal(res.total);
        setStale(res.stale);
        setError('');
      } catch (e) {
        if (keyRef.current !== key) return;
        setError(e instanceof Error ? e.message : 'Could not load listings.');
      }
    },
    [query, category],
  );

  useEffect(() => {
    setLoading(true);
    loadPage(1, false).finally(() => setLoading(false));
  }, [loadPage]);

  useEffect(() => {
    api
      .categories()
      .then((res) => setCategories(res.categories))
      .catch(() => undefined);
  }, []);

  const refresh = () => {
    setRefreshing(true);
    loadPage(1, false).finally(() => setRefreshing(false));
  };

  const loadMore = () => {
    if (loadingMore || loading || page >= totalPages) return;
    setLoadingMore(true);
    loadPage(page + 1, true).finally(() => setLoadingMore(false));
  };

  const submitSearch = () => {
    setQuery(input.trim());
    setCategory('');
  };

  const header = (
    <View style={{ paddingTop: 8 }}>
      <View style={styles.searchRow}>
        <View style={[styles.searchBox, { backgroundColor: t.surface, borderColor: t.border }]}>
          <Ionicons name="search" size={18} color={t.muted} />
          <TextInput
            value={input}
            onChangeText={setInput}
            onSubmitEditing={submitSearch}
            returnKeyType="search"
            placeholder="Search produce, farms"
            placeholderTextColor={t.muted}
            style={{ flex: 1, color: t.text, paddingVertical: 10, marginLeft: 8 }}
          />
          {input ? (
            <Pressable
              accessibilityLabel="Clear search"
              onPress={() => {
                setInput('');
                setQuery('');
              }}
            >
              <Ionicons name="close-circle" size={18} color={t.muted} />
            </Pressable>
          ) : null}
        </View>
        <Pressable
          accessibilityRole="button"
          accessibilityLabel="Search"
          onPress={submitSearch}
          style={[styles.searchButton, { backgroundColor: t.primary }]}
        >
          <Ionicons name="arrow-forward" size={18} color={t.onPrimary} />
        </Pressable>
      </View>

      <ScrollView
        horizontal
        showsHorizontalScrollIndicator={false}
        contentContainerStyle={{ paddingVertical: 12 }}
      >
        <Chip label="All" active={category === ''} onPress={() => setCategory('')} />
        {categories.map((c) => (
          <Chip
            key={c.id}
            label={c.name}
            active={category === c.slug}
            onPress={() => setCategory(c.slug)}
          />
        ))}
      </ScrollView>

      <Text style={{ color: t.muted, marginBottom: 12 }}>
        {total} {total === 1 ? 'listing' : 'listings'}
        {query ? ` for “${query}”` : ''}
      </Text>
    </View>
  );

  if (loading) {
    return (
      <View style={[styles.fill, { backgroundColor: t.canvas }]}>
        <ActivityIndicator color={t.primary} />
      </View>
    );
  }

  return (
    <FlatList
      style={{ backgroundColor: t.canvas }}
      contentContainerStyle={styles.list}
      data={items}
      keyExtractor={(item) => String(item.id)}
      numColumns={2}
      columnWrapperStyle={{ gap: 12 }}
      ListHeaderComponent={
        <>
          <OfflineBanner visible={stale} />
          {header}
          {error ? <ErrorNote message={error} onRetry={refresh} /> : undefined}
        </>
      }
      renderItem={({ item }) => (
        <ListingCard listing={item} onPress={() => navigation.navigate('Listing', { id: item.id })} />
      )}
      ListEmptyComponent={
        error ? undefined : (
          <Empty
            icon="search-outline"
            title="No listings found"
            hint="Try a different search or category."
          />
        )
      }
      onEndReached={loadMore}
      onEndReachedThreshold={0.4}
      refreshControl={
        <RefreshControl refreshing={refreshing} onRefresh={refresh} tintColor={t.primary} />
      }
      ListFooterComponent={
        loadingMore ? <ActivityIndicator style={{ marginVertical: 16 }} color={t.primary} /> : undefined
      }
    />
  );
}

const styles = StyleSheet.create({
  fill: { flex: 1, alignItems: 'center', justifyContent: 'center' },
  list: { paddingHorizontal: 16, paddingBottom: 24 },
  searchRow: { flexDirection: 'row', gap: 10, alignItems: 'center' },
  searchBox: {
    flex: 1,
    flexDirection: 'row',
    alignItems: 'center',
    borderWidth: 1,
    borderRadius: 10,
    paddingHorizontal: 12,
  },
  searchButton: { width: 44, height: 44, borderRadius: 10, alignItems: 'center', justifyContent: 'center' },
});
