import { Ionicons } from '@expo/vector-icons';
import type { NativeStackScreenProps } from '@react-navigation/native-stack';
import { useCallback, useEffect, useState } from 'react';
import {
  ActivityIndicator,
  FlatList,
  Image,
  Pressable,
  StyleSheet,
  Text,
  View,
} from 'react-native';
import { api, imageUrl, type SavedListing } from '../api';
import type { RootStackParams } from '../navigation';
import { money, useTheme } from '../theme';
import { Empty, ErrorNote, OfflineBanner } from '../ui';

type Props = NativeStackScreenProps<RootStackParams, 'Saved'>;

export function SavedScreen({ navigation }: Props) {
  const t = useTheme();
  const [saved, setSaved] = useState<SavedListing[]>([]);
  const [loading, setLoading] = useState(true);
  const [stale, setStale] = useState(false);
  const [error, setError] = useState('');

  const load = useCallback(async () => {
    try {
      const res = await api.saved();
      setSaved(res.saved);
      setStale(res.stale);
      setError('');
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Could not load saved listings.');
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    load();
  }, [load]);

  if (loading) {
    return (
      <View style={[styles.fill, { backgroundColor: t.canvas }]}>
        <ActivityIndicator color={t.primary} />
      </View>
    );
  }

  if (error) {
    return (
      <View style={[styles.fill, { backgroundColor: t.canvas }]}>
        <ErrorNote message={error} onRetry={load} />
      </View>
    );
  }

  return (
    <FlatList
      style={{ backgroundColor: t.canvas }}
      contentContainerStyle={{ padding: 16 }}
      data={saved}
      keyExtractor={(item) => String(item.listing_id)}
      ListHeaderComponent={<OfflineBanner visible={stale} />}
      ListEmptyComponent={
        <Empty
          icon="heart-outline"
          title="Nothing saved yet"
          hint="Tap the heart on a listing to keep it here."
        />
      }
      renderItem={({ item }) => (
        <Pressable
          accessibilityRole="button"
          onPress={() => navigation.navigate('Listing', { id: item.listing_id })}
          style={({ pressed }) => [
            styles.row,
            { borderColor: t.border, opacity: pressed ? 0.85 : 1 },
          ]}
        >
          {imageUrl(item.image) ? (
            <Image source={{ uri: imageUrl(item.image) }} style={styles.thumb} />
          ) : (
            <View style={[styles.thumb, styles.thumbFallback, { backgroundColor: t.surface }]}>
              <Ionicons name="leaf-outline" size={22} color={t.muted} />
            </View>
          )}
          <View style={{ flex: 1 }}>
            <Text style={{ color: t.text, fontWeight: '600' }} numberOfLines={2}>
              {item.name}
            </Text>
            <Text style={{ color: t.muted, fontSize: 12, marginTop: 2 }}>{item.farm_name}</Text>
            <Text style={{ color: t.primary, fontWeight: '700', marginTop: 4 }}>
              {money(item.price)}
              <Text style={{ color: t.muted, fontWeight: '400', fontSize: 12 }}> / {item.unit}</Text>
            </Text>
            {item.price_dropped && item.price_at_save ? (
              <Text style={{ color: t.accent, fontSize: 12, marginTop: 2 }}>
                Price dropped from {money(item.price_at_save)}
              </Text>
            ) : null}
          </View>
          <Ionicons name="chevron-forward" size={18} color={t.muted} />
        </Pressable>
      )}
    />
  );
}

const styles = StyleSheet.create({
  fill: { flex: 1, alignItems: 'center', justifyContent: 'center', padding: 24 },
  row: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 12,
    borderWidth: 1,
    borderRadius: 14,
    padding: 12,
    marginBottom: 12,
  },
  thumb: { width: 64, height: 64, borderRadius: 10 },
  thumbFallback: { alignItems: 'center', justifyContent: 'center' },
});
